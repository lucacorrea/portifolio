const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
  downloadContentFromMessage,
} = require('@whiskeysockets/baileys');
const express = require('express');
const fs = require('fs-extra');
const path = require('path');
const pino = require('pino');
const qrcode = require('qrcode');

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) {
    return;
  }

  const content = fs.readFileSync(filePath, 'utf8');

  for (const line of content.split(/\r?\n/)) {
    const trimmed = line.trim();

    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }

    const separatorIndex = trimmed.indexOf('=');

    if (separatorIndex <= 0) {
      continue;
    }

    const key = trimmed.slice(0, separatorIndex).trim();

    if (
      !/^[A-Za-z_][A-Za-z0-9_]*$/.test(key)
      || process.env[key] !== undefined
      || (!key.startsWith('WHATSAPP_') && key !== 'PORT' && key !== 'BRIDGE_TOKEN' && key !== 'APP_URL' && key !== 'APP_BASE_PATH')
    ) {
      continue;
    }

    let value = trimmed.slice(separatorIndex + 1).trim();

    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }

    process.env[key] = value;
  }
}

loadEnvFile(path.resolve(__dirname, '..', '.env'));
loadEnvFile(path.resolve(__dirname, '.env'));

const app = express();
const port = Number(process.env.PORT || process.env.WHATSAPP_BRIDGE_PORT || 8080);
const authRootDir = process.env.WHATSAPP_BRIDGE_AUTH_DIR || path.join(__dirname, 'auth_info_baileys');
const bridgeToken = process.env.WHATSAPP_BRIDGE_TOKEN || process.env.BRIDGE_TOKEN || '';
const webhookToken = process.env.WHATSAPP_WEBHOOK_TOKEN || bridgeToken;
const maxMediaBytes = Number(process.env.WHATSAPP_WEBHOOK_MAX_MEDIA_BYTES || 8 * 1024 * 1024);
const sessions = new Map();

app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function sanitizeInstanceName(value) {
  const sanitized = String(value || 'default')
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^[-_]+|[-_]+$/g, '')
    .slice(0, 100);

  return sanitized || 'default';
}

function requestInstanceName(req) {
  return sanitizeInstanceName(
    req.query.instance
    || req.query.instanceName
    || req.body?.instance
    || req.body?.instanceName
    || 'default'
  );
}

function instanceAuthDir(instanceName) {
  return path.join(authRootDir, instanceName);
}

function getSession(instanceName) {
  const normalized = sanitizeInstanceName(instanceName);

  if (!sessions.has(normalized)) {
    sessions.set(normalized, {
      instanceName: normalized,
      sock: null,
      qrCodeBase64: null,
      qrCodeRaw: null,
      connectionStatus: 'starting',
      connectedNumber: null,
      connectingPromise: null,
      lastError: null,
    });
  }

  return sessions.get(normalized);
}

function buildWebhookUrl() {
  if (process.env.WHATSAPP_WEBHOOK_URL) {
    return process.env.WHATSAPP_WEBHOOK_URL;
  }

  const appUrl = String(process.env.APP_URL || '').replace(/\/+$/, '');
  const basePath = String(process.env.APP_BASE_PATH || '').replace(/^\/?/, '/').replace(/\/+$/, '');

  if (!appUrl || /(?:seudominio\.com\.br|example\.com)$/i.test(appUrl.replace(/^https?:\/\//i, ''))) {
    return '';
  }

  return `${appUrl}${basePath === '/' ? '' : basePath}/webhooks/whatsapp_comprovante.php`;
}

const webhookUrl = buildWebhookUrl();

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

function unwrapMessage(message) {
  let content = message || {};

  if (content.ephemeralMessage?.message) {
    content = content.ephemeralMessage.message;
  }

  if (content.viewOnceMessage?.message) {
    content = content.viewOnceMessage.message;
  }

  if (content.viewOnceMessageV2?.message) {
    content = content.viewOnceMessageV2.message;
  }

  return content;
}

function mediaFromMessage(message) {
  const content = unwrapMessage(message);

  if (content.imageMessage) {
    return {
      media: content.imageMessage,
      type: 'image',
      mimeType: content.imageMessage.mimetype || 'image/jpeg',
      caption: content.imageMessage.caption || '',
    };
  }

  if (content.documentMessage) {
    const mimeType = content.documentMessage.mimetype || 'application/octet-stream';
    const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(mimeType)) {
      return null;
    }

    return {
      media: content.documentMessage,
      type: 'document',
      mimeType,
      caption: content.documentMessage.caption || content.documentMessage.fileName || '',
    };
  }

  return null;
}

async function downloadMedia(mediaInfo) {
  const chunks = [];
  let totalBytes = 0;
  const stream = await downloadContentFromMessage(mediaInfo.media, mediaInfo.type);

  for await (const chunk of stream) {
    totalBytes += chunk.length;

    if (totalBytes > maxMediaBytes) {
      throw new Error('Arquivo recebido excede o limite da bridge');
    }

    chunks.push(chunk);
  }

  return Buffer.concat(chunks);
}

async function postWebhook(payload) {
  if (!webhookUrl) {
    return;
  }

  if (typeof fetch !== 'function') {
    throw new Error('Node.js 18 ou superior e necessario para enviar webhooks');
  }

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };

  if (webhookToken) {
    headers.Authorization = `Bearer ${webhookToken}`;
  }

  const response = await fetch(webhookUrl, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new Error(`Webhook retornou HTTP ${response.status}: ${text.slice(0, 200)}`);
  }
}

async function handleIncomingMessages(session, event) {
  if (!webhookUrl || !event?.messages?.length) {
    return;
  }

  for (const message of event.messages) {
    if (!message?.message || message.key?.fromMe) {
      continue;
    }

    const remoteJid = String(message.key?.remoteJid || '');

    if (!remoteJid.endsWith('@s.whatsapp.net')) {
      continue;
    }

    const mediaInfo = mediaFromMessage(message.message);

    if (!mediaInfo) {
      continue;
    }

    try {
      const mediaBuffer = await downloadMedia(mediaInfo);

      await postWebhook({
        instanceName: session.instanceName,
        sender: remoteJid.replace('@s.whatsapp.net', ''),
        media: mediaBuffer.toString('base64'),
        mimeType: mediaInfo.mimeType,
        messageId: message.key?.id || null,
        timestamp: message.messageTimestamp || null,
        caption: mediaInfo.caption,
      });
    } catch (error) {
      console.error(`[${session.instanceName}] Erro ao processar midia recebida:`, error.message || error);
    }
  }
}

async function connectToWhatsApp(instanceName) {
  const session = getSession(instanceName);

  if (session.connectingPromise) {
    return session.connectingPromise;
  }

  session.connectingPromise = (async () => {
    session.connectionStatus = 'connecting';
    session.lastError = null;

    const { state, saveCreds } = await useMultiFileAuthState(instanceAuthDir(session.instanceName));
    const { version } = await fetchLatestBaileysVersion();

    session.sock = makeWASocket({
      version,
      printQRInTerminal: true,
      auth: {
        creds: state.creds,
        keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' })),
      },
      logger: pino({ level: 'silent' }),
    });

    session.sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        session.qrCodeRaw = qr;
        session.qrCodeBase64 = await qrcode.toDataURL(qr);
        session.connectionStatus = 'waiting_qr';
      }

      if (connection === 'open') {
        session.connectionStatus = 'connected';
        session.qrCodeBase64 = null;
        session.qrCodeRaw = null;
        session.connectedNumber = session.sock.user?.id ? String(session.sock.user.id).split(':')[0] : null;
        session.lastError = null;
        return;
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

        session.connectionStatus = shouldReconnect ? 'reconnecting' : 'disconnected';
        session.qrCodeBase64 = null;
        session.qrCodeRaw = null;
        session.connectedNumber = null;
        session.sock = null;
        session.lastError = lastDisconnect?.error?.message || null;

        if (shouldReconnect) {
          setTimeout(() => {
            session.connectingPromise = null;
            connectToWhatsApp(session.instanceName).catch((error) => {
              session.connectionStatus = 'error';
              session.lastError = error.message || String(error);
              console.error(`[${session.instanceName}] Reconnect error:`, error);
            });
          }, 3000);
        }
      }
    });

    session.sock.ev.on('creds.update', saveCreds);
    session.sock.ev.on('messages.upsert', (event) => {
      handleIncomingMessages(session, event).catch((error) => {
        console.error(`[${session.instanceName}] Incoming message error:`, error);
      });
    });
  })().catch((error) => {
    session.connectionStatus = 'error';
    session.lastError = error.message || String(error);
    throw error;
  }).finally(() => {
    session.connectingPromise = null;
  });

  return session.connectingPromise;
}

app.get('/status', async (req, res) => {
  const instanceName = requestInstanceName(req);
  const session = getSession(instanceName);

  if (!session.sock && ['starting', 'reconnecting'].includes(session.connectionStatus)) {
    connectToWhatsApp(instanceName).catch((error) => console.error(`[${instanceName}] Startup error:`, error));
  }

  res.json({
    connected: session.connectionStatus === 'connected',
    status: session.connectionStatus,
    number: session.connectedNumber,
    instanceName,
    error: session.lastError,
  });
});

app.get('/qrcode', async (req, res) => {
  const instanceName = requestInstanceName(req);
  const session = getSession(instanceName);

  if (!session.sock && session.connectionStatus !== 'connected') {
    await connectToWhatsApp(instanceName).catch((error) => {
      session.connectionStatus = 'error';
      session.lastError = error.message || String(error);
    });
  }

  const startedAt = Date.now();

  while (!session.qrCodeBase64 && session.connectionStatus !== 'connected' && session.connectionStatus !== 'error' && Date.now() - startedAt < 12000) {
    await sleep(500);
  }

  if (session.qrCodeBase64) {
    res.json({
      qr: session.qrCodeBase64,
      code: session.qrCodeRaw,
      status: session.connectionStatus,
      instanceName,
    });
    return;
  }

  res.json({
    qr: null,
    code: session.qrCodeRaw,
    status: session.connectionStatus,
    instanceName,
    message: session.connectionStatus === 'connected' ? 'Ja conectado' : (session.lastError || 'Gerando QR Code'),
  });
});

app.post('/send-message', async (req, res) => {
  const instanceName = requestInstanceName(req);
  const session = getSession(instanceName);
  const rawNumber = String(req.body.number || '').trim();
  const number = rawNumber.includes('@') ? rawNumber : rawNumber.replace(/\D/g, '');
  const text = String(req.body.text || '').trim();

  if (!session.sock || session.connectionStatus !== 'connected') {
    res.status(503).json({ error: 'WhatsApp nao esta conectado', instanceName });
    return;
  }

  if (!number || !text) {
    res.status(400).json({ error: 'Numero e texto sao obrigatorios', instanceName });
    return;
  }

  try {
    const jid = number.includes('@s.whatsapp.net') ? number : `${number}@s.whatsapp.net`;
    const response = await session.sock.sendMessage(jid, { text });
    res.json({ success: true, response, instanceName });
  } catch (error) {
    res.status(500).json({ error: error.message || 'Falha ao enviar mensagem', instanceName });
  }
});

async function logoutInstance(req, res) {
  const instanceName = requestInstanceName(req);
  const session = getSession(instanceName);

  try {
    if (session.sock) {
      await session.sock.logout().catch(() => null);
    }

    await fs.remove(instanceAuthDir(instanceName));
    session.sock = null;
    session.qrCodeBase64 = null;
    session.qrCodeRaw = null;
    session.connectedNumber = null;
    session.connectionStatus = 'disconnected';
    session.lastError = null;

    res.json({ success: true, instanceName });
  } catch (error) {
    session.connectionStatus = 'error';
    session.lastError = error.message || String(error);
    res.status(500).json({ error: error.message || 'Falha ao desconectar WhatsApp', instanceName });
  }
}

app.get('/logout', logoutInstance);
app.post('/logout', logoutInstance);

app.listen(port, () => {
  console.log(`Bridge WhatsApp rodando em http://localhost:${port}`);
  if (webhookUrl) {
    console.log(`Webhook de comprovantes configurado: ${webhookUrl}`);
  }
});
