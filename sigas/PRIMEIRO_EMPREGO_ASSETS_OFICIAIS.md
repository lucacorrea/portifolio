# Assets oficiais — Coari Meu Primeiro Emprego

O módulo usa exclusivamente os assets registrados pelo `ModuleRegistry`:

- `assets/css/modules/primeiro-emprego.css`
- `assets/js/modules/primeiro-emprego.js`

Os antigos `module.css`, `module2.0.css`, `module.js` e `module2.0.js` foram removidos para evitar cache, duplicidade e divergência de carregamento na hospedagem.

O helper `sigas_frontend_asset()` adiciona `?v=<filemtime>` automaticamente, portanto alterações nesses arquivos invalidam o cache do navegador.
