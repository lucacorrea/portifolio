const fs = require('fs-extra');
const path = require('path');

function createSessionManager(instanceId) {
  const baseRoot = path.resolve(__dirname, '..', '..');
  const baseStorage = path.resolve(__dirname, '..', 'storage');
  const configuredSessionPath = process.env.WHATSAPP_SESSION_PATH || 'bridge/storage/sessions';
  const sessionRoot = path.isAbsolute(configuredSessionPath)
    ? configuredSessionPath
    : path.resolve(baseRoot, configuredSessionPath);
  const sessionDir = path.join(sessionRoot, safeName(instanceId));
  const lockDir = path.join(baseStorage, 'locks');
  const lockFile = path.join(lockDir, `${safeName(instanceId)}.lock`);
  const cacheDir = path.join(baseStorage, 'cache');

  fs.ensureDirSync(sessionDir);
  fs.ensureDirSync(lockDir);
  fs.ensureDirSync(cacheDir);

  async function withLock(action) {
    await acquireLock();
    try {
      return await action();
    } finally {
      await fs.remove(lockFile).catch(() => {});
    }
  }

  async function acquireLock() {
    await fs.ensureDir(lockDir);
    try {
      await fs.writeFile(lockFile, String(process.pid), { flag: 'wx' });
    } catch (error) {
      throw new Error('Outra operacao da instancia SEMAS esta em andamento.');
    }
  }

  async function clearSession() {
    const resolved = path.resolve(sessionDir);
    if (!resolved.startsWith(path.resolve(sessionRoot))) {
      throw new Error('Caminho de sessao invalido.');
    }
    await fs.emptyDir(sessionDir);
  }

  function cacheFile(name) {
    return path.join(cacheDir, safeName(name));
  }

  return { sessionDir, cacheDir, lockFile, withLock, clearSession, cacheFile };
}

function safeName(value) {
  return String(value).replace(/[^a-zA-Z0-9_.-]/g, '_');
}

module.exports = { createSessionManager };
