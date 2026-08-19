# Banco — Primeiro Emprego

## Hospedagem existente

Faça backup antes de qualquer alteração.

Para a versão operacional atual:

1. Garanta que `0002-primeiroEmprego-operacional.sql` e `0003-primeiroEmprego-programa.sql` já tenham sido aplicados.
2. Execute `0004-primeiroEmprego-lotacoes.sql` para criar a estrutura própria de lotações (caso ainda não exista).
3. Execute `0005-primeiroEmprego-padrao-operacional.sql` para adicionar a coluna `sigla` em `pe_parceiros`.

`0005` não cria siglas automaticamente; ele apenas prepara a estrutura e normaliza valores que já existirem.

## Instalação nova

Execute, na ordem:

1. `0001-primeiroEmprego.sql`
2. `0002-primeiroEmprego-operacional.sql`
3. `0003-primeiroEmprego-programa.sql`
4. `0004-primeiroEmprego-lotacoes.sql`
5. `0005-primeiroEmprego-padrao-operacional.sql`

O CPF do candidato não é a identidade técnica do registro. A identidade interna permanece sendo `pe_candidatos.id`, permitindo cadastro e posterior revisão de CPF ausente, inconsistente ou duplicado.
