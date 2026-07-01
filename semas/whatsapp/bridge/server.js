const { createApp } = require('./src/app');
const { createLogger } = require('./src/logger');

const logger = createLogger('server');
const app = createApp({ logger });

const port = Number(process.env.WHATSAPP_BRIDGE_PORT || 3091);
const host = process.env.WHATSAPP_BRIDGE_HOST || '127.0.0.1';

const server = app.listen(port, host, () => {
  logger.info('bridge_started', {
    service: 'semas-whatsapp-bridge',
    host,
    port,
    pid: process.pid
  });
});

async function shutdown(signal) {
  logger.info('bridge_shutdown', { signal });
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 10000).unref();
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
process.on('uncaughtException', (error) => {
  logger.error('uncaught_exception', { message: error.message });
  process.exit(1);
});
process.on('unhandledRejection', (error) => {
  logger.error('unhandled_rejection', { message: error instanceof Error ? error.message : String(error) });
});
