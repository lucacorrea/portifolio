# Padrão Operacional — Meu Primeiro Emprego

## Regra global

1. Cabeçalho curto e objetivo.
2. Indicadores (KPIs) úteis para priorização.
3. Filtros específicos do fluxo.
4. Listagem/tabela como conteúdo principal.
5. Linhas brancas; estados são identificados por cor de texto/indicador.
6. Clique na linha abre modal de ações.
7. Cadastro, visualização e edição ficam em modais nas páginas operacionais de listagem.
8. Exclusão física só permanece onde a regra de negócio permite. Lotação é encerrada e preservada no histórico.

## Cores sem fundo de linha

- Verde: regular, ativo, aprovado, pago, concluído, lotado.
- Amarelo/laranja: pendência, atenção, não lotado, revisão cadastral.
- Vermelho: erro crítico, CPF duplicado, vencido, suspenso, revisar lotação.
- Azul: em andamento, encaminhado, pronto para importar.
- Cinza: encerrado/arquivado/histórico.

## Filtros por página

### Candidatos
- Pesquisa
- Revisão cadastral
- Status
- Bairro
- Setor/local
- Origem

### Órgãos e instituições parceiras
- Status
- Tipo
- Com/Sem participantes lotados
- Pesquisa por nome, sigla, responsável, CNPJ e contato

### Vagas
- Status
- Instituição
- Setor
- Pesquisa por cargo, instituição e requisitos

### Lotações
- Situação: Lotado / Não lotado / Revisar lotação / Pronto para importar
- Situação cadastral
- Tipo de pendência cadastral
- Órgão/instituição
- Local/setor
- Pesquisa por candidato, CPF e informação original da planilha

### Encaminhamentos
- Status
- Instituição
- Vaga/oportunidade
- Pesquisa por candidato, responsável e retorno

### Documentação
- Status
- Tipo de documento
- Com/Sem arquivo
- Pesquisa por candidato, documento e observação

### Frequência
- Competência
- Situação
- Local de atuação
- Faixa de frequência

### Bolsas
- Competência
- Status do pagamento
- Situação da frequência

### Capacitações
- Status
- Instituição
- Certificado

### Acompanhamentos
Visitas sociais:
- Decisão
- Entrevistador
- Técnico responsável

Acompanhamentos do programa:
- Status
- Tipo
- Local de atuação
- Responsável

### Relatórios
- Pesquisa
- Status
- Situação cadastral
- Situação de lotação
- Bairro
- Local/setor

## Lotações inconsistentes

A classificação `Revisar lotação` tem prioridade visual sobre `Lotado` quando a informação importada ou o local ativo contém valores claramente operacionais/suspeitos, como:

- NÃO VEIO DA SARA
- NÃO ESTÁ NA SARA
- PROCURAR CRACHÁ
- UFAM PROCURAR CRACHÁ
- PRIMEIRA VEZ / PRIMEIRE VEZ
- SEM INFORMAÇÃO / NÃO INFORMADO
- INÊS DE NAZAERÉ
- DARQUILANA AMORIM
- BERIANO

O texto original continua visível para correção manual.
