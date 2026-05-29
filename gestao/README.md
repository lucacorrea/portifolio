# L&J Caixa Premium - Páginas Separadas

Projeto HTML/CSS/JS com estrutura separada por páginas, mantendo o padrão visual premium clean.

## Estrutura

```txt
index.html
pages/
  nova-venda.html
  produtos.html
  produto-form.html
  relatorios.html
  clientes.html
  cliente-detalhes.html
  historico-vendas.html
  venda-detalhes.html
  comprovante.html
  configuracoes.html
assets/
  css/styles.css
  js/data.js
  js/app.js
  icons/icon.svg
  img/
manifest.json
service-worker.js
```

## Como testar

Abra o `index.html`.

## Como instalar no celular

1. Suba a pasta para a Hostinger com HTTPS ativo.
2. Abra pelo Chrome Android.
3. Toque em `⋮ > Adicionar à tela inicial`.
4. Abra pelo ícone criado.

## Observações

- Dados estão mockados em `assets/js/data.js`.
- Layout e interações estão em `assets/js/app.js`.
- Câmera depende de HTTPS ou localhost.

## Correção aplicada

Foi corrigido o problema da tela ficar embaçada ao abrir no navegador.
A causa era o backdrop do modal permanecendo visível mesmo com `hidden`.

Correção adicionada em `assets/css/styles.css`:

```css
.modal-backdrop[hidden] {
  display: none !important;
}
```
