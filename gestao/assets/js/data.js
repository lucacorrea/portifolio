window.AppData = {
  settings: {
    companyName: 'L&J Soluções Tech',
    companyPhone: '(97) 99999-0000',
    companyAddress: 'Coari - AM',
    receiptMode: 'ask',
    receiptTemplate: 'detailed',
    expirationAlertDays: 7,
    debtDueDays: 30
  },
  products: [
    { id: 1, name: 'Leite Integral 1L', sku: 'PROD-001', barcode: '7891000000011', category: 'Laticínios', lot: 'LT-2026-001', expiry: '2026-06-02', stock: 8, minStock: 10, price: 6.50, cost: 4.20, image: 'prod-leite.svg' },
    { id: 2, name: 'Café Torrado 500g', sku: 'PROD-002', barcode: '7891000000028', category: 'Mercearia', lot: 'CF-2026-018', expiry: '2026-11-10', stock: 24, minStock: 8, price: 18.90, cost: 11.40, image: 'prod-cafe.svg' },
    { id: 3, name: 'Iogurte Natural', sku: 'PROD-003', barcode: '7891000000035', category: 'Laticínios', lot: 'IO-2026-010', expiry: '2026-05-31', stock: 5, minStock: 12, price: 4.99, cost: 3.10, image: 'prod-iogurte.svg' },
    { id: 4, name: 'Arroz Tipo 1 5kg', sku: 'PROD-004', barcode: '7891000000042', category: 'Mercearia', lot: 'AR-2026-044', expiry: '2027-02-15', stock: 32, minStock: 15, price: 29.90, cost: 21.00, image: 'prod-arroz.svg' },
    { id: 5, name: 'Sabonete Neutro', sku: 'PROD-005', barcode: '7891000000059', category: 'Higiene', lot: 'SB-2026-003', expiry: '2027-08-20', stock: 2, minStock: 10, price: 3.50, cost: 1.80, image: 'prod-sabonete.svg' }
  ],
  clients: [
    { id: 1, name: 'Maria Oliveira', phone: '(97) 99999-0000', cpf: '000.000.000-00', address: 'Rua Exemplo, nº 123', debt: 320, paid: 100, due: '2026-05-30', status: 'Atrasado', history: ['20/05/2026 — Compra na conta — R$ 320,00', '28/05/2026 — Pagamento parcial — R$ 100,00', '28/05/2026 — Novo vencimento — 10/06/2026'] },
    { id: 2, name: 'João Silva', phone: '(97) 98888-1111', cpf: '111.111.111-11', address: 'Av. Principal, nº 55', debt: 0, paid: 860, due: '', status: 'Em dia', history: ['25/05/2026 — Pagamento concluído — R$ 86,50'] },
    { id: 3, name: 'Mercado São João', phone: '(97) 97777-2222', cpf: '22.222.222/0001-22', address: 'Centro Comercial', debt: 980, paid: 0, due: '2026-06-10', status: 'Devendo', history: ['26/05/2026 — Compra na conta — R$ 980,00'] }
  ],
  sales: [
    { id: 128, date: '2026-05-28', time: '15:42', seller: 'Lucas Corrêa', customer: 'Venda balcão', customerPhone: '', payment: 'PIX', status: 'Finalizada', subtotal: 244.80, discount: 0, addition: 2, total: 246.80, paid: 246.80, change: 0, device: 'Caixa 01', items: [{ productId: 1, name: 'Leite Integral 1L', lot: 'LT-2026-001', expiry: '2026-06-02', qty: 1, unit: 6.50 }, { productId: 2, name: 'Café Torrado 500g', lot: 'CF-2026-018', expiry: '2026-11-10', qty: 2, unit: 18.90 }, { productId: 4, name: 'Arroz Tipo 1 5kg', lot: 'AR-2026-044', expiry: '2027-02-15', qty: 1, unit: 29.90 }], audit: { createdBy: 'Lucas Corrêa', createdAt: '28/05/2026 às 15:42', lastChange: 'Nenhuma' } },
    { id: 129, date: '2026-05-28', time: '16:10', seller: 'Ana Silva', customer: 'Maria Oliveira', customerPhone: '(97) 99999-0000', payment: 'Conta do cliente', status: 'Em aberto', subtotal: 320, discount: 0, addition: 0, total: 320, paid: 100, change: 0, due: '2026-06-10', device: 'Caixa 02', items: [{ productId: 3, name: 'Iogurte Natural', lot: 'IO-2026-010', expiry: '2026-05-31', qty: 8, unit: 4.99 }, { productId: 4, name: 'Arroz Tipo 1 5kg', lot: 'AR-2026-044', expiry: '2027-02-15', qty: 4, unit: 29.90 }], audit: { createdBy: 'Ana Silva', createdAt: '28/05/2026 às 16:10', lastChange: 'Pagamento parcial em 28/05/2026' } }
  ],
  users: [
    { name: 'Lucas Corrêa', role: 'Administrador', status: 'Ativo' },
    { name: 'Ana Silva', role: 'Operador de caixa', status: 'Ativo' }
  ]
};