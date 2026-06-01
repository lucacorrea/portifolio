# L&J Caixa - Sistema PHP/MySQL

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
- APIs reais com autenticação por sessão.

## Instalação

1. Importe `database/schema.sql`.
2. Configure o `.env` existente com os dados reais do banco.
3. Crie a empresa e o usuário administrador reais conforme o processo de implantação.
4. Acesse `login.php`.

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
- Mantenha `APP_DEBUG=false` em produção.
