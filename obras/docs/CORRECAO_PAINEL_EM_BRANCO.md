# Correção: conteúdo do painel desaparecia após atualização de relatórios

## Causa identificada

Na versão de 24/11/2026, a inclusão de impressão/Excel/CSV removeu por engano um bloco de constantes do arquivo `public/assets/app.js`:

- `FORM_ROUTES`
- `GEO_CENTER`, `GEO_KEYS`, `GEO_META`, `GEO_OFFSETS`
- `geoMapInstance`, `geoMapMarkers`, `geoPickerMarker`, `geoQuery`, `geoLayers`, `geoTried`

A função `renderPage()` referenciava `geoMapInstance` antes de renderizar a tela principal. Isso causava `ReferenceError: geoMapInstance is not defined` no navegador. Como `shell()` já havia montado a barra lateral e o cabeçalho, somente o conteúdo central ficava vazio.

## Correção realizada

O bloco de definições foi restaurado **sem desfazer os recursos de impressão e exportação**. Nenhuma alteração no banco de dados, no PHP de conexão, no login ou nas credenciais é necessária.

## Teste preventivo

No terminal do VS Code, a partir da raiz do repositório:

```bash
node obras/tools/testar_interface.js
```

O teste renderiza 20 páginas em JavaScript, sem MySQL e sem bibliotecas externas. Se uma variável indispensável tiver sido removida, a execução falha com código diferente de zero. É um teste de regressão do carregamento inicial, não substitui testes com navegador, conexão real e operações de cadastro.

## Como publicar

1. Extraia o ZIP completo e copie a pasta `obras` para o projeto no VS Code, preservando os arquivos de credenciais **somente na Hostinger**.
2. Faça commit e sincronize com o GitHub, no branch de deploy escolhido.
3. Após a publicação, abra `https://lucascorrea.pro/obras/public/index.php` e atualize com `Ctrl + Shift + R`.
4. Verifique painel, obras, mapa, relatórios e as opções de impressão/exportação. O `app.js` já usa `filemtime()` no endereço do script, ajudando a evitar cache antigo.

**Não precisa importar SQL novamente. Não crie nem exclua tabelas para resolver esta falha.**
