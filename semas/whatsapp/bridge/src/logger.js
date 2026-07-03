const fs = require('fs-extra');
const path = require('path');

function createLogger(scope) {
  const baseRoot = path.resolve(__dirname, '..', '..');
  const configured = process.env.WHATSAPP_LOG_PATH || 'bridge/logs';
  const logDir = path.isAbsolute(configured)
    ? configured
    : path.resolve(baseRoot, configured);
  fs.ensureDirSync(logDir);

  function write(level, event, context = {}) {
    const line = {
      at: new Date().toISOString(),
      level,
      scope,
      event,
      context: sanitize(context)
    };

    const file = path.join(logDir, `bridge-${new Date().toISOString().slice(0, 10)}.log`);
    fs.appendFile(file, `${JSON.stringify(line)}\n`).catch(() => {});
    if (level === 'error') {
      console.error(JSON.stringify(line));
    }
  }

  return {
    info: (event, context) => write('info', event, context),
    warn: (event, context) => write('warn', event, context),
    error: (event, context) => write('error', event, context)
  };
}

function sanitize(value) {
  if (Array.isArray(value)) {
    return value.map(sanitize);
  }
  if (value && typeof value === 'object') {
    const out = {};
    for (const [key, item] of Object.entries(value)) {
      const lower = key.toLowerCase();
      if (lower.includes('key') || lower.includes('token') || lower.includes('secret') || lower.includes('password') || lower.includes('qr')) {
        out[key] = '[removed]';
      } else {
        out[key] = sanitize(item);
      }
    }
    return out;
  }
  if (typeof value === 'string' && value.length > 120) {
    return `${value.slice(0, 120)}...`;
  }
  return value;
}

module.exports = { createLogger, sanitize };
