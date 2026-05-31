# L&J Caixa — Base PHP OOP + .env + SQL

Projeto com:

- Layout responsivo PC + Mobile;
- Páginas separadas;
- PHP orientado a objeto;
- `.env` para configuração segura;
- Autoload simples via namespace `App\`;
- Login com sessão segura;
- CSRF;
- Rate limit básico de login;
- PDO;
- SQL completo;
- Estrutura preparada para API real.

## Instalação

1. Importe `database/schema.sql`.
2. Importe `database/seed.sql`.
3. Configure o `.env` com os dados reais do banco.
4. Acesse `login.php`.

## Acesso inicial

```txt
E-mail: admin@ljsolucoestech.com.br
Senha: Admin@123
```

Troque a senha depois do primeiro acesso.

## Estrutura principal

```txt
backend/
  Core/
  Security/
  Models/
  Repositories/
  Services/
  Controllers/
  Middlewares/
api/
pages/
database/
uploads/
storage/
```

## Segurança

- Não suba `.env` para repositório público.
- Na Hostinger, mantenha `.htaccess`.
- Use HTTPS.
- Troque a senha inicial.
- Integre os endpoints API com regras reais antes de produção.
