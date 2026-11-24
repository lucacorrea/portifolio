# OBRAS360 — Excel e impressão institucional (V5)

## Entrega atual

- **Excel (.xlsx):** botões de exportação do painel e dos relatórios geram três abas: **Registros**, **Indicadores** e **Dados completos**, com dados consultados do MySQL e filtros aplicados no servidor. O cabeçalho **PREFEITURA MUNICIPAL DE COARI / OBRAS360** fica centralizado em todas as abas; a tipografia do Excel foi reduzida, e toda célula das tabelas de registros recebe borda discreta nos quatro lados.
- **Imprimir / Salvar PDF:** permanece como relatório A4 horizontal de fonte ampliada para leitura, com identificação do órgão, resumo, tabelas e dados completos. A impressão não foi reduzida junto com o Excel.
- **CSV:** opção removida do painel e da página de impressão, e desabilitada na rota de exportação. Para compartilhar dados com formatação, use **Excel (.xlsx)**.
- **Imprimir ficha:** gera documento individual do registro selecionado.

## Como testar

1. Abra um módulo (por exemplo, Ordens de serviço) com registros cadastrados e selecione **Excel**.
2. Verifique, na aba **Registros**, fonte menor, cabeçalho institucional centralizado e **bordas em todas as células** da tabela, inclusive linhas alternadas, percentuais e situação.
3. Abra **Indicadores** e **Dados completos**, confirmando que o cabeçalho também está centralizado.
4. Confira que **não existe mais** botão CSV nos módulos, Central de Relatórios, Usuários, Auditoria ou na barra de impressão.
5. Gere **Imprimir / Salvar PDF** e confirme que o texto permanece maior e legível.
6. Confirme que os filtros de busca, situação e período continuam presentes no arquivo exportado.

Os relatórios exigem autenticação. Os formatos não incluem hashes, senhas ou segredos do sistema. Não há mudança no esquema SQL, nas credenciais privadas ou no layout principal.
