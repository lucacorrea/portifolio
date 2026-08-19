# Banco — Primeiro Emprego

## Hospedagem existente
Execute apenas:

`ATUALIZAR_HOSPEDAGEM_PRIMEIRO_EMPREGO.sql`

Esse arquivo reúne as alterações de revisão/importação (`0002`) e as tabelas operacionais adicionais (`0003`). Faça backup antes.

## Instalação nova
Execute:

1. `0001-primeiroEmprego.sql`
2. `0003-primeiroEmprego-programa.sql`

O CPF de candidato não possui restrição UNIQUE. A identidade do cadastro é o `id` interno.
