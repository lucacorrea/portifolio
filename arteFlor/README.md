# Arte&Flor — MVP Front-end Premium

Projeto demonstrativo em PHP, HTML, CSS e JavaScript puro para catálogo de floricultura, carrinho, checkout, área do cliente, painel administrativo e frente de caixa/PDV.

## Importante

Este pacote é somente front-end/MVP visual:

- sem backend real;
- sem banco de dados;
- sem autenticação real;
- sem pagamento real;
- sem API real;
- pedidos e vendas são simulados com `localStorage`;
- imagens são externas e ilustrativas.

## Publicação na hospedagem

Envie a pasta `arteFlor` completa para a hospedagem e abra:

```txt
/arteFlor/index.php
/arteFlor/catalogo.php
/arteFlor/admin/login.php
```

O projeto usa `base_url()` e `asset()` para carregar CSS, JS e links internos corretamente dentro da pasta `/arteFlor/`.

## Fluxo público

- Catálogo com imagens reais ilustrativas;
- Detalhes do produto com galeria;
- Carrinho com localStorage;
- Checkout com Pix demonstrativo;
- Pedido finalizado dentro do sistema visual;
- Área do cliente com pedidos fictícios/localStorage.

## Fluxo administrativo

- Login demonstrativo;
- Dashboard premium;
- Produtos e cadastro separados;
- Categorias e cadastro separados;
- Frente de caixa/PDV;
- Pedidos;
- Estoque;
- Relatórios;
- Integrações;
- Cupons;
- Clientes.

## Ajustes realizados nesta versão

- Adicionada camada `assets/css/premium-final.css` para acabamento visual premium;
- Header público e admin carregam a camada premium;
- Imagens do catálogo usam URLs externas reais;
- Cards de produto renderizam `<img>` corretamente;
- Botão principal de compra adiciona ao carrinho, não finaliza no WhatsApp;
- Checkout e PDV finalizam dentro do sistema visual;
- Corrigido bug visual do thumb do carrinho;
- Todas as páginas PHP foram testadas com `php -l`;
- Todas as páginas principais responderam HTTP 200 em servidor PHP local.
