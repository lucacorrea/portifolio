# Central WhatsApp SEMAS

Sistema isolado da SEMAS para comunicação e atualização cadastral via WhatsApp.

## Arquitetura

- PHP autenticado em `semas/whatsapp/`.
- Bridge Node.js próprio em `semas/whatsapp/bridge/`.
- Biblioteca WhatsApp: `@whiskeysockets/baileys`.
- Instância: configurada por `WHATSAPP_INSTANCE_ID`, padrão `semas_whatsapp`.
- Sessões: `bridge/storage/sessions/`.
- Logs do bridge: `bridge/logs/`.
- Logs PHP: `storage/logs/`.
- Webhook exclusivo: `webhook/whatsapp.php`.

O Tático GPS é apenas referência técnica. A Central não importa arquivos, não chama endpoints e não compartilha sessão, processo, porta, número ou logs do Tático.

## Endpoints PHP

- `api/status-conexao.php`
- `api/gerar-qrcode.php`
- `api/conectar-numero.php`
- `api/reiniciar-cliente.php`
- `api/desconectar.php`
- `api/apagar-sessao.php`
- `api/processar-fila.php`
- `webhook/whatsapp.php`

## Endpoints internos Node

- `GET /health`
- `GET /status`
- `POST /connect/qrcode`
- `GET /qrcode`
- `POST /pairing-code`
- `POST /send-message`
- `POST /restart`
- `POST /disconnect`
- `POST /session/reset`

Todos os endpoints internos, exceto `/health`, exigem `X-Internal-Key`.
