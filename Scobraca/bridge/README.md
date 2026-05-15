# Bridge WhatsApp

Bridge Node.js baseada em Baileys para gerar QR Code, enviar mensagens e encaminhar comprovantes recebidos pelo WhatsApp.

## Endpoints

```text
GET  /status?instance=fluxpay-empresa-1
GET  /qrcode?instance=fluxpay-empresa-1
POST /send-message
POST /logout?instance=fluxpay-empresa-1
```

Os endpoints continuam aceitando chamadas sem `instance`, mas em SaaS use sempre a instância da empresa. O corpo de `/send-message` aceita `instanceName`, `number` e `text`.

Quando `WHATSAPP_BRIDGE_TOKEN` estiver definido, envie:

```text
Authorization: Bearer SEU_TOKEN
```

## Desenvolvimento

```bash
npm install
npm start
```

No `.env` do sistema PHP:

```env
WHATSAPP_PROVIDER=bridge
WHATSAPP_BRIDGE_URL=http://localhost:8080
WHATSAPP_BRIDGE_TOKEN=um-token-seguro
WHATSAPP_WEBHOOK_URL=http://localhost:8000/webhooks/whatsapp_comprovante.php
WHATSAPP_WEBHOOK_TOKEN=um-token-seguro
```

Ao iniciar, a bridge carrega automaticamente o `.env` da raiz do projeto e, se existir, um `.env` dentro da própria pasta `bridge`.

Se `WHATSAPP_WEBHOOK_URL` estiver configurado, imagens e PDFs recebidos em conversas privadas serão enviados ao webhook com `Authorization: Bearer WHATSAPP_WEBHOOK_TOKEN`. Se `WHATSAPP_WEBHOOK_TOKEN` ficar vazio, a bridge usa `WHATSAPP_BRIDGE_TOKEN` como fallback.

Em produção, rode a bridge como processo persistente e use HTTPS.
