# K.Yamaguchi Service — Layout Premium PHP/JS/AJAX

Protótipo visual em PHP puro com JavaScript e AJAX simulado para um sistema de gestão de refrigeração.

## Telas incluídas

- `dashboard.php` — Dashboard executivo
- `tabelas.php` — Página padrão de listagens/tabelas
- `relatorios.php` — Página de relatórios
- `api/*.php` — Endpoints mockados em JSON para alimentar as telas via AJAX

## Como rodar localmente

Com PHP instalado:

```bash
php -S localhost:8000
```

Depois acesse:

```txt
http://localhost:8000
```

## Estrutura

```txt
/assets/css/base.css
/assets/css/dashboard.css
/assets/css/tables.css
/assets/css/reports.css

/assets/js/app.js
/assets/js/dashboard.js
/assets/js/tabelas.js
/assets/js/relatorios.js

/includes/header.php
/includes/sidebar.php
/includes/topbar.php
/includes/footer.php

/api/dashboard.php
/api/listagem.php
/api/relatorios.php
```

## Observação técnica

Esse pacote é layout/protótipo. Os endpoints `api/*.php` usam dados simulados. Na implementação real, substitua os arrays PHP por consultas ao banco com validação, autenticação, permissões e tratamento de erro.
