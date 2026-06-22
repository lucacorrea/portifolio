# Arquitetura de integração SIGAS ↔ SEMTH

## Objetivo

Permitir que o novo SIGAS identifique pessoas já cadastradas no sistema SEMTH/SEMAS antes de criar um registro, evitando duplicidade sem transformar as duas aplicações em um único sistema.

## Separação de responsabilidade

### SIGAS

- proprietário dos cadastros novos;
- proprietário dos atendimentos, solicitações, benefícios e documentos produzidos no novo fluxo;
- pode criar, editar e arquivar somente seus próprios registros;
- mantém `legacy_source` e `legacy_id` quando houver correspondência no SEMTH.

### SEMTH

- continua proprietário dos registros existentes;
- disponibiliza somente um conjunto mínimo de campos por view ou API;
- não recebe operações de escrita vindas do SIGAS;
- não é atualizado automaticamente quando um dado muda no SIGAS.

## Fluxo de cadastro

1. usuário informa o CPF;
2. API normaliza o CPF;
3. API consulta a base SIGAS;
4. API consulta a view autorizada do SEMTH com credencial `SELECT`;
5. serviço de comparação classifica o resultado;
6. interface decide a ação permitida.

### Estados

| Estado | SIGAS | SEMTH | Ação |
|---|---|---|---|
| `create-new` | não encontrado | não encontrado | liberar cadastro SIGAS |
| `open-existing` | encontrado | qualquer | bloquear novo e abrir existente |
| `create-reference` | não encontrado | encontrado | criar referência local, sem copiar silenciosamente todos os dados |
| `open-linked` | encontrado | encontrado e vinculado | abrir cadastro SIGAS |
| `review-conflict` | encontrado | encontrado com divergência | bloquear e enviar para revisão |

## Estrutura local recomendada

```sql
CREATE TABLE pessoa_referencias_externas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    sistema_origem VARCHAR(30) NOT NULL,
    identificador_externo VARCHAR(100) NOT NULL,
    cpf_hash CHAR(64) NULL,
    ultima_consulta_em DATETIME NULL,
    status_vinculo ENUM('Vinculado','Divergente','Inativo') NOT NULL DEFAULT 'Vinculado',
    UNIQUE KEY uk_origem_id (sistema_origem, identificador_externo),
    INDEX idx_pessoa (pessoa_id)
);
```

A tabela armazena referência, não uma cópia integral do cadastro antigo.

## Campos mínimos de consulta

- identificador do registro legado;
- CPF normalizado;
- nome;
- data de nascimento;
- NIS, quando existir;
- situação do registro;
- data da última atualização;
- unidade de origem.

Documentos, fotos, senhas, hashes, observações sociais completas e dados desnecessários não devem ser retornados na consulta preventiva.

## Auditoria

Registrar:

- usuário do SIGAS;
- data e hora;
- finalidade da consulta;
- CPF mascarado ou hash;
- resultado em cada base;
- decisão aplicada;
- identificadores retornados;
- IP e sessão.

## Regra técnica obrigatória

A conexão do SIGAS com o banco do SEMTH deve usar um usuário diferente do usuário da aplicação antiga, com acesso apenas às views autorizadas e somente permissão `SELECT`.
