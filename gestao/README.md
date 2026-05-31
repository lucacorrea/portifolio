# L&J Caixa Premium — Site com Login PHP + MySQL

Esta versão inclui:

- Layout responsivo PC + Mobile;
- Páginas separadas;
- Login com PHP/PDO;
- Sessão segura;
- CSRF no login;
- Rate limit básico de tentativas;
- Auditoria de login;
- SQL completo do sistema;
- Logout;
- Páginas protegidas por autenticação.

## Estrutura

```txt
login.php
logout.php
index.php
pages/
  nova-venda.php
  produtos.php
  produto-form.php
  relatorios.php
  clientes.php
  cliente-detalhes.php
  historico-vendas.php
  venda-detalhes.php
  comprovante.php
  configuracoes.php

backend/
  config/database.php
  core/db.php
  security/auth.php
  security/csrf.php
  security/session.php

database/
  schema.sql

assets/
  css/styles.css
  js/data.js
  js/app.js
```

## Instalação na Hostinger

1. Crie um banco MySQL no painel da Hostinger.
2. Importe `database/schema.sql` pelo phpMyAdmin.
3. Edite `backend/config/database.php` com:
   - nome do banco;
   - usuário;
   - senha;
   - host.
4. Envie os arquivos para `public_html` ou para uma subpasta.
5. Acesse `login.php`.

## Acesso inicial

```txt
E-mail: admin@ljsolucoestech.com.br
Senha: Admin@123
```

Troque a senha depois do primeiro acesso.

## Segurança

Para produção, recomendo como próximos passos:

- tela para alterar senha;
- recuperação de senha por e-mail;
- permissões por nível de usuário;
- guardar logs de ações críticas;
- mover uploads para pasta validada;
- integrar os dados mockados do JS com endpoints PHP reais.
