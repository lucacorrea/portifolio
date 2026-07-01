const path = require('path');
const dotenv = require('dotenv');
const express = require('express');
const { createAuthMiddleware } = require('./auth-middleware');
const { createWhatsappClient } = require('./whatsapp-client');
const { validateSendPayload, validatePairingPayload } = require('./validators');

dotenv.config({ path: path.join(__dirname, '..', '..', '.env') });

function createApp({ logger }) {
  const app = express();
  const instanceId = process.env.WHATSAPP_INSTANCE_ID || 'semas_whatsapp';
  const client = createWhatsappClient({ logger, instanceId });
  const auth = createAuthMiddleware({
    internalKey: process.env.WHATSAPP_INTERNAL_KEY || '',
    logger
  });

  app.disable('x-powered-by');
  app.use(express.json({ limit: '128kb' }));
  app.use((req, res, next) => {
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('Cache-Control', 'no-store');
    next();
  });

  app.get('/health', (req, res) => {
    const status = client.status();
    res.json({
      success: true,
      service: 'semas-whatsapp-bridge',
      status: 'healthy',
      instanceId,
      clientState: status.data.state,
      uptime: Math.floor(process.uptime())
    });
  });

  app.use(auth);

  app.get('/status', (req, res) => {
    res.json(client.status());
  });

  app.post('/connect/qrcode', async (req, res) => {
    try {
      await client.ensureStarted();
      res.json(client.status());
    } catch (error) {
      logger.error('connect_qrcode_failed', { message: error.message });
      res.status(500).json({ success: false, message: 'Falha ao iniciar cliente SEMAS.' });
    }
  });

  app.get('/qrcode', (req, res) => {
    res.json(client.qrcode());
  });

  app.post('/pairing-code', async (req, res) => {
    const validation = validatePairingPayload(req.body || {});
    if (!validation.success) {
      return res.status(422).json(validation);
    }

    try {
      const result = await client.requestPairingCode(validation.data.phone);
      res.json(result);
    } catch (error) {
      logger.error('pairing_code_failed', { message: error.message });
      res.status(409).json({ success: false, message: error.message });
    }
  });

  app.post('/send-message', async (req, res) => {
    const validation = validateSendPayload(req.body || {});
    if (!validation.success) {
      return res.status(422).json(validation);
    }

    try {
      const result = await client.sendText(validation.data);
      res.status(result.success ? 200 : 409).json(result);
    } catch (error) {
      logger.error('send_message_failed', { message: error.message });
      res.status(500).json({ success: false, message: 'Falha ao enviar mensagem.' });
    }
  });

  app.post('/restart', async (req, res) => {
    try {
      const result = await client.restart();
      res.json(result);
    } catch (error) {
      logger.error('restart_failed', { message: error.message });
      res.status(500).json({ success: false, message: 'Falha ao reiniciar cliente SEMAS.' });
    }
  });

  app.post('/disconnect', async (req, res) => {
    try {
      const result = await client.disconnect(false);
      res.json(result);
    } catch (error) {
      logger.error('disconnect_failed', { message: error.message });
      res.status(500).json({ success: false, message: 'Falha ao desconectar conta SEMAS.' });
    }
  });

  app.post('/session/reset', async (req, res) => {
    try {
      const result = await client.disconnect(true);
      res.json(result);
    } catch (error) {
      logger.error('reset_session_failed', { message: error.message });
      res.status(500).json({ success: false, message: 'Falha ao apagar sessao SEMAS.' });
    }
  });

  client.ensureStarted().catch((error) => {
    logger.error('startup_restore_failed', { message: error.message });
  });

  return app;
}

module.exports = { createApp };
