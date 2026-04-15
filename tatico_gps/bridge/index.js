const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason, 
    fetchLatestBaileysVersion, 
    makeCacheableSignalKeyStore,
    downloadMediaMessage,
    downloadContentFromMessage 
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const express = require('express');
const qrcode = require('qrcode');
const { Boom } = require('@hapi/boom');
const fs = require('fs-extra');
const path = require('path');

const app = express();
const port = 8080;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

let sock;
let qrCodeBase64 = null;
let connectionStatus = 'connecting';
let connectedNumber = null;

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(path.join(__dirname, 'auth_info_baileys'));
    const { version, isLatest } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        printQRInTerminal: true,
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' })),
        },
        logger: pino({ level: 'silent' }),
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            qrCodeBase64 = await qrcode.toDataURL(qr);
            connectionStatus = 'waiting_qr';
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom)?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('Conexão fechada. Tentando reconectar:', shouldReconnect);
            
            connectionStatus = 'disconnected';
            qrCodeBase64 = null;
            connectedNumber = null;

            // Mesmo que tenha sido logout proposital, reiniciamos o loop 
            // para que um NOVO QR Code seja gerado para a página.
            connectToWhatsApp();
        } else if (connection === 'open') {
            console.log('Conexão aberta com sucesso!');
            connectionStatus = 'connected';
            qrCodeBase64 = null;
            connectedNumber = sock.user.id.split(':')[0];
        }
    });

    sock.ev.on('creds.update', saveCreds);

    // Evento para processar mensagens (Base para o seu futuro Chatbot)
    sock.ev.on('messages.upsert', async m => {
        if (m.type !== 'notify') return;

        for (const msg of m.messages) {
            if (msg.key.fromMe) continue; // Ignorar mensagens enviadas por nós

            const sender = msg.key.remoteJid.split('@')[0];
            const messageType = Object.keys(msg.message || {})[0];

            // Detectar Imagem ou Documento (PDF)
            if (messageType === 'imageMessage' || messageType === 'documentWithCaptionMessage' || messageType === 'documentMessage') {
                console.log(`Mídia recebida de ${sender}. Processando como possível comprovante...`);

                try {
                    // Baixar a mídia
                    const buffer = await downloadMediaMessage(msg, 'buffer', {}, { 
                        logger: pino({ level: 'silent' }),
                        reuploadRequest: sock.updateMediaMessage
                    });

                    const base64 = buffer.toString('base64');
                    const mimeType = msg.message[messageType]?.mimetype || 'image/jpeg';

                    // Notificar o PHP
                    // Ajuste a URL para o seu domínio principal
                    const webhookUrl = 'https://lucascorrea.pro/tatico_gps/adm/php/whatsapp/processar_comprovante.php';
                    
                    await fetch(webhookUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            sender,
                            business_id: sock.user.id.split(':')[0],
                            media: base64,
                            mimeType,
                            messageId: msg.key.id
                        })
                    });

                    console.log(`Notificação de comprovante enviada para o PHP (${sender})`);
                } catch (err) {
                    console.error('Erro ao processar mídia:', err);
                }
            }
        }
    });
}

// Endpoints da API para o PHP
app.get('/status', (req, res) => {
    res.json({
        connected: connectionStatus === 'connected',
        status: connectionStatus,
        number: connectedNumber
    });
});

app.get('/qrcode', (req, res) => {
    if (qrCodeBase64) {
        res.json({ qr: qrCodeBase64 });
    } else {
        res.json({ qr: null, message: connectionStatus === 'connected' ? 'Já conectado' : 'Gerando...' });
    }
});

app.post('/send-message', async (req, res) => {
    const { number, text } = req.body;

    if (!sock || connectionStatus !== 'connected') {
        return res.status(503).json({ error: 'WhatsApp não está conectado' });
    }

    if (!number || !text) {
        return res.status(400).json({ error: 'Número e texto são obrigatórios' });
    }

    try {
        const jid = number.includes('@s.whatsapp.net') ? number : `${number}@s.whatsapp.net`;
        await sock.sendMessage(jid, { text });
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

app.get('/logout', async (req, res) => {
    try {
        await sock.logout();
        await fs.remove(path.join(__dirname, 'auth_info_baileys'));
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

app.listen(port, () => {
    console.log(`Bridge WhatsApp rodando em http://localhost:${port}`);
    connectToWhatsApp();
});
