# OSmais

Estrutura inicial do sistema baseada no dashboard de demonstração.

## Como executar

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000/dashboard.php`.

## Estrutura

- `dashboard.php`: tela principal da dashboard.
- `ordens-servico.php`: listagem e gestão visual de ordens de serviço.
- `clientes.php`: cadastro visual, filtros e histórico de clientes.
- `tecnicos.php`: gestão visual de técnicos e disponibilidade.
- `agenda.php`: agenda operacional em lista/cards.
- `pecas.php`: controle visual de peças e estoque.
- `servicos.php`: catálogo visual de serviços.
- `orcamentos.php`: criação visual de orçamentos, PDF front-end e WhatsApp.
- `faturamento.php`: controle visual de notas/faturamento.
- `relatorios.php`: indicadores e relatórios simples.
- `configuracoes.php`: configurações visuais do sistema.
- `includes/menu.php`: menu lateral do sistema.
- `includes/topbar.php`: barra superior da tela.
- `includes/shell.php`: estrutura base compartilhada entre as telas.
- `includes/modal-nova-os.php`: modal de criação de OS e container de toast.
- `pages/dashboard.php`: conteúdo da tela de dashboard.
- `pages/operational.php`: template compartilhado das telas operacionais.
- `assets/css/dashboard.css`: estilos da dashboard.
- `assets/js/dashboard.js`: comportamento e dados de demonstração da dashboard.
- `assets/js/osmais-app.js`: dados mockados e interações das telas operacionais.
