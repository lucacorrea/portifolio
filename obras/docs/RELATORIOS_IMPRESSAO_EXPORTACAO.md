# OBRAS360 — relatórios para gestão, impressão e exportação

## O que foi alterado

- **Imprimir / Salvar PDF:** todos os botões de impressão dos módulos operacionais, Planejamento, Mapa e Central de Relatórios usam `public/relatorio-documento.php` com `formato=imprimir`. A página abre em nova guia com folha preparada para A4 (horizontal), cabeçalho da Secretaria, indicadores, resumo da situação, tabelas completas, responsável pela emissão e observação de integridade. Clique em **Imprimir / Salvar PDF** e selecione uma impressora ou **Salvar como PDF** no navegador.
- **CSV UTF-8:** gera diretamente a partir dos registros ativos do MySQL (não das tabelas fictícias do JavaScript), com BOM para abrir em programas de planilha, separador `;`, colunas do módulo e proteção contra interpretação de células como fórmulas.
- **Excel (.xlsx):** arquivo de Excel real com cabeçalho institucional, campos completos, autofiltro e formatação. Gera um ZIP XLSX válido mesmo sem a extensão `ZipArchive` no PHP.
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

1. Acesse `obras.php`, filtre por situação e faça uma pesquisa. Clique em **CSV** e **Excel**. Confirme a quantidade filtrada e os acentos nos arquivos.
2. Clique em **Imprimir**. Na nova guia, confira a diagramação e clique em **Imprimir / Salvar PDF**. Revise a prévia de impressão antes de salvar.
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
- A versão precisa ser validada no MySQL real da Hostinger após publicação. Os testes locais validam formato, lógica, sintaxe e a renderização do documento com dados fictícios.
