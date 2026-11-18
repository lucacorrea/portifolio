# Verificação de instalação e aceitação

## Pré-requisitos

- PHP 8.2+, `PDO` e **pdo_sqlite** ou **pdo_mysql**, `fileinfo`, `openssl`, suporte Argon2id.
- Servidor web com `DocumentRoot=public/`, conta de serviço com permissão de escrita em `storage/`.
- Navegador moderno (Chrome/Edge/Firefox), internet para mapa. Dados de demonstração somente no banco de testes.

## Roteiro de teste manual

1. Instalação em banco vazio: executar `php bin/setup.php --demo --email=...`. Confirmar que exibe senha temporária, sem guardar a senha em arquivos.
2. Entrar com senha temporária: confirmar que exige troca antes do painel. Alterar e repetir login. Senha antiga não pode funcionar.
3. Administrador: criar usuário técnico, copiar senha temporária, confirmar alteração obrigatória e bloqueio de criação de usuários para não administradores.
4. Consultor (consulta): abrir todas as listagens/relatórios; tentar POST de criação sem permissão e confirmar HTTP 403.
5. Obras: criar, pesquisar, editar execução, arquivar. Confirmar ausência da listagem e auditoria de criação/edição/arquivamento.
6. Fiscalização/Projetos/Medições: cadastrar com obra vinculada; validar que obra inexistente não pode ser vinculada.
7. Documento: anexar PDF permitido, baixar após login, tentar arquivo `.php` ou extensão incorreta; rejeição esperada.
8. Estoque: criar material com saldo inicial; entrada 10; saída 5; conferir saldo e histórico. Testar saída acima do saldo; rejeição esperada.
9. Protocolo: criar, encaminhar entre setores, conferir status e trilha de encaminhamento.
10. Planejamento: cadastrar marco, editar data, validar exibição no cronograma e listagem.
11. Mapa: cadastrar coordenadas em uma obra e prédio, abrir mapa, usar filtros, clicar para abrir ficha e exportar `.geojson`.
12. Relatórios: filtrar por status/pesquisa, exportar CSV/XLSX, abrir no Excel ou LibreOffice, imprimir/salvar como PDF e conferir valores; testar entrada começando `=`, `+` ou `@` para conferir proteção CSV.
13. Segurança: acessar documento sem login, tentar POST sem CSRF, tentar enviar usuário não autorizado e confirmar negação.
14. Verificar responsividade em 360, 768 e 1440 px; conferir menus, tabelas e fontes.

## Verificações automáticas incluídas

- `php bin/test-security.php`: unicidade do hash Argon2id, verificação, senha inválida e estrutura XLSX/ZIP sem banco.
- `php -l` em cada arquivo PHP (sintaxe).
- O arquivo `database/sqlite.sql` pode ser verificado usando SQLite em banco temporário.

## Notas

O ambiente de desenvolvimento em que a entrega foi produzida possuía PHP para validação de sintaxe, mas **não tinha drivers PDO instalados**. Portanto a **execução ponta a ponta com banco não pôde ser confirmada nesse ambiente**; ela deve fazer parte da aceitação técnica no servidor de destino. Não confundir lint e verificação estática com homologação de segurança.
