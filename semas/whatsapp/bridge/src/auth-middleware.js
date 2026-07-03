const crypto = require('crypto');

function createAuthMiddleware({ internalKey, logger }) {
  const attempts = new Map();
  const windowMs = 60 * 1000;
  const maxAttempts = 120;

  return function authMiddleware(req, res, next) {
    const now = Date.now();
    const ip = req.ip || req.socket.remoteAddress || 'unknown';
    const current = attempts.get(ip) || { count: 0, start: now };
    if (now - current.start > windowMs) {
      current.count = 0;
      current.start = now;
    }
    current.count += 1;
    attempts.set(ip, current);

    if (current.count > maxAttempts) {
      logger.warn('rate_limited', { ip });
      return res.status(429).json({ success: false, message: 'Muitas requisicoes.' });
    }

    if (!internalKey || internalKey.length < 32) {
      return res.status(503).json({ success: false, message: 'Chave interna nao configurada.' });
    }

    const received = String(req.headers['x-internal-key'] || '');
    if (!safeEquals(received, internalKey)) {
      logger.warn('auth_denied', { ip, path: req.path });
      return res.status(401).json({ success: false, message: 'Nao autorizado.' });
    }

    next();
  };
}

function safeEquals(a, b) {
  const left = Buffer.from(String(a));
  const right = Buffer.from(String(b));
  if (left.length !== right.length) {
    return false;
  }
  return crypto.timingSafeEqual(left, right);
}

module.exports = { createAuthMiddleware };
