const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason, 
    fetchLatestBaileysVersion, 
    makeCacheableSignalKeyStore 
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const express = require('express');
const qrcode = require('qrcode');
const { Boom } = require('@hapi/boom');
const fs = require('fs-extra');
const path = require('path');

const app = express();
const port = 8080;

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
            console.log('Conexão fechada devido a ', lastDisconnect.error, ', tentando reconectar: ', shouldReconnect);
            connectionStatus = 'disconnected';
            qrCodeBase64 = null;
            if (shouldReconnect) {
                connectToWhatsApp();
            }
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
        // Futura lógica do chatbot entrará aqui
        // console.log(JSON.stringify(m, undefined, 2));
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
