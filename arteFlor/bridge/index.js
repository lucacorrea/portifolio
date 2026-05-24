require('dotenv').config({ path: require('path').join(__dirname, '.env') });

const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
} = require('@whiskeysockets/baileys');
const express = require('express');
const qrcode = require('qrcode');
const pino = require('pino');
const fs = require('fs-extra');
const path = require('path');

const app = express();

const PORT = Number(process.env.PORT || 8080);
const HOST = process.env.HOST || '0.0.0.0';
const BRIDGE_API_KEY = String(process.env.BRIDGE_API_KEY || process.env.ARTEFLOR_BRIDGE_API_KEY || '').trim();
const AUTH_DIR = process.env.BAILEYS_AUTH_DIR || path.join(__dirname, 'auth_info_baileys');
const LOG_LEVEL = process.env.LOG_LEVEL || 'info';

const logger = pino({ level: LOG_LEVEL });
const baileysLogger = pino({ level: process.env.BAILEYS_LOG_LEVEL || 'silent' });

app.disable('x-powered-by');
app.use(express.json({ limit: '512kb' }));
app.use(express.urlencoded({ extended: true, limit: '512kb' }));

let sock = null;
let qrCodeBase64 = null;
let connectionStatus = 'starting';
let connectedNumber = null;
let lastError = null;
let reconnectTimer = null;
let isStarting = false;

function requireApiKey(req, res, next) {
  if (!BRIDGE_API_KEY) {
    return res.status(503).json({
      success: false,
      error: 'BRIDGE_API_KEY não configurada no bridge.',
    });
  }

  const receivedKey = String(req.get('x-api-key') || '').trim();
  const bearer = String(req.get('authorization') || '').replace(/^Bearer\s+/i, '').trim();

  if (receivedKey !== BRIDGE_API_KEY && bearer !== BRIDGE_API_KEY) {
    return res.status(401).json({
      success: false,
      error: 'API key inválida.',
    });
  }

  return next();
}

function normalizeWhatsAppNumber(number) {
  let digits = String(number || '').replace(/\D+/g, '');

  if (!digits) return null;

  if (!digits.startsWith('55') && (digits.length === 10 || digits.length === 11)) {
    digits = `55${digits}`;
  }

  if (digits.length < 10 || digits.length > 15) {
    return null;
  }

  return digits;
}

function scheduleReconnect(delayMs = 2000) {
  if (reconnectTimer) return;

  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    connectToWhatsApp().catch((error) => {
      lastError = error.message;
      logger.error({ err: error }, 'Falha ao reconectar WhatsApp');
      scheduleReconnect(5000);
    });
  }, delayMs);
}

async function connectToWhatsApp() {
  if (isStarting) return;
  isStarting = true;

  try {
    connectionStatus = connectionStatus === 'connected' ? 'connected' : 'connecting';

    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
      version,
      printQRInTerminal: process.env.PRINT_QR_IN_TERMINAL === 'true',
      auth: {
        creds: state.creds,
        keys: makeCacheableSignalKeyStore(state.keys, baileysLogger),
      },
      logger: baileysLogger,
      browser: ['ArteFlor', 'Chrome', '1.0.0'],
      markOnlineOnConnect: false,
      syncFullHistory: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        qrCodeBase64 = await qrcode.toDataURL(qr);
        connectionStatus = 'waiting_qr';
        connectedNumber = null;
        lastError = null;
        logger.info('Novo QR Code gerado');
      }

      if (connection === 'open') {
        connectionStatus = 'connected';
        qrCodeBase64 = null;
        connectedNumber = normalizeWhatsAppNumber(sock?.user?.id?.split(':')?.[0] || '');
        lastError = null;
        logger.info({ number: connectedNumber }, 'WhatsApp conectado');
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = statusCode === DisconnectReason.loggedOut;

        connectionStatus = loggedOut ? 'logged_out' : 'disconnected';
        qrCodeBase64 = null;
        connectedNumber = null;
        lastError = lastDisconnect?.error?.message || null;

        logger.warn({ statusCode, loggedOut, lastError }, 'Conexão WhatsApp fechada');

        if (loggedOut) {
          await fs.remove(AUTH_DIR);
        }

        scheduleReconnect(loggedOut ? 1000 : 3000);
      }
    });

    sock.ev.on('messages.upsert', async (event) => {
      if (event.type !== 'notify') return;

      for (const msg of event.messages || []) {
        if (msg.key?.fromMe) continue;
        const sender = String(msg.key?.remoteJid || '').split('@')[0];
        const messageType = Object.keys(msg.message || {})[0] || 'unknown';
        logger.info({ sender, messageType }, 'Mensagem recebida no bridge ArteFlor');
      }
    });
  } finally {
    isStarting = false;
  }
}

app.get('/health', (req, res) => {
  res.json({
    success: true,
    service: 'arteflor-whatsapp-bridge',
    status: connectionStatus,
    uptime: process.uptime(),
  });
});

app.get('/status', requireApiKey, (req, res) => {
  res.json({
    success: true,
    connected: connectionStatus === 'connected',
    status: connectionStatus,
    number: connectedNumber,
    hasQr: Boolean(qrCodeBase64),
    lastError,
  });
});

app.get('/qrcode', requireApiKey, (req, res) => {
  if (connectionStatus === 'connected') {
    return res.json({
      success: true,
      connected: true,
      status: connectionStatus,
      number: connectedNumber,
      qr: null,
      message: 'Já conectado.',
    });
  }

  if (qrCodeBase64) {
    return res.json({
      success: true,
      connected: false,
      status: connectionStatus,
      qr: qrCodeBase64,
      message: 'Escaneie o QR com o WhatsApp da empresa.',
    });
  }

  scheduleReconnect(500);

  return res.json({
    success: false,
    connected: false,
    status: connectionStatus,
    qr: null,
    message: 'QR ainda não disponível. Aguarde alguns segundos e consulte novamente.',
  });
});

app.post('/send-message', requireApiKey, async (req, res) => {
  const number = normalizeWhatsAppNumber(req.body?.number);
  const text = String(req.body?.text || '').trim();

  if (!sock || connectionStatus !== 'connected') {
    return res.status(503).json({
      success: false,
      error: 'WhatsApp não está conectado.',
      status: connectionStatus,
    });
  }

  if (!number || !text) {
    return res.status(400).json({
      success: false,
      error: 'Número e texto são obrigatórios.',
    });
  }

  if (text.length > 4096) {
    return res.status(422).json({
      success: false,
      error: 'Mensagem muito longa. Limite: 4096 caracteres.',
    });
  }

  try {
    const jid = `${number}@s.whatsapp.net`;
    const result = await sock.sendMessage(jid, { text });

    return res.json({
      success: true,
      status: 'sent',
      number,
      messageId: result?.key?.id || null,
    });
  } catch (error) {
    logger.error({ err: error, number }, 'Erro ao enviar mensagem');

    return res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});

async function logoutHandler(req, res) {
  try {
    if (sock) {
      await sock.logout().catch(() => null);
    }

    await fs.remove(AUTH_DIR);

    sock = null;
    qrCodeBase64 = null;
    connectedNumber = null;
    connectionStatus = 'logged_out';
    lastError = null;

    scheduleReconnect(500);

    return res.json({
      success: true,
      message: 'Sessão removida. Um novo QR será gerado.',
    });
  } catch (error) {
    logger.error({ err: error }, 'Erro ao remover sessão');

    return res.status(500).json({
      success: false,
      error: error.message,
    });
  }
}

app.post('/logout', requireApiKey, logoutHandler);
app.get('/logout', requireApiKey, logoutHandler);

app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'Endpoint não encontrado.',
  });
});

app.use((error, req, res, next) => {
  logger.error({ err: error }, 'Erro inesperado no bridge');
  res.status(500).json({
    success: false,
    error: 'Erro interno no bridge.',
  });
});

app.listen(PORT, HOST, () => {
  logger.info({ host: HOST, port: PORT }, 'Bridge WhatsApp ArteFlor iniciado');
  if (!BRIDGE_API_KEY) {
    logger.warn('BRIDGE_API_KEY não configurada. Endpoints protegidos retornarão 503.');
  }

  connectToWhatsApp().catch((error) => {
    lastError = error.message;
    logger.error({ err: error }, 'Falha inicial ao conectar WhatsApp');
    scheduleReconnect(5000);
  });
});
