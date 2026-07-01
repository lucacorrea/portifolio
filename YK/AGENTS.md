# AGENTS.md

- Menus de ações de tabelas devem usar `table-action-dropdown` e o portal global em `assets/js/osmais-app.js`; não prenda `.dropdown-menu` ao overflow da tabela nem resolva removendo `overflow-x` dos painéis.
- Orçamentos não possuem funcionários.
- A equipe operacional é definida somente na Ordem de Serviço.
- Uma OS pode ser criada sem equipe.
- Uma OS pode possuir um, dois ou mais funcionários.
- Cada integrante possui função operacional.
- Quando houver equipe, exatamente um integrante deve ser o responsável principal.
- Para agendar, a OS deve possuir pelo menos um funcionário e um principal.
- Todos os integrantes devem ser considerados na validação de conflito.
- Agenda e Serviços da Semana são projeções das OS.
- Orçamentos aprovados podem gerar uma OS operacional por vez.
- Finalizar uma OS é uma operação transacional.
- Produtos utilizados na finalização geram movimentação de estoque.
- Pagamentos geram movimentação de Caixa.
- Saldo pendente gera Conta a Receber.
- Pagamentos e movimentações financeiras nunca são apagados; correções usam estorno.
- Comprovante de serviço é documento não fiscal.
- Dropdown de ações usa table-action-dropdown e portal global.
