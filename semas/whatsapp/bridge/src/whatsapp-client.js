const fs = require('fs-extra');
const pino = require('pino');
const qrcode = require('qrcode');
const { Boom } = require('@hapi/boom');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const { createSessionManager } = require('./session-manager');
const { createMessageHandler } = require('./message-handler');
const { maskPhone } = require('./validators');

function createWhatsappClient({ logger, instanceId }) {
  const manager = createSessionManager(instanceId);
  const messageHandler = createMessageHandler({ logger, instanceId, manager });
  let sock = null;
  let starting = null;
  let state = 'not_initialized';
  let connected = false;
  let phone = null;
  let accountName = null;
  let lastChangeAt = new Date().toISOString();
  let qr = null;
  let qrExpiresAt = null;
  let reconnectAttempts = 0;

  function setState(nextState, extra = {}) {
    state = nextState;
    lastChangeAt = new Date().toISOString();
    logger.info('state_changed', { state, ...extra });
  }

  async function ensureStarted() {
    if (sock && ['connected', 'qr_available', 'waiting_qr', 'authenticating', 'restoring_session', 'reconnecting'].includes(state)) {
      return;
    }
    if (starting) {
      return starting;
    }

    starting = manager.withLock(async () => {
      setState(sock ? 'reconnecting' : 'starting');
      const { state: authState, saveCreds } = await useMultiFileAuthState(manager.sessionDir);
      const { version } = await fetchLatestBaileysVersion();
      setState('restoring_session');

      sock = makeWASocket({
        version,
        printQRInTerminal: false,
        auth: {
          creds: authState.creds,
          keys: makeCacheableSignalKeyStore(authState.keys, pino({ level: 'silent' }))
        },
        logger: pino({ level: 'silent' }),
        browser: ['SEMAS Coari', 'Chrome', '1.0.0']
      });

      sock.ev.on('creds.update', saveCreds);
      sock.ev.on('connection.update', async (update) => {
        await onConnectionUpdate(update);
      });
      sock.ev.on('messages.upsert', async (event) => {
        if (event.type !== 'notify') {
          return;
        }
        for (const message of event.messages || []) {
          await messageHandler.handleIncoming(sock, message);
        }
      });
    }).finally(() => {
      starting = null;
    });

    return starting;
  }

  async function onConnectionUpdate(update) {
    const { connection, lastDisconnect, qr: rawQr } = update;
    if (rawQr) {
      qr = await qrcode.toDataURL(rawQr);
      qrExpiresAt = Date.now() + 60000;
      connected = false;
      phone = null;
      setState('qr_available');
    }

    if (connection === 'connecting') {
      setState('authenticating');
    }

    if (connection === 'open') {
      connected = true;
      reconnectAttempts = 0;
      qr = null;
      qrExpiresAt = null;
      phone = sock?.user?.id?.split(':')[0] || null;
      accountName = sock?.user?.name || 'Conta SEMAS';
      setState('connected');
    }

    if (connection === 'close') {
      connected = false;
      phone = null;
      qr = null;
      qrExpiresAt = null;
      const code = new Boom(lastDisconnect?.error)?.output?.statusCode;
      const loggedOut = code === DisconnectReason.loggedOut;
      setState(loggedOut ? 'auth_failed' : 'disconnected', { code });
      sock = null;

      if (!loggedOut && reconnectAttempts < 5) {
        reconnectAttempts += 1;
        const delay = Math.min(30000, 2000 * reconnectAttempts);
        setState('reconnecting', { attempt: reconnectAttempts, delay });
        setTimeout(() => ensureStarted().catch((error) => {
          logger.error('reconnect_failed', { message: error.message });
          setState('error');
        }), delay).unref();
      }
    }
  }

  function status() {
    return {
      success: true,
      data: {
        instanceId,
        state,
        connected,
        phoneMasked: maskPhone(phone),
        accountName: connected ? accountName : null,
        lastChangeAt,
        qrExpiresAt: qrExpiresAt ? new Date(qrExpiresAt).toISOString() : null
      }
    };
  }

  function qrcodePayload() {
    const expired = qrExpiresAt && Date.now() > qrExpiresAt;
    if (expired) {
      qr = null;
      qrExpiresAt = null;
      if (!connected) {
        setState('waiting_qr');
      }
    }

    return {
      success: true,
      data: {
        qr,
        expiresAt: qrExpiresAt ? new Date(qrExpiresAt).toISOString() : null,
        state
      }
    };
  }

  async function requestPairingCode(phoneNumber) {
    await ensureStarted();
    if (!sock || connected) {
      throw new Error('Cliente conectado ou indisponivel para pairing code.');
    }
    if (typeof sock.requestPairingCode !== 'function') {
      throw new Error('Biblioteca atual nao suporta pairing code nesta instancia.');
    }

    setState('waiting_pairing_code');
    const code = await sock.requestPairingCode(phoneNumber);
    return {
      success: true,
      data: {
        pairingCode: code,
        expiresAt: new Date(Date.now() + 60000).toISOString(),
        state
      }
    };
  }

  async function sendText(payload) {
    if (!connected || !sock) {
      return { success: false, message: 'WhatsApp SEMAS nao esta conectado.' };
    }
    return messageHandler.sendText(sock, payload);
  }

  async function restart() {
    setState('stopping');
    if (sock) {
      try {
        sock.end?.();
      } catch {
        // Socket best-effort shutdown.
      }
    }
    sock = null;
    connected = false;
    await ensureStarted();
    return status();
  }

  async function disconnect(clearSession) {
    if (instanceId.toLowerCase().includes('tatico')) {
      throw new Error('Instancia invalida para operacao SEMAS.');
    }
    setState('stopping');
    if (sock) {
      try {
        await sock.logout();
      } catch (error) {
        logger.warn('logout_failed', { message: error.message });
      }
      sock = null;
    }
    connected = false;
    phone = null;
    qr = null;
    qrExpiresAt = null;

    if (clearSession) {
      await manager.withLock(async () => {
        await manager.clearSession();
      });
    }

    setState('disconnected');
    return status();
  }

  return {
    ensureStarted,
    status,
    qrcode: qrcodePayload,
    requestPairingCode,
    sendText,
    restart,
    disconnect
  };
}

module.exports = { createWhatsappClient };
