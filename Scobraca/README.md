# Tático GPS SaaS - Estrutura Base

Esta é uma estrutura inicial para transformar o Tático GPS em um SaaS multiempresa.

## O que já vem nesta estrutura

- Área do **Admin da Plataforma** para gerenciar empresas, planos, assinaturas e usuários locatários.
- Área da **Empresa Locatária** para os usuários da empresa acessarem o sistema.
- Banco de dados com `empresa_id` nas tabelas principais.
- Login com separação de tipo de usuário:
  - `platform_admin`: administrador dono do SaaS.
  - `empresa_admin`: responsável pela empresa que alugou o sistema.
  - `operador`: usuário interno da empresa locatária.
- Proteção básica de sessão.
- Proteção CSRF nos formulários.
- CSS externo em `public/assets/css/app.css`.
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
  login.php
  logout.php
  admin/
  app/
  assets/css/app.css
storage/logs/
```

## Instalação básica

1. Crie um banco MySQL.
2. Importe `database/schema_saas.sql`.
3. Importe `database/seed.sql`.
4. Copie `.env.example` para `.env`.
5. Ajuste os dados do banco no `.env`.
6. Aponte o domínio/subdomínio para a pasta `public/`.

## Login inicial do Admin da Plataforma

```text
E-mail: admin@taticogps.com.br
Senha: Admin123@2026
```

Depois de acessar, altere a senha.

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
