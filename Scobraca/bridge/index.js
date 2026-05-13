const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
} = require('@whiskeysockets/baileys');
const express = require('express');
const fs = require('fs-extra');
const path = require('path');
const pino = require('pino');
const qrcode = require('qrcode');

const app = express();
const port = Number(process.env.PORT || process.env.WHATSAPP_BRIDGE_PORT || 8080);
const authDir = process.env.WHATSAPP_BRIDGE_AUTH_DIR || path.join(__dirname, 'auth_info_baileys');
const bridgeToken = process.env.WHATSAPP_BRIDGE_TOKEN || process.env.BRIDGE_TOKEN || '';

let sock = null;
let qrCodeBase64 = null;
let connectionStatus = 'starting';
let connectedNumber = null;
let connectingPromise = null;

app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));

app.use((req, res, next) => {
  if (!bridgeToken) {
    next();
    return;
  }

  const bearer = req.headers.authorization || '';
  const headerToken = req.headers['x-bridge-token'] || req.headers['x-whatsapp-token'] || '';
  const provided = bearer.startsWith('Bearer ') ? bearer.slice(7) : headerToken;

  if (provided === bridgeToken) {
    next();
    return;
  }

  res.status(401).json({ error: 'Token invalido para a bridge WhatsApp' });
});

async function connectToWhatsApp() {
  if (connectingPromise) {
    return connectingPromise;
  }

  connectingPromise = (async () => {
    connectionStatus = 'connecting';

    const { state, saveCreds } = await useMultiFileAuthState(authDir);
    const { version } = await fetchLatestBaileysVersion();

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

      if (connection === 'open') {
        connectionStatus = 'connected';
        qrCodeBase64 = null;
        connectedNumber = sock.user?.id ? String(sock.user.id).split(':')[0] : null;
        return;
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

        connectionStatus = shouldReconnect ? 'reconnecting' : 'disconnected';
        qrCodeBase64 = null;
        connectedNumber = null;
        sock = null;

        if (shouldReconnect) {
          setTimeout(() => {
            connectingPromise = null;
            connectToWhatsApp().catch((error) => console.error('Reconnect error:', error));
          }, 3000);
        }
      }
    });

    sock.ev.on('creds.update', saveCreds);
  })().finally(() => {
    connectingPromise = null;
  });

  return connectingPromise;
}

app.get('/status', (_req, res) => {
  res.json({
    connected: connectionStatus === 'connected',
    status: connectionStatus,
    number: connectedNumber,
  });
});

app.get('/qrcode', async (_req, res) => {
  if (!sock && connectionStatus !== 'connected') {
    await connectToWhatsApp();
  }

  if (qrCodeBase64) {
    res.json({ qr: qrCodeBase64, status: connectionStatus });
    return;
  }

  res.json({
    qr: null,
    status: connectionStatus,
    message: connectionStatus === 'connected' ? 'Ja conectado' : 'Gerando QR Code',
  });
});

app.post('/send-message', async (req, res) => {
  const number = String(req.body.number || '').replace(/\D/g, '');
  const text = String(req.body.text || '').trim();

  if (!sock || connectionStatus !== 'connected') {
    res.status(503).json({ error: 'WhatsApp nao esta conectado' });
    return;
  }

  if (!number || !text) {
    res.status(400).json({ error: 'Numero e texto sao obrigatorios' });
    return;
  }

  try {
    const jid = number.includes('@s.whatsapp.net') ? number : `${number}@s.whatsapp.net`;
    const response = await sock.sendMessage(jid, { text });
    res.json({ success: true, response });
  } catch (error) {
    res.status(500).json({ error: error.message || 'Falha ao enviar mensagem' });
  }
});

app.get('/logout', async (_req, res) => {
  try {
    if (sock) {
      await sock.logout();
    }

    await fs.remove(authDir);
    sock = null;
    qrCodeBase64 = null;
    connectedNumber = null;
    connectionStatus = 'disconnected';

    setTimeout(() => {
      connectToWhatsApp().catch((error) => console.error('Restart error:', error));
    }, 1000);

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: error.message || 'Falha ao desconectar WhatsApp' });
  }
});

app.listen(port, () => {
  console.log(`Bridge WhatsApp rodando em http://localhost:${port}`);
  connectToWhatsApp().catch((error) => {
    connectionStatus = 'error';
    console.error('Startup error:', error);
  });
});
