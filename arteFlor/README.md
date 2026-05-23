# Arte&Flor — MVP Front-end Premium

Projeto demonstrativo em PHP, HTML, CSS e JavaScript puro para catálogo de floricultura, carrinho, checkout, área do cliente, painel administrativo e frente de caixa/PDV.

## Importante

Este pacote começou como front-end/MVP visual e agora possui estrutura inicial de banco e conexão PDO segura:

- sem backend real;
- sem autenticação real;
- sem pagamento real;
- sem API real;
- banco MySQL/MariaDB já modelado em `database/schema.sql`;
- conexão obrigatória configurada por `.env`;
- pedidos e vendas são simulados com `localStorage`;
- imagens são externas e ilustrativas.

As telas públicas e administrativas ainda usam `assets/data/produtos.json` e `localStorage` até a próxima etapa de persistência real.

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

## Usuário de suporte

Para criar ou atualizar o usuário de suporte no banco hospedado:

```bash
SUPPORT_PASSWORD="<senha_temporaria>" php database/create-support-user.php
```

Por padrão, o script cria `suporte@arteflor.demo` com perfil `operador`. Para alterar:

```bash
SUPPORT_EMAIL=suporte@seudominio.com SUPPORT_NAME=Suporte SUPPORT_PROFILE=operador SUPPORT_PASSWORD="<senha_temporaria>" php database/create-support-user.php
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

- Catálogo com imagens reais ilustrativas;
- Detalhes do produto com galeria;
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

- Adicionada camada `assets/css/premium-final.css` para acabamento visual premium;
- Header público e admin carregam a camada premium;
- Imagens do catálogo usam URLs externas reais;
- Cards de produto renderizam `<img>` corretamente;
- Botão principal de compra adiciona ao carrinho, não finaliza no WhatsApp;
- Checkout e PDV finalizam dentro do sistema visual;
- Corrigido bug visual do thumb do carrinho;
- Todas as páginas PHP foram testadas com `php -l`;
- Todas as páginas principais responderam HTTP 200 em servidor PHP local.
