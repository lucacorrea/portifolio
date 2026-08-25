# SIGAS — Importação de pagamentos por PDF

## Arquivos alterados

- `primeiro-emprego/_layout.php`
  - preserva `extraStyles` e `extraScripts` definidos pela página.
- `frontend/modules/primeiro-emprego/pages/importar-candidatos.php`
  - separa a central em **Planilha de candidatos** e **PDF de pagamentos**;
  - adiciona análise AJAX sem perder o arquivo selecionado;
  - mantém intacto o fluxo XLSX/CSV existente.
- `frontend/modules/primeiro-emprego/module.css`
  - estilos isolados da nova interface de conciliação.
- `database/primeiroEmprego/ATUALIZAR_HOSPEDAGEM_PRIMEIRO_EMPREGO.sql`
  - inclui as tabelas de auditoria de pagamentos.
- `database/primeiroEmprego/README.md`
  - adiciona a migration 0006.

## Arquivos novos

- `frontend/modules/primeiro-emprego/lib/payment-pdf.php`
  - valida PDF, extrai texto no servidor quando possível, interpreta o extrato, compara com `pe_candidatos`, detecta conflitos em `pe_bolsas` e executa a conciliação.
- `frontend/modules/primeiro-emprego/payment-pdf-import.js`
  - usa PDF.js no navegador para ler todas as páginas, enviar a pré-análise e renderizar a conferência.
- `database/primeiroEmprego/0006-primeiroEmprego-pagamentos-pdf.sql`
  - cria `pe_pagamento_importacoes` e `pe_pagamento_importacao_itens`.
- `LEIA-ME_IMPORTACAO_PAGAMENTOS_PDF.txt`
  - instalação e regras operacionais.

## Regras implementadas

1. O PDF não cadastra 972 pessoas novamente.
2. CPF com menos de 11 dígitos recebe zeros à esquerda e passa pela validação dos dígitos verificadores.
3. Somente CPF válido e único em `pe_candidatos` é conciliado automaticamente.
4. Nome divergente gera alerta, mas não substitui o CPF como identificador.
5. CPF não encontrado, ambíguo ou inválido fica registrado para revisão.
6. O PDF pode marcar `pe_candidatos.status = 'Contemplado'`, mas não altera dados pessoais.
7. `pe_bolsas` é usada como tabela financeira principal.
8. Bolsa existente com valor/data incompatível não é sobrescrita.
9. O mesmo PDF concluído é bloqueado pelo hash SHA-256.
10. A lista precisa estar marcada como `PAGA`.
11. Quantidade e valor extraídos precisam conferir com o resumo do próprio PDF.
12. Todas as linhas ficam registradas nas tabelas de auditoria.

## Validação realizada

O extrato real fornecido foi processado em teste local:

- 972 linhas reconhecidas;
- R$ 486.000,00 somados;
- primeiro CPF `6591864202` normalizado para `06591864202` e validado;
- parser reconheceu Convênio 21684, Lista 7 e competência padrão 2026-08.
