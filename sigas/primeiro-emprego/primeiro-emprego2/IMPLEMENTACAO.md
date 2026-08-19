# SIGAS — Meu Primeiro Emprego
## Importação integral + fila de revisão cadastral

### Regra de negócio desta versão

O candidato é identificado pelo `pe_candidatos.id`, nunca pelo CPF.

Nenhuma destas ocorrências bloqueia a inclusão:

- CPF não informado;
- CPF inválido/inconsistente;
- CPF duplicado;
- telefone não informado;
- telefone fora do padrão;
- data de nascimento não informada;
- data de nascimento inválida.

### Classificação automática

- 1 problema de CPF: `Revisar CPF`;
- 1 problema de telefone: `Revisar Telefone`;
- 1 problema de nascimento: `Revisar Data de Nascimento`;
- 2 ou mais problemas: `Revisar Cadastro`;
- CPF duplicado: o candidato é incluído, recebe `cpf_duplicado = 1` e entra na revisão adequada.

As pendências são independentes do `status` do programa. Assim, um candidato pode ser `Contemplado` e simultaneamente estar em `Revisar CPF`.

### Banco já instalado na hospedagem

Execute somente:

`database/003_revisao_sem_bloqueios.sql`

Depois substitua os arquivos do módulo pela versão deste pacote.

### Importação da lista atual

Na página `Importar candidatos`:

1. selecione o XLSX;
2. clique em `Validar sem gravar`;
3. confira os totais e as filas de revisão;
4. clique em `Importar todos`.

A lista de referência usada durante o desenvolvimento contém 972 candidatos e o parser desta versão classifica 110 para alguma revisão, sem bloquear nenhum por qualidade cadastral.

### Segurança

- CSRF continua obrigatório;
- arquivo limitado a XLSX/CSV e 8 MB;
- dados são gravados com prepared statements;
- CPF duplicado não causa UPDATE em outro candidato;
- cada linha importada recebe um ID próprio;
- auditoria por importação/linha é mantida em `pe_importacoes` e `pe_importacao_itens`.

### Próxima fase

As ações de revisão (corrigir CPF, confirmar sem telefone, confirmar dado indisponível, concluir revisão etc.) podem ser construídas sobre os campos:

- `revisao_status`;
- `revisao_cpf`;
- `revisao_telefone`;
- `revisao_nascimento`;
- `cpf_duplicado`;
- `revisao_motivos`;
- `revisao_atualizada_em`.
