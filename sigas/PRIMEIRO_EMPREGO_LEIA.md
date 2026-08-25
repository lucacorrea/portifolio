# SIGAS — Coari Meu Primeiro Emprego (operacional)

Esta versão usa a arquitetura nativa do SIGAS: `ModuleRegistry`, `PageContext`, `Database`, `Csrf`, `Storage`, `module-layout.php`, `module.css` e `module.js`.

## Atualização de uma hospedagem que já possui o SIGAS

1. Faça backup do banco e dos arquivos atuais.
2. No banco usado pelo SIGAS, execute:
   `database/primeiroEmprego/ATUALIZAR_HOSPEDAGEM_PRIMEIRO_EMPREGO.sql`
3. Suba os arquivos deste projeto preservando o `.env` existente da hospedagem.
4. Garanta permissão de escrita nos diretórios privados configurados por `SIGAS_IMAGE_PATH` e `SIGAS_DOCUMENT_PATH`.
5. Entre em **Coari Meu Primeiro Emprego** e faça primeiro uma validação da planilha em **Importar candidatos** antes de gravar.

## Instalação nova do módulo

Execute, nesta ordem:

1. `database/primeiroEmprego/0001-primeiroEmprego.sql`
2. `database/primeiroEmprego/0003-primeiroEmprego-programa.sql`

`0002-primeiroEmprego-operacional.sql` é uma migração para bases já existentes e está incluída no SQL consolidado de atualização da hospedagem.

## Regras de importação implementadas

- Nenhuma pessoa é bloqueada por CPF ausente, inválido ou duplicado.
- Cada candidato é identificado pelo `id` interno; CPF não é identificador único.
- CPF ausente/inválido gera **Revisar CPF**.
- Telefone ausente/fora do padrão gera **Revisar Telefone**.
- Nascimento ausente/inválido gera **Revisar Data de Nascimento**.
- Duas ou mais pendências geram **Revisar Cadastro**, preservando os motivos individuais.
- CPF válido repetido entre pessoas distintas não mescla cadastros: ambos permanecem e recebem alerta de duplicidade.
- Revisões podem corrigir o dado ou confirmar manualmente a situação, mantendo histórico de responsável/data.

## Fluxos operacionais incluídos

- Painel com indicadores reais.
- Candidatos, busca, filtros e paginação.
- Cadastro inicial/triagem.
- Importação XLSX/CSV com pré-validação e auditoria.
- Revisão de CPF, telefone, nascimento e cadastro.
- Visita social e parecer técnico.
- Ficha cadastral final e lotação.
- Parceiros.
- Vagas e oportunidades.
- Encaminhamentos.
- Documentação em storage privado.
- Frequência.
- Bolsas.
- Capacitações e participantes.
- Acompanhamentos.
- Relatórios e CSV.
- Configurações do módulo.

## Validação feita com a planilha fornecida

Resultado esperado na pré-validação:

- 972 registros processáveis.
- 862 sem pendência.
- 110 para revisão.
- 90 em Revisar CPF.
- 13 em Revisar Telefone.
- 3 em Revisar Data de Nascimento.
- 4 em Revisar Cadastro.
- 4 candidatos envolvidos em CPF duplicado.
- 0 bloqueados.

## Segurança

- Autenticação utiliza o `PageContext` do SIGAS.
- Formulários usam CSRF do projeto.
- Consultas usam PDO/prepared statements.
- Documentos e imagens usam o `Storage` privado do SIGAS.
- O download de documentos exige sessão autenticada e envia cabeçalhos `private/no-store`.
- Não há credenciais de banco fixadas no módulo.
