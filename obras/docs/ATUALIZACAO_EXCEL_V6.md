# OBRAS360 — Excel V6: padronização de tabelas

Atualização de 24/11/2026. Esta revisão afeta apenas a exportação `.xlsx`, sem alterar dados no MySQL ou o layout das páginas.

## Abas do arquivo Excel

- **Registros:** tabela principal com cabeçalhos em contorno completo, células centralizadas na vertical e horizontal, filtros e linhas alternadas.
- **Indicadores:** reconstruída em três colunas fixas (*Indicador*, *Valor* e *Referência*) e outra tabela de *Situação*, *Quantidade* e *Participação*. Foram removidas as mesclas internas que causavam bordas quebradas e desalinhamentos.
- **Dados completos:** todas as informações com cabeçalho padronizado e bordas em toda a tabela. O título institucional ocupa as seis primeiras colunas para continuar visível quando a aba é muito larga.

O cabeçalho `PREFEITURA MUNICIPAL DE COARI / OBRAS360` permanece centralizado nas três abas, com borda. Os títulos de relatório e as seções também recebem contorno discreto. A impressão/PDF conserva a fonte ampliada e **não foi modificada** nesta versão. CSV permanece desabilitado.

## Como aplicar

1. Substituir a pasta `obras/` no VS Code pela pasta do ZIP completo.
2. Sincronizar a branch `main` com o GitHub, que publica na Hostinger.
3. Atualizar a página (`Ctrl+Shift+R`) e baixar um **novo arquivo** Excel, pois arquivos já baixados não mudam.
4. Conferir nas três abas: cabeçalhos, alinhamentos, filtros e bordas.

Não reinstale o banco e não altere o arquivo de configuração privada do MySQL para essa atualização.
