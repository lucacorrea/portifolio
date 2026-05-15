# Igreja Tefe - Sistema Financeiro

Este repositório usará como base o plano de implementação do MVP salvo em:

- `docs/plano-implementacao-mvp-sistema-financeiro-igrejas.md`
- `docs/plano-implementacao-mvp-sistema-financeiro-igrejas-revisado.pdf`

O escopo inicial é um sistema financeiro web multi-igreja, com PHP, MySQL, MVC leve,
autenticação por sessão segura, isolamento obrigatório por `igreja_id`, auditoria mínima,
dashboard financeiro e relatórios em PDF/Excel.

Antes de implementar novas funcionalidades, consulte o plano em `docs/`.

## Estrutura inicial

O projeto usa uma arquitetura MVC leve:

- `app/Controllers`: entrada das requisições.
- `app/Models`: entidades e acesso ao banco.
- `app/Core`: infraestrutura da aplicação.
- `app/Middleware`: autenticação, CSRF, papéis e isolamento por igreja.
- `app/Services`: regras de negócio, auditoria e exportações.
- `views`: telas renderizadas no servidor.
- `public`: ponto de entrada web e assets.
- `database`: migrations, seeds e scripts SQL.
- `storage`: logs e arquivos gerados.

## Rodando localmente

1. Copie `.env.example` para `.env` e ajuste as variáveis locais.
2. Instale o autoload quando o Composer estiver disponível:

```bash
composer dump-autoload
```

3. Suba o servidor local:

```bash
composer serve
```

ou:

```bash
php -S localhost:8000 -t public
```
