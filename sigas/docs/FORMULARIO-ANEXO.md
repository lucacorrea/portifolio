# Especificação funcional — Cadastro ANEXO

## 1. Finalidade

O Cadastro ANEXO é o formulário-base do prontuário socioassistencial do SIGAS. Ele preserva as informações utilizadas pelo sistema ANEXO atual, mas reorganiza a coleta para reduzir repetição, ambiguidade e erro operacional.

## 2. Regra de identidade

Antes de liberar o formulário, o SIGAS deve consultar o CPF:

1. na base local do SIGAS;
2. na interface de leitura do SEMTH;
3. em uma tabela local de vínculos entre registros.

### Decisões

| Situação | Decisão |
|---|---|
| Não localizado | permitir novo prontuário SIGAS |
| Somente no SIGAS | bloquear e abrir prontuário existente |
| Somente no SEMTH | permitir referência local, sem alterar o SEMTH |
| Já vinculado | bloquear e abrir prontuário SIGAS |
| Divergência | bloquear e encaminhar para revisão |

## 3. Etapas do formulário

### 3.1 Identificação

- nome completo;
- CPF;
- NIS;
- RG, emissão e UF;
- data de nascimento;
- gênero;
- estado civil;
- naturalidade;
- nacionalidade;
- telefone principal e alternativo;
- data, hora, responsável e origem gerados pelo sistema.

### 3.2 Endereço e território

- logradouro;
- número;
- bairro ou comunidade;
- complemento;
- ponto de referência;
- tempo de moradia;
- grupo tradicional;
- unidade de referência;
- território de acompanhamento.

### 3.3 Situação socioeconômica

- situação de trabalho;
- local de trabalho, quando aplicável;
- renda individual;
- faixa de renda;
- PCD e tipo;
- BPC;
- Bolsa Família;
- benefício municipal;
- benefício estadual.

Valores familiares são calculados com base na renda do responsável, integrantes e benefícios informados.

### 3.4 Composição familiar

Cônjuge e demais moradores utilizam a mesma estrutura:

- nome;
- parentesco;
- nascimento;
- CPF;
- NIS;
- escolaridade;
- renda;
- PCD;
- BPC;
- ocupação;
- observação.

Isso elimina blocos separados e repetidos para cônjuge.

### 3.5 Habitação

- situação do imóvel;
- valor mensal, quando alugado ou financiado;
- tipo de moradia;
- abastecimento de água;
- energia;
- esgotamento;
- destino do lixo;
- características do entorno;
- quantidade de cômodos;
- observação habitacional.

### 3.6 Demanda

- tipo de demanda;
- prioridade;
- canal de entrada;
- tipificação;
- encaminhamento inicial;
- resumo objetivo;
- observação restrita.

A demanda atual deve gerar uma solicitação própria. Ela não deve sobrescrever o histórico do prontuário.

### 3.7 Documentos e revisão

Categorias previstas:

- identificação;
- residência;
- renda;
- outros.

O backend deve validar MIME, tamanho, extensão e autorização antes de armazenar o arquivo fora do diretório público.

## 4. Campos calculados

Não devem ser digitados manualmente:

- total de moradores;
- renda familiar;
- total de rendimentos;
- renda per capita;
- total de pessoas com deficiência.

## 5. Persistência futura

A gravação recomendada é transacional:

1. criar ou atualizar o prontuário;
2. salvar composição familiar;
3. criar solicitação inicial;
4. registrar documentos;
5. registrar vínculo externo, quando houver;
6. gravar auditoria;
7. confirmar a transação.

Nenhuma etapa pode executar escrita no SEMTH.
