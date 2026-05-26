require('dotenv').config({ path: require('path').join(__dirname, '.env') });

const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    downloadMediaMessage,
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const express = require('express');
const qrcode = require('qrcode');
const { Boom } = require('@hapi/boom');
const fs = require('fs-extra');
const path = require('path');

const app = express();
const port = process.env.PORT || 8080;
const host = process.env.HOST || '0.0.0.0';
const bridgeApiKey = String(process.env.BRIDGE_API_KEY || process.env.ARTEFLOR_BRIDGE_API_KEY || '').trim();
const requireBridgeApiKey = String(process.env.BRIDGE_REQUIRE_API_KEY || 'true').toLowerCase() !== 'false';
const authPath = process.env.BAILEYS_AUTH_DIR || path.join(__dirname, 'auth_info_baileys');
const inboundMediaWebhookUrl = String(process.env.INBOUND_MEDIA_WEBHOOK_URL || '').trim();

app.disable('x-powered-by');
app.use(express.json({ limit: '12mb' }));
app.use(express.urlencoded({ extended: true, limit: '12mb' }));

let sock;
let qrCodeBase64 = null;
let connectionStatus = 'connecting';
let connectedNumber = null;
let isConnecting = false;
let reconnectTimeout = null;

function requireApiKey(req, res, next) {
    if (!requireBridgeApiKey) {
        return next();
    }

    if (!bridgeApiKey) {
        return res.status(503).json({ error: 'BRIDGE_API_KEY não configurada no bridge.' });
    }

    const headerKey = String(req.get('x-api-key') || '').trim();
    const bearerKey = String(req.get('authorization') || '').replace(/^Bearer\s+/i, '').trim();

    if (headerKey !== bridgeApiKey && bearerKey !== bridgeApiKey) {
        return res.status(401).json({ error: 'API key inválida.' });
    }

    return next();
}

function normalizeNumber(number) {
    let digits = String(number || '').replace(/\D+/g, '');

    if (!digits) {
        return null;
    }

    if (!digits.startsWith('55') && (digits.length === 10 || digits.length === 11)) {
        digits = `55${digits}`;
    }

    if (digits.length < 10 || digits.length > 15) {
        return null;
    }

    return digits;
}

function scheduleReconnect(delay = 2000) {
    if (reconnectTimeout) {
        return;
    }

    reconnectTimeout = setTimeout(() => {
        reconnectTimeout = null;
        connectToWhatsApp().catch((err) => {
            console.error('Erro ao reconectar WhatsApp:', err);
            scheduleReconnect(5000);
        });
    }, delay);
}

async function connectToWhatsApp() {
    if (isConnecting) {
        return;
    }

    isConnecting = true;

    try {
        const { state, saveCreds } = await useMultiFileAuthState(authPath);
        const { version } = await fetchLatestBaileysVersion();

        sock = makeWASocket({
            version,
            printQRInTerminal: process.env.PRINT_QR_IN_TERMINAL !== 'false',
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' })),
            },
            logger: pino({ level: 'silent' }),
            browser: ['ArteFlor', 'Chrome', '1.0.0'],
            markOnlineOnConnect: false,
            syncFullHistory: false,
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                qrCodeBase64 = await qrcode.toDataURL(qr);
                connectionStatus = 'waiting_qr';
                connectedNumber = null;
                console.log('Novo QR Code gerado');
            }

            if (connection === 'close') {
                const statusCode = lastDisconnect?.error instanceof Boom
                    ? lastDisconnect.error.output?.statusCode
                    : lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

                console.log('Conexão fechada. Tentando reconectar:', shouldReconnect);

                connectionStatus = 'disconnected';
                qrCodeBase64 = null;
                connectedNumber = null;

                if (!shouldReconnect) {
                    await fs.remove(authPath).catch(() => null);
                }

                // Mesmo modelo do Tático GPS: reinicia o loop para gerar novo QR.
                scheduleReconnect(1000);
            } else if (connection === 'open') {
                console.log('Conexão aberta com sucesso!');
                connectionStatus = 'connected';
                qrCodeBase64 = null;
                connectedNumber = normalizeNumber(sock.user?.id?.split(':')?.[0] || '');
            }
        });

        sock.ev.on('creds.update', saveCreds);

        // Evento para processar mensagens recebidas.
        // Mantém a mesma base do Tático GPS para evoluir chatbot/comprovantes no futuro.
        sock.ev.on('messages.upsert', async (m) => {
            if (m.type !== 'notify') return;

            for (const msg of m.messages) {
                if (msg.key.fromMe) continue;

                const sender = String(msg.key.remoteJid || '').split('@')[0];
                const messageType = Object.keys(msg.message || {})[0];

                if (messageType === 'imageMessage' || messageType === 'documentWithCaptionMessage' || messageType === 'documentMessage') {
                    console.log(`Mídia recebida de ${sender}. Processando como possível comprovante...`);

                    if (!inboundMediaWebhookUrl) {
                        console.log('INBOUND_MEDIA_WEBHOOK_URL não configurada. Mídia ignorada.');
                        continue;
                    }

                    try {
                        const buffer = await downloadMediaMessage(msg, 'buffer', {}, {
                            logger: pino({ level: 'silent' }),
                            reuploadRequest: sock.updateMediaMessage,
                        });

                        const base64 = buffer.toString('base64');
                        const mimeType = msg.message[messageType]?.mimetype || 'image/jpeg';

                        await fetch(inboundMediaWebhookUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                ...(bridgeApiKey ? { 'X-API-Key': bridgeApiKey } : {}),
                            },
                            body: JSON.stringify({
                                sender,
                                business_id: sock.user?.id?.split(':')?.[0] || connectedNumber,
                                media: base64,
                                mimeType,
                                messageId: msg.key.id,
                            }),
                        });

                        console.log(`Notificação de mídia enviada para o PHP (${sender})`);
                    } catch (err) {
                        console.error('Erro ao processar mídia:', err);
                    }
                }
            }
        });
    } finally {
        isConnecting = false;
    }
}

// Endpoints da API para o PHP
app.get('/health', (req, res) => {
    res.json({
        success: true,
        service: 'arteflor-whatsapp-bridge',
        status: connectionStatus,
        connected: connectionStatus === 'connected',
        number: connectedNumber,
    });
});

app.get('/status', requireApiKey, (req, res) => {
    res.json({
        connected: connectionStatus === 'connected',
        status: connectionStatus,
        number: connectedNumber,
    });
});

app.get('/qrcode', requireApiKey, (req, res) => {
    if (qrCodeBase64) {
        res.json({ qr: qrCodeBase64, status: connectionStatus });
    } else {
        res.json({
            qr: null,
            status: connectionStatus,
            number: connectedNumber,
            message: connectionStatus === 'connected' ? 'Já conectado' : 'Gerando...',
        });
    }
});

app.post('/send-message', requireApiKey, async (req, res) => {
    const { number, text } = req.body;

    if (!sock || connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp não está conectado' });
    }

    if (!number || !text) {
        return res.status(400).json({ error: 'Número e texto são obrigatórios' });
    }

    const normalizedNumber = normalizeNumber(number);
    if (!normalizedNumber) {
        return res.status(400).json({ error: 'Número inválido' });
    }

    try {
        const jid = String(number).includes('@s.whatsapp.net') ? number : `${normalizedNumber}@s.whatsapp.net`;
        const result = await sock.sendMessage(jid, { text: String(text) });
        res.json({ success: true, messageId: result?.key?.id || null });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

async function logout(req, res) {
    try {
        if (sock) {
            await sock.logout().catch(() => null);
        }

        await fs.remove(authPath);

        connectionStatus = 'disconnected';
        qrCodeBase64 = null;
        connectedNumber = null;
        sock = null;

        scheduleReconnect(1000);

        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
}

app.get('/logout', requireApiKey, logout);
app.post('/logout', requireApiKey, logout);

app.listen(port, host, () => {
    console.log(`Bridge WhatsApp ArteFlor rodando em http://${host}:${port}`);

    if (!requireBridgeApiKey) {
        console.warn('Atenção: BRIDGE_REQUIRE_API_KEY=false. Use apenas se o bridge não estiver exposto publicamente.');
    } else if (!bridgeApiKey) {
        console.warn('Atenção: configure BRIDGE_API_KEY no .env antes de usar em produção.');
    }

    connectToWhatsApp().catch((err) => {
        console.error('Erro ao iniciar conexão WhatsApp:', err);
        scheduleReconnect(5000);
    });
});
