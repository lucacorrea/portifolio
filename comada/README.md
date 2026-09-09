# L&J Comandas — Protótipo PHP

Protótipo navegável de layout para sistema de comandas multiestabelecimento.

## Requisitos
- PHP 8.1+ recomendado
- Apache/Nginx ou servidor embutido do PHP

## Rodar localmente

```bash
php -S localhost:8080
```

Depois acesse:

`http://localhost:8080`

No XAMPP, coloque a pasta dentro de `htdocs` e acesse pela URL local correspondente.

## Estrutura
- `includes/` componentes compartilhados
- `assets/css/app.css` design system responsivo
- `assets/js/app.js` interações do protótipo
- páginas `.php` para operação, cadastros, estoque, gestão, configurações e SaaS

## Importante
Este ZIP é o **layout/protótipo funcional**, sem banco de dados e sem autenticação real. Os dados são demonstrativos. Para produção, a próxima etapa deve implementar banco, autenticação, permissões, CSRF, validação server-side, auditoria persistente e regras transacionais de caixa/comandas.
