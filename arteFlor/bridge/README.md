# Bridge WhatsApp ArteFlor

Bridge Node.js para conectar o WhatsApp da Arte&Flor via Baileys, no mesmo modelo usado no `tatico_gps`, mas com proteção por API key.

## Instalação

```bash
cd arteFlor/bridge
npm install
cp .env.example .env
```

Edite `.env` e configure:

```env
NODE_ENV=production
PORT=8080
BRIDGE_API_KEY=uma-chave-forte-gerada-com-openssl-rand-hex-32
```

Inicie:

```bash
npm start
```

Em produção, rode com PM2, systemd ou gerenciador equivalente:

```bash
npm install -g pm2
pm2 start index.js --name arteflor-whatsapp-bridge
pm2 save
```

## Endpoints

Todos os endpoints operacionais exigem a chave em `X-API-Key` ou `Authorization: Bearer`.

- `GET /health`: saúde do serviço, sem API key.
- `GET /status`: status da conexão.
- `GET /qrcode`: retorna o QR em base64 quando disponível.
- `POST /send-message`: envia mensagem.
- `POST /logout`: remove a sessão e força novo QR.

## Configuração no ArteFlor

No admin:

1. Acesse `admin/integracoes.php`.
2. Informe a URL pública do bridge, por exemplo `https://whatsapp.seudominio.com`.
3. Informe a mesma `BRIDGE_API_KEY`.
4. Salve.
5. Clique em `Gerar QR de conexão`.
6. Escaneie com WhatsApp > Dispositivos conectados.

## Segurança

Não exponha o bridge sem HTTPS e sem API key. O endpoint `/send-message` envia mensagens reais pelo WhatsApp conectado.
