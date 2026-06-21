# K. Yamaguchi Refrigeração

Layout visual em PHP, Bootstrap 5 e Bootstrap Icons para gestão de serviços de refrigeração.

## Como executar

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000/dashboard.php`.

## Escopo desta etapa

- Somente interface visual.
- Dados fictícios fixos para demonstração do layout.
- Sem banco de dados, APIs, autenticação, uploads, integrações, cálculos reais, PDF real, WhatsApp, localStorage ou persistência.
- JavaScript limitado a sidebar mobile, tooltips, tabs, dropdowns e modais Bootstrap.

## Páginas

- `dashboard.php`
- `ordens-servico.php`
- `orcamentos.php`
- `clientes.php`
- `agenda.php`
- `pecas.php`
- `servicos.php`
- `funcionarios.php`
- `caixa.php`
- `faturamento.php`
- `relatorios.php`
- `configuracoes.php`

## Estrutura

- `includes/menu.php`: menu lateral e identidade K. Yamaguchi.
- `includes/topbar.php`: breadcrumb, título, descrição e ação principal visual.
- `includes/shell.php`: estrutura base compartilhada.
- `includes/ui.php`: componentes e layouts visuais reutilizáveis.
- `pages/dashboard.php`: renderização do dashboard.
- `pages/operational.php`: renderização das páginas internas.
- `assets/css/dashboard.css`: design system e responsividade.
- `assets/js/osmais-app.js`: interações visuais mínimas.
