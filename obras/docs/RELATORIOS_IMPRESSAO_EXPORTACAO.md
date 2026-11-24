# OBRAS360 — relatórios para gestão, impressão e exportação

## O que foi alterado

- **Imprimir / Salvar PDF:** todos os botões de impressão dos módulos operacionais, Planejamento, Mapa e Central de Relatórios usam `public/relatorio-documento.php` com `formato=imprimir`. A página abre em nova guia com folha preparada para A4 (horizontal), cabeçalho institucional discreto, título sem gradiente, indicadores de leitura fácil, tabela limpa, responsável pela emissão e observação de integridade. Clique em **Imprimir / Salvar PDF** e selecione uma impressora ou **Salvar como PDF** no navegador.
- **CSV UTF-8:** gera diretamente a partir dos registros ativos do MySQL (não das tabelas fictícias do JavaScript), com BOM para abrir em programas de planilha, separador `;`, colunas do módulo e proteção contra interpretação de células como fórmulas.
- **Excel (.xlsx):** arquivo de Excel com duas abas, **Resumo** (indicadores, filtros e distribuição por situação) e **Registros** (dados completos do MySQL). Possui cabeçalho institucional, valores monetários e percentuais numéricos, filtros, linhas alternadas e linhas de cabeçalho congeladas. A estrutura Open XML foi corrigida para evitar o aviso de reparação e a planilha vazia exibida no Excel. Funciona sem `ZipArchive` no PHP.
- **Imprimir ficha:** dentro da janela lateral de registro, o botão **Imprimir ficha** mostra os campos completos de um único registro, inclusive descrição e orçamento quando cadastrados.
- **Relatório executivo:** no painel geral existe um botão específico para gerar uma síntese com obras, execução, situação, orçamento estimado, ordens concluídas, alertas e materiais abaixo do mínimo.
- **Filtros:** na listagem, pesquisa, situação e ordenação são enviados ao relatório. Na Central de Relatórios, situação e intervalo de datas são respeitados igualmente no PDF, Excel e CSV.
- **Gestão administrativa:** contas de usuários e histórico de auditoria têm impressão e exportação disponíveis somente para suporte/administrador, sem incluir senhas nem hashes.

## Onde estão os arquivos

- `app/relatorios.php` — regras de seleção, filtros, agregações, CSV e XLSX.
- `public/relatorio-documento.php` — rota autenticada de impressão e downloads.
- `public/assets/relatorios.css` — modelo visual A4 isolado, que não altera o painel.
- `public/assets/relatorios.js` — botão de impressão/SALVAR PDF na nova guia.
- `public/assets/app.js` — ações dos botões do painel, com filtros preservados.

## Como testar depois do deploy

1. Acesse `obras.php`, filtre por situação e faça uma pesquisa. Clique em **Excel**. Confirme que o arquivo abre **sem pedir reparo**, e que as abas **Resumo** e **Registros** têm dados, valores e acentos. Repita o teste em outros módulos.
2. Clique em **Imprimir**. Na nova guia, confira o layout limpo, sem banner ilustrado. Clique em **Imprimir / Salvar PDF** e revise a prévia A4 horizontal. Desative os cabeçalhos e rodapés automáticos do Chrome se quiser uma folha somente com o conteúdo do sistema.
3. Abra a ficha de uma obra e clique em **Imprimir ficha**. A ficha deve trazer somente esse registro e todos os campos cadastrados.
4. Na **Central de Relatórios**, selecione um módulo, situação e período; gere a prévia e exporte em Excel, CSV e impressão. Devem corresponder ao mesmo recorte.
5. No **Painel geral**, clique em **Relatório executivo**. Confirme que os indicadores consideram todos os cadastros ativos.
6. Teste tabelas vazias, nomes longos, múltiplas páginas e ausência de registros.

## Segurança e limitações

- Os relatórios exigem **sessão autenticada**; arquivos de usuários/auditoria respeitam permissões do servidor.
- Não incluem senhas, hashes, chaves de API ou credenciais de banco.
- A geração de PDF é pelo **navegador** em sua opção *Salvar como PDF*. O servidor não cria arquivo PDF criptograficamente assinado.
- Os indicadores refletem dados cadastrados, sem validação administrativa automática. Registros marcados como `demo` são avisados e devem ser conferidos antes do uso oficial.
- Não houve alteração de SQL ou mudança de tabelas nesta entrega.
- **CSV é um formato de texto e não admite estilos visuais.** Para apresentações, utilize o **Excel (.xlsx)** ou imprima/salve em PDF.
- A versão precisa ser validada no MySQL real da Hostinger após publicação. Os testes locais validam formato, lógica, sintaxe e a renderização do documento com dados fictícios.


## Atualização V4 — leitura ampliada e Excel redesenhado

- Impressão: A4 horizontal com tipografia maior, maior contraste, tabelas legíveis e paginação natural.
- Excel (.xlsx): abre na aba **Registros** (6 campos principais), exibe **Indicadores** na segunda e **Dados completos** na terceira; cabeçalhos claros, larguras ajustadas, linhas alternadas, filtros e números reais.
- CSV (.csv): possui as mesmas **seis colunas principais** da aba Registros, UTF-8 com BOM e `;` como separador. **CSV não suporta tipografia, cores, abas, larguras ou formatação visual**, portanto para apresentação institucional use XLSX ou Imprimir/PDF.
- Ambos os formatos usam os mesmos filtros e consultam o MySQL ao serem gerados.
- Nenhuma alteração no banco de dados.
