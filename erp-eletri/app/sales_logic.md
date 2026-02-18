# Sales & POS Module Implementation

## Components
1.  **Venda (Sale) Model**: Handles `vendas` table.
2.  **ItemVenda (SaleItem) Model**: Handles `venda_itens` table.
3.  **VendasController**:
    -   `index()`: List sales history.
    -   `create()`: The POS Interface (PDV).
    -   `store()`: Helper to save sale via AJAX/API.
4.  **Views**:
    -   `vendas/index.php`: History.
    -   `vendas/pos.php`: The POS UI (Vue.js or plain JS).

## POS Features
-   Product Search (AJAX).
-   Add item to cart (JS).
-   Update quantities.
-   Remove item.
-   Finalize Sale (Select Client, Payment Method).
-   Receipt (Print friendly).

## Database
-   `vendas` table already exists.
-   `venda_itens` table already exists.
-   We need to ensure `Movimentacao` model is called when sale is finalized to deduct stock.
