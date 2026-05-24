# Arte&Flor — Sistema PHP Premium

Projeto em PHP puro, MySQL/PDO, HTML, CSS e JavaScript para catálogo de floricultura, carrinho, checkout, pedidos, área do cliente, painel administrativo e frente de caixa/PDV.

## Importante

Este pacote começou como front-end/MVP visual e agora possui fluxo principal conectado ao banco:

- sem pagamento real;
- Pix manual, sem gateway e sem webhook;
- WhatsApp opcional apenas como notificação pós-compra;
- banco MySQL/MariaDB já modelado em `database/schema.sql`;
- conexão obrigatória configurada por `.env`;
- checkout público salva pedidos, itens, pagamentos manuais, histórico e baixa de estoque no banco;
- área do cliente consulta pedido oficial pelo código;
- admin de pedidos usa dados reais do banco;
- produtos, categorias, tags, imagens e estoque do catálogo público vêm do banco;
- imagens podem ser enviadas pelo admin ou vir de URL externa.

O carrinho público continua em `localStorage` apenas como experiência de navegação. No checkout, preço, total, estoque e status são recalculados no backend antes de gravar o pedido.

## Banco de dados e .env

1. Com o banco já criado na hospedagem, importe as tabelas:

```bash
mysql -u usuario_do_banco -p nome_do_banco < database/schema.sql
```

2. Aplique as migrations incrementais quando existirem:

```bash
mysql -u usuario_do_banco -p nome_do_banco < database/migrations/20260524_create_whatsapp_notificacoes.sql
```

3. Crie o arquivo `.env` na raiz do projeto hospedado, usando `.env.example` como base:

```txt
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_SOCKET=
DB_DATABASE=arteflor
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco
DB_CHARSET=utf8mb4
DB_TIMEOUT=5
DB_ALLOW_EMPTY_PASSWORD=false
```

4. Garanta que a extensão `pdo_mysql` esteja ativa no PHP da hospedagem.

O sistema abre a conexão com o banco no bootstrap. Em produção, falhas retornam uma página 503 genérica e os detalhes ficam apenas no log do servidor.

## WhatsApp pós-compra

O WhatsApp não finaliza venda. O pedido é salvo primeiro no banco; depois o sistema tenta registrar/enviar uma notificação ao cliente.

Configuração recomendada:

1. Copie `config.local.example.php` para `config.local.php`.
2. Preencha apenas no servidor, nunca no GitHub:

```php
const WHATSAPP_CLOUD_API_TOKEN = '';
const WHATSAPP_PHONE_NUMBER_ID = '';
const WHATSAPP_API_VERSION = 'v21.0';
```

3. No admin, acesse `/admin/integracoes.php`.
4. Use `simulação/log` para testar sem token.
5. Para envio real, configure modo `Cloud API`, Phone Number ID e token no `config.local.php` ou no campo secreto do admin.

A mensagem pós-compra é editável e aceita variáveis como `{{codigo}}`, `{{cliente}}`, `{{total}}`, `{{itens}}` e `{{link_pedido}}`.

## Upload de produtos

O cadastro administrativo de produtos salva os dados no banco e aceita múltiplas imagens por produto.

- pasta pública de upload: `assets/uploads/produtos`;
- formatos aceitos: JPG, PNG, WEBP, GIF e AVIF;
- limite atual: 8 imagens por envio, até 5 MB por imagem;
- SVG e arquivos executáveis não são aceitos por segurança;
- a hospedagem precisa permitir escrita nessa pasta.

## Usuário de suporte

Para criar ou atualizar o usuário de suporte no banco hospedado:

```bash
SUPPORT_PASSWORD="<senha_temporaria>" php database/create-support-user.php
```

Por padrão, o script cria `suporte@arteflor.demo` com perfil `operador`. Para alterar:

```bash
SUPPORT_EMAIL=suporte@seudominio.com SUPPORT_NAME=Suporte SUPPORT_PROFILE=operador SUPPORT_PASSWORD="<senha_temporaria>" php database/create-support-user.php
```

Para resetar o usuário de suporte existente para senha temporária `123`:

```bash
SUPPORT_EMAIL=suportelucacorrea@gmail.com SUPPORT_NAME=suporte SUPPORT_PROFILE=admin SUPPORT_PASSWORD=123 php database/create-support-user.php
```

Use uma senha temporária apenas para o primeiro acesso e troque assim que possível.

Depois de criado, acesse `/admin/login.php` com o e-mail ou nome cadastrado. As páginas em `/admin/` exigem sessão ativa e redirecionam automaticamente para o login quando a sessão expira.
O login também registra tentativas e aplica bloqueio temporário após muitas falhas para o mesmo e-mail e IP.

## Publicação na hospedagem

Envie a pasta `arteFlor` completa para a hospedagem e abra:

```txt
/arteFlor/index.php
/arteFlor/catalogo.php
/arteFlor/admin/login.php
```

O projeto usa `base_url()` e `asset()` para carregar CSS, JS e links internos corretamente dentro da pasta `/arteFlor/`.

## Fluxo público

- Catálogo com produtos, imagens, tags e estoque vindos do banco;
- Detalhes do produto com galeria e relacionados reais;
- Carrinho com localStorage;
- Checkout salvando pedido real no banco;
- Pix manual/demonstrativo, com confirmação pelo admin;
- Baixa de estoque ao criar pedido;
- Área do cliente consultando pedido real por código.

## Fluxo administrativo

- Login demonstrativo;
- Dashboard premium;
- Produtos e cadastro separados;
- Categorias e cadastro separados;
- Frente de caixa/PDV;
- Pedidos reais com filtros, KPIs, detalhes, status, pagamento manual e reenvio WhatsApp;
- Estoque;
- Relatórios;
- Integrações com Pix manual e WhatsApp pós-compra;
- Cupons;
- Clientes.

## Ajustes realizados nesta versão

- Ações reais do admin de produtos: imagens, status, duplicação, estoque e tags;
- Home, catálogo e detalhe do produto conectados ao banco;
- Checkout público conectado ao banco com transação, validação e baixa de estoque;
- Área do cliente conectada ao banco por código do pedido;
- Admin de pedidos conectado ao banco com status, pagamento manual e WhatsApp;
- Integrações com mensagem WhatsApp editável, modo simulação e histórico;
- Adicionada camada `assets/css/premium-final.css` para acabamento visual premium;
- Header público e admin carregam a camada premium;
- Cards de produto renderizam `<img>` corretamente;
- Botão principal de compra adiciona ao carrinho, não finaliza no WhatsApp;
- Pix permanece manual/demonstrativo, sem API real;
- Corrigido bug visual do thumb do carrinho;
- Valide sintaxe com `php -l` nos arquivos alterados antes do deploy.
