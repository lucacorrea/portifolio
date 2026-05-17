# Arte&Flor — MVP Front Catálogo de Vendas

Projeto front-end demonstrativo para catálogo de vendas da floricultura Arte&Flor.

## Objetivo

Criar uma vitrine digital profissional para validação com a cliente, com catálogo, carrinho, checkout via WhatsApp, área do cliente, blog e área administrativa demonstrativa.

## Escopo atual

- Somente front-end.
- Sem backend.
- Sem banco de dados.
- Dados simulados em JSON.
- Carrinho e simulações usando localStorage.
- Preparado para futura integração com API.

## Estrutura

```txt
arteFlor/
├── index.html
├── catalogo.html
├── produto.html
├── carrinho.html
├── checkout.html
├── cliente.html
├── blog.html
├── post.html
├── admin/
│   ├── login.html
│   ├── dashboard.html
│   ├── produtos.html
│   ├── produto-form.html
│   ├── estoque.html
│   ├── caixa.html
│   ├── pedidos.html
│   └── relatorios.html
├── assets/
│   ├── css/
│   ├── js/
│   ├── img/
│   └── data/
```

## Identidade visual

Paleta base:

- Verde principal: `#4F8F6B`
- Verde hover: `#3D7254`
- Verde pastel: `#DDEBDD`
- Verde sálvia: `#AFCBB2`
- Verde menta: `#EAF6EA`
- Creme floral: `#FFF8F0`
- Rosa floral: `#F5C6D6`
- Vinho rosé: `#8A4A5B`
- Marrom natural: `#B48A63`
- Grafite: `#333333`

## Regras do MVP

1. Não criar backend nesta fase.
2. Não criar banco nesta fase.
3. Manter HTML, CSS e JavaScript puro.
4. Separar CSS e JS por responsabilidade.
5. Usar JSON local para dados fake.
6. Usar localStorage para carrinho e simulações administrativas.
7. Checkout deve gerar mensagem organizada para WhatsApp.
8. A área admin deve parecer real, mas sem autenticação real.
9. O layout deve ser mobile first.
10. O front precisa estar pronto para integração futura com API.
