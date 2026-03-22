# SaaS Contábil ERP

Estrutura inicial completa em PHP, sem banco de dados, focada em escritório contábil.

## Acesso de teste
- URL base: /saas
- Login: /saas/login
- Dashboard: /saas/dashboard

## Usuário de teste
- E-mail: admin@saas.com
- Senha: 123456

## Estrutura
- `index.php` como porta de entrada
- `bootstrap/` para inicialização
- `app/Modules/Auth` para login
- `app/Modules/Dashboard` para painel contábil
- `resources/views` para layouts e páginas
- `public/assets` para CSS e JS

## Observação
O helper `url()` já está preparado para o sistema rodando dentro da pasta `/saas`.
