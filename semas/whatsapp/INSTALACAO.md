# Instalação da Central WhatsApp SEMAS

## 1. Configuração

Crie `semas/whatsapp/.env` a partir de `.env.example` e defina valores próprios para:

- `WHATSAPP_INTERNAL_KEY`
- `WHATSAPP_WEBHOOK_SECRET`
- `WHATSAPP_BRIDGE_HOST`
- `WHATSAPP_BRIDGE_PORT`
- `WHATSAPP_WEBHOOK_URL`

Não publique `.env`.

## 2. Dependências Node

```bash
cd semas/whatsapp/bridge
npm install --omit=dev
```

## 3. Banco

A migration fica em:

```text
semas/whatsapp/database/migrations/20260622_whatsapp_emprego_central.sql
```

A aplicação também executa a migration de forma idempotente ao usar os serviços da Central.

## 4. Inicialização

Com PM2:

```bash
cd semas/whatsapp/bridge
pm2 start ecosystem.config.js
pm2 save
```

Sem PM2:

```bash
cd semas/whatsapp/bridge
npm start
```

## 5. Cron

Exemplos:

```bash
php semas/whatsapp/cron/processar-fila.php 10
php semas/whatsapp/cron/verificar-status.php
php semas/whatsapp/cron/limpar-logs.php 30
```

## 6. Recuperação

- Reiniciar cliente: tela `Conexão`.
- Desconectar conta SEMAS: tela `Conexão`.
- Apagar sessão SEMAS: tela `Conexão`, confirmação `APAGAR SESSAO SEMAS`.
- Backup de sessão: copie `bridge/storage/sessions/` com o processo parado.

## 7. Checklist de produção

- PHP com `curl` ou acesso HTTP local habilitado.
- Node.js instalado.
- Porta interna do bridge liberada somente para localhost.
- `WHATSAPP_INTERNAL_KEY` e `WHATSAPP_WEBHOOK_SECRET` com valores longos.
- Diretórios `bridge/storage/`, `bridge/logs/` e `storage/logs/` graváveis.
- Apache bloqueando diretórios internos via `.htaccess`.
