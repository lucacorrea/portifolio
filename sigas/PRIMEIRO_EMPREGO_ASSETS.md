# Assets oficiais — Meu Primeiro Emprego

A partir desta versão, o módulo usa exclusivamente:

- `frontend/modules/primeiro-emprego/primeiro-emprego-ui-20260819.css`
- `frontend/modules/primeiro-emprego/primeiro-emprego-ui-20260819.js`

Os nomes antigos `module.css`, `module2.0.css`, `module.js` e `module2.0.js` foram removidos para evitar cache ou arquivos divergentes na hospedagem.

O carregamento é feito por `primeiro-emprego/_layout.php` e possui fallback explícito em `frontend/layouts/head.php` e `frontend/layouts/scripts.php`.
