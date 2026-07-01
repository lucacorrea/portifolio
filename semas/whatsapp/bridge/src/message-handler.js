const fs = require('fs-extra');
const { sendWebhook } = require('./webhook-handler');

function createMessageHandler({ logger, instanceId, manager }) {
  async function handleIncoming(sock, message) {
    if (!message || message.key?.fromMe) {
      return;
    }

    const remoteJid = message.key?.remoteJid || '';
    const sender = remoteJid.split('@')[0] || '';
    const text = extractText(message);
    if (!sender || !text) {
      return;
    }

    await sendWebhook({
      logger,
      payload: {
        instanceId,
        event: 'message.received',
        messageId: message.key?.id || null,
        from: sender,
        type: 'text',
        text,
        timestamp: new Date().toISOString()
      }
    });
  }

  async function sendText(sock, payload) {
    const idempotency = await readIdempotency(manager);
    if (idempotency[payload.idempotencyKey]) {
      return {
        success: true,
        message: 'Mensagem ja processada.',
        data: idempotency[payload.idempotencyKey]
      };
    }

    const jid = payload.to.includes('@s.whatsapp.net') ? payload.to : `${payload.to}@s.whatsapp.net`;
    const result = await sock.sendMessage(jid, { text: payload.text });
    const data = {
      externalId: result?.key?.id || null,
      to: payload.to,
      sentAt: new Date().toISOString()
    };
    idempotency[payload.idempotencyKey] = data;
    await writeIdempotency(manager, idempotency);

    return { success: true, message: 'Mensagem enviada.', data };
  }

  return { handleIncoming, sendText };
}

function extractText(message) {
  const payload = message.message || {};
  return (
    payload.conversation ||
    payload.extendedTextMessage?.text ||
    payload.imageMessage?.caption ||
    payload.documentMessage?.caption ||
    ''
  ).trim();
}

async function readIdempotency(manager) {
  const file = manager.cacheFile('idempotency.json');
  try {
    return await fs.readJson(file);
  } catch {
    return {};
  }
}

async function writeIdempotency(manager, data) {
  const file = manager.cacheFile('idempotency.json');
  await fs.writeJson(file, data, { spaces: 0 });
}

module.exports = { createMessageHandler };
