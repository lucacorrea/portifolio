module.exports = {
  apps: [
    {
      name: 'semas-whatsapp-bridge',
      cwd: __dirname,
      script: 'server.js',
      instances: 1,
      exec_mode: 'fork',
      max_memory_restart: '512M',
      autorestart: true,
      watch: false,
      env: {
        NODE_ENV: 'production',
        WHATSAPP_INSTANCE_ID: 'semas_whatsapp',
        WHATSAPP_BRIDGE_HOST: '127.0.0.1',
        WHATSAPP_BRIDGE_PORT: '3091'
      },
      out_file: './logs/pm2-out.log',
      error_file: './logs/pm2-error.log',
      merge_logs: true
    }
  ]
};
