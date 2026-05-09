# FluxPay - Estrutura Base

Esta é uma estrutura inicial do FluxPay como SaaS multiempresa.

## O que já vem nesta estrutura

- Área do **Admin da Plataforma** para gerenciar empresas, planos, assinaturas e usuários locatários.
- Área da **Empresa Locatária** para os usuários da empresa acessarem o sistema.
- Banco de dados com `empresa_id` nas tabelas principais.
- Login com rotas separadas por contexto:
  - `platform_admin`: administrador dono do SaaS.
  - `empresa_admin`: responsável pela empresa que alugou o sistema.
  - `operador`: usuário interno da empresa locatária.
- Proteção básica de sessão.
- Proteção CSRF nos formulários.
- CSS externo em `public/assets/css/app.css` para o sistema e `public/assets/css/style.css` para a landing.
- `.env.example` para tirar credenciais do código.

## Estrutura

```text
bootstrap/
  app.php
app/
  Auth/
  Config/
  Helpers/
  Http/Actions/
  Includes/
  Middleware/
database/
  schema_saas.sql
  seed.sql
public/
  index.php
  checkout.php
  login.php
  logout.php
  admin/
  app/
  assets/css/app.css
  assets/css/style.css
  assets/js/main.js
storage/logs/
```

O `index.php` da raiz existe apenas como redirecionamento seguro para `public/index.php`.
Em produção, a configuração correta é apontar o domínio diretamente para a pasta `public/`.

## Instalação básica

1. Crie um banco MySQL.
2. Importe `database/schema_saas.sql`.
3. Importe `database/seed.sql`.
4. Copie `.env.example` para `.env`.
5. Ajuste os dados do banco no `.env`.
6. Aponte o domínio/subdomínio para a pasta `public/`.

Se a aplicação precisar rodar em subpasta, por exemplo `/Scobraca/public`, os links públicos são detectados automaticamente.
Em servidor com proxy ou regra de URL especial, defina `APP_BASE_PATH=/Scobraca/public` no `.env`.

## Logins

### Admin da Plataforma

URL: `/admin/login.php`

```text
E-mail: admin@fluxpay.com.br
Senha: Admin123@2026
```

Depois de acessar, altere a senha.

### Empresa locatária

URL: `/login.php`

## Observação importante

Esta estrutura é uma base de migração. O seu sistema atual ainda precisa ter cada módulo adaptado para sempre filtrar por `empresa_id`, por exemplo:

```php
SELECT * FROM clientes WHERE empresa_id = :empresa_id
```

Nunca use no SaaS:

```php
SELECT * FROM clientes
```

Sem filtro por empresa, uma empresa pode visualizar dados de outra.

## Landing page FluxPay

A landing pública está em `public/index.php`. O checkout demonstrativo separado está em `public/checkout.php`. Ambos usam:

```text
public/assets/css/style.css
public/assets/js/main.js
public/assets/icons/favicon.svg
public/assets/img/fluxpay-og.svg
```

### Como abrir localmente

Use um servidor PHP apontando para `public/`:

```bash
php -S localhost:8000 -t public
```

Depois acesse `http://localhost:8000`.

### Como subir na Hostinger

1. Envie o projeto para a hospedagem.
2. Aponte o domínio para a pasta `public/` quando a hospedagem permitir.
3. Se rodar em subpasta, ajuste `APP_BASE_PATH` no `.env`.
4. Ative HTTPS antes de qualquer integração real com pagamentos.

### Onde alterar

- Textos, planos, depoimentos, FAQ e seções: `public/index.php`.
- Fluxo visual de checkout, resumo do pedido e campos da compra: `public/checkout.php`.
- Visual, responsividade e animações CSS: `public/assets/css/style.css`.
- Mega menu, carrossel, FAQ, contadores, parallax leve, formulários e checkout demonstrativo: `public/assets/js/main.js`.
- Favicon e imagem de compartilhamento: `public/assets/icons/favicon.svg` e `public/assets/img/fluxpay-og.svg`.

O formulário da landing não envia dados reais nesta versão. A função `handleLeadSubmit()` em `public/assets/js/main.js` está preparada para conectar um endpoint futuro da FluxPay.
O checkout também não processa pagamentos reais. A função `initCheckoutPage()` contém o ponto marcado com TODO para conectar gateway, criação de assinatura e validações server-side.
