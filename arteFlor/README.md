# Arte&Flor — MVP Front-end Premium

Projeto demonstrativo em PHP, HTML, CSS e JavaScript puro para catálogo de floricultura, carrinho, checkout, área do cliente, painel administrativo e frente de caixa/PDV.

## Importante

Este pacote começou como front-end/MVP visual e agora possui estrutura inicial de banco e conexão PDO segura:

- sem pagamento real;
- sem API real;
- banco MySQL/MariaDB já modelado em `database/schema.sql`;
- conexão obrigatória configurada por `.env`;
- pedidos e vendas são simulados com `localStorage`;
- produtos, categorias, tags, imagens e estoque do catálogo público vêm do banco;
- imagens podem ser enviadas pelo admin ou vir de URL externa.

O carrinho, checkout demonstrativo, área do cliente e PDV visual ainda usam `localStorage`/dados de demonstração onde indicado. A vitrine pública de produtos não usa mais `assets/data/produtos.json` como fonte principal.

## Banco de dados e .env

1. Com o banco já criado na hospedagem, importe as tabelas:

```bash
mysql -u usuario_do_banco -p nome_do_banco < database/schema.sql
```

2. Crie o arquivo `.env` na raiz do projeto hospedado, usando `.env.example` como base:

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

3. Garanta que a extensão `pdo_mysql` esteja ativa no PHP da hospedagem.

O sistema abre a conexão com o banco no bootstrap. Em produção, falhas retornam uma página 503 genérica e os detalhes ficam apenas no log do servidor.

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
- Checkout com Pix demonstrativo;
- Pedido finalizado dentro do sistema visual;
- Área do cliente com pedidos fictícios/localStorage.

## Fluxo administrativo

- Login demonstrativo;
- Dashboard premium;
- Produtos e cadastro separados;
- Categorias e cadastro separados;
- Frente de caixa/PDV;
- Pedidos;
- Estoque;
- Relatórios;
- Integrações;
- Cupons;
- Clientes.

## Ajustes realizados nesta versão

- Ações reais do admin de produtos: imagens, status, duplicação, estoque e tags;
- Home, catálogo e detalhe do produto conectados ao banco;
- Adicionada camada `assets/css/premium-final.css` para acabamento visual premium;
- Header público e admin carregam a camada premium;
- Cards de produto renderizam `<img>` corretamente;
- Botão principal de compra adiciona ao carrinho, não finaliza no WhatsApp;
- Checkout e PDV finalizam dentro do sistema visual;
- Corrigido bug visual do thumb do carrinho;
- Todas as páginas PHP foram testadas com `php -l`;
- Todas as páginas principais responderam HTTP 200 em servidor PHP local.
