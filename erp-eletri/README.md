# ERP Elétrica - Sistema de Gestão

Sistema completo de gestão para loja de materiais elétricos (MVC / PHP / Bootstrap 5).

## 🚀 Como Acessar

1. Certifique-se de que o banco de dados está configurado em `config/database.php`.
2. Acesse a instalação para criar o banco de dados e usuários:
   - **URL:** `http://seu-servidor/erp_eletrica/install/setup.php` (ou local `public/index.php`)
   - Isso criará as tabelas e inserirá dados fictícios de teste.

3. Após a instalação, acesse o sistema:
   - **URL:** `http://seu-servidor/erp_eletrica/public/`

## 🔑 Credenciais de Acesso (Teste)

Todas as senhas padrão são **123456**.

| Nível      | Login              | Senha  |
|------------|--------------------|--------|
| Admin      | admin@admin.com    | 123456 |
| Gerente    | gerente@coari.com  | 123456 |
| Vendedor   | vendedor@coari.com | 123456 |
| Caixa      | caixa@coari.com    | 123456 |

## 🛠 Módulos

- **Pré-Venda (Balcão/F1):** Busca produtos (leitor de código de barras), seleciona preços (Normal/À Vista/Prefeitura) e gera pedido.
- **Caixa (PDV/F2):** Busca pré-venda pelo número e finaliza com múltiplas formas de pagamento.
- **Produtos:** Cadastro completo com 3 níveis de preço e imagem.
- **Estoque:** Controle por filial (Coari/Codajás).
- **Relatórios:** Vendas diárias e por forma de pagamento.

## 💻 Tecnologias

- **Backend:** PHP 8+ (PDO, MVC Pattern)
- **Frontend:** Bootstrap 5, Vanilla JS
- **Banco:** MySQL
- **Design:** Clean, Corporate, Technical

## 🔒 Segurança

- Senhas criptografadas (password_hash)
- Proteção contra SQL Injection (PDO Prepared Statements)
- Sessões Seguras
