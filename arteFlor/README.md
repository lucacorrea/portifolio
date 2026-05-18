# Arte&Flor — MVP PHP Catálogo de Vendas

Projeto em PHP puro para validação comercial da floricultura Arte&Flor.

## Objetivo

Criar uma vitrine digital profissional para a cliente aprovar o front antes do desenvolvimento completo do backend, com catálogo, detalhes de produto, carrinho, checkout demonstrativo no sistema, área do cliente, blog e área administrativa demonstrativa.

## Escopo atual

- PHP puro com includes reutilizáveis.
- HTML, CSS e JavaScript organizados.
- Sem banco de dados nesta primeira fase.
- Dados simulados em arquivos JSON.
- Carrinho e simulações usando localStorage.
- Preparado para futura integração com MySQL e painel administrativo real.

## Estrutura proposta

```txt
arteFlor/
├── index.php
├── catalogo.php
├── produto.php
├── carrinho.php
├── checkout.php
├── cliente.php
├── blog.php
├── post.php
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── produtos.php
│   ├── produto-form.php
│   ├── categorias.php
│   ├── categoria-form.php
│   ├── estoque.php
│   ├── caixa.php
│   ├── pedidos.php
│   ├── relatorios.php
│   ├── integracoes.php
│   ├── cupons.php
│   ├── cupom-form.php
│   ├── clientes.php
│   └── cliente-form.php
├── includes/
│   ├── config.php
│   ├── helpers.php
│   ├── header.php
│   ├── footer.php
│   ├── product-card.php
│   ├── admin-sidebar.php
│   ├── admin-head.php
│   └── admin-footer.php
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

1. Usar PHP puro.
2. Separar partes repetidas em includes.
3. Não criar banco de dados nesta fase.
4. Não implementar login real ainda.
5. Usar JSON local como fonte de dados provisória.
6. Usar localStorage para carrinho e simulações administrativas.
7. Checkout deve finalizar o pedido dentro do sistema visual usando localStorage.
8. Área admin deve ser demonstrativa, mas visualmente profissional.
9. Layout mobile first.
10. Preparar estrutura para futura integração com MySQL, autenticação e API.

## Próxima fase

Depois da aprovação da cliente, transformar os JSONs em tabelas MySQL e implementar:

- Autenticação segura;
- CRUD real de produtos;
- Upload de múltiplas imagens;
- Controle de estoque;
- Caixa;
- Pedidos;
- Relatórios;
- Integração Pix, caso seja aprovado.
