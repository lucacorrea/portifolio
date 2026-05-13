# Bridge WhatsApp

Bridge Node.js baseada em Baileys para gerar QR Code e enviar mensagens pelo WhatsApp.

## Endpoints

```text
GET  /status
GET  /qrcode
POST /send-message
GET  /logout
```

Quando `WHATSAPP_BRIDGE_TOKEN` estiver definido, envie:

```text
Authorization: Bearer SEU_TOKEN
```

## Desenvolvimento

```bash
npm install
set WHATSAPP_BRIDGE_TOKEN=um-token-seguro
npm start
```

No `.env` do sistema PHP:

```env
WHATSAPP_PROVIDER=bridge
WHATSAPP_BRIDGE_URL=http://localhost:8080
WHATSAPP_BRIDGE_TOKEN=um-token-seguro
```

Em produção, rode a bridge como processo persistente e use HTTPS.
