# FluxEmpresa

Sistema web SaaS para empresas controlarem solicitações, orçamentos, execução/entrega, comprovantes, pagamentos e prestação de contas.

## Objetivo do MVP

Criar uma base segura em PHP puro + MySQL para operar múltiplas empresas dentro da mesma instalação, com isolamento por `empresa_id` e uma área de Super Admin da L&J para administrar todos os clientes sem precisar usar o login de cada empresa.

## Fluxo principal

1. Empresa cadastra cliente.
2. Empresa cadastra produtos/serviços.
3. Operador cria solicitação/pedido.
4. Sistema gera orçamento.
5. Orçamento é exportado em PDF.
6. Orçamento é enviado pelo WhatsApp.
7. Cliente aprova.
8. Empresa executa serviço ou entrega produto.
9. Empresa anexa comprovantes/fotos/documentos.
10. Financeiro controla pagamento.
11. Sistema gera relatório de prestação de contas.

## Perfis iniciais

- `SUPER_ADMIN`: L&J, acesso global a todas as empresas.
- `ADMIN_EMPRESA`: dono/gestor da empresa cliente.
- `OPERADOR`: cria solicitações, atualiza execução e anexos.
- `FINANCEIRO`: controla pagamentos e relatórios financeiros.

## Segurança obrigatória

- Nunca salvar credenciais reais no repositório.
- Usar `.env` local baseado no `.env.example`.
- Toda tabela operacional deve ter `empresa_id`.
- Todas as consultas devem filtrar por `empresa_id`, exceto telas do `SUPER_ADMIN`.
- Toda ação POST deve usar CSRF.
- Uploads devem validar tamanho, extensão e MIME real.
- Logs devem registrar ações sensíveis.

## Estrutura

```txt
fluxEmpresa/
├── public/
├── app/
├── database/
├── storage/
├── .env.example
├── .gitignore
├── README.md
└── IMPLEMENTACAO.md
```

## Status

Estrutura inicial criada para o MVP. As próximas etapas são implementar autenticação real, migrations, CRUDs e geração de PDF/WhatsApp.
