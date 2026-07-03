# SIGAS Coari — estrutura organizada com Cadastro ANEXO

Protótipo front-end institucional da Secretaria Municipal de Assistência Social de Coari/AM.

## Objetivo desta versão

Esta versão reorganiza o SIGAS para coexistir com o sistema SEMTH/ANEXO que já está em funcionamento.

- o SIGAS mantém sua própria base de dados;
- o SEMTH é consultado somente para prevenir duplicidade;
- nenhum sistema altera automaticamente o outro;
- o formulário principal preserva os grupos de dados do ANEXO, mas apresenta os campos em uma ordem mais clara;
- família, solicitações, atendimentos e documentos passam a fazer parte de um único prontuário;
- o menu principal foi reduzido para evitar opções repetidas.

## Navegação principal

1. **Visão Geral** — indicadores e pendências.
2. **Consultar CPF / Documento** — comparação entre SIGAS e SEMTH.
3. **Novo Cadastro ANEXO** — formulário socioassistencial em sete etapas.
4. **Pessoas e Prontuários** — listagem única com paginação, origem e situação.
5. **Solicitações** — pedidos atuais vinculados ao prontuário.
6. **Atendimentos** — histórico de ações realizadas.
7. **Programas e Benefícios** — módulo consolidado.
8. **Comida na Mesa / Entregas** — concessões e entregas.
9. **Unidades** — acesso consolidado aos painéis das unidades.
10. **Relatórios, Integração, Usuários e Configurações** — gestão institucional.
11. **Manual do Sistema** — documentação de uso dentro da própria interface.

## Cadastro ANEXO

O arquivo `cadastro-anexo.html` contém sete etapas:

1. Identificação;
2. Endereço e território;
3. Situação socioeconômica;
4. Composição familiar;
5. Habitação e infraestrutura;
6. Demanda e avaliação inicial;
7. Documentos e revisão.

### Melhorias aplicadas

- validação obrigatória do CPF antes do preenchimento;
- consulta demonstrativa nas bases SIGAS e SEMTH;
- bloqueio de duplicidade;
- referência externa somente leitura;
- campos condicionais exibidos somente quando necessários;
- cálculo automático da renda familiar e per capita;
- composição familiar dinâmica;
- rascunho salvo no navegador;
- revisão final antes da conclusão;
- classificação de documentos por finalidade;
- linguagem operacional mais simples.

## Arquivos principais

```text
sigas-coari/
├── index.php
├── dashboard.php
├── sair.php
├── cadastro-anexo.html
├── pessoas.html
├── registro.html
├── solicitacoes.html
├── atendimentos.html
├── beneficios.html
├── modulo.html
├── unidades.html
├── integracao-semth.html
├── relatorios.html
├── usuarios.html
├── configuracoes.html
├── manual-sistema.html
├── assets/
│   ├── css/style.css
│   ├── img/brasao-placeholder.svg
│   └── js/
│       ├── app.js
│       ├── cadastro-anexo.js
│       ├── integration-demo.js
│       └── listagem-pessoas.js
└── docs/
    ├── ARQUITETURA-INTEGRACAO.md
    ├── FORMULARIO-ANEXO.md
    ├── GUIA-DE-NAVEGACAO.md
    ├── api-contract-example.json
    └── semth-readonly-user.sql
```

## CPFs demonstrativos

| CPF | Resultado |
|---|---|
| `123.456.789-09` | já existe no SIGAS; cadastro bloqueado |
| `987.654.321-00` | existe somente no SEMTH; referência local permitida |
| `111.222.333-44` | registros já vinculados; cadastro bloqueado |
| `555.666.777-88` | divergência; revisão obrigatória |
| outro CPF com 11 números | novo cadastro permitido |

## Execução

Abra `index.php` em um servidor web com PHP e o `.env` real configurado. Bootstrap, Bootstrap Icons, Chart.js e Google Fonts são carregados por CDN.

## Limitação do protótipo

O login e o dashboard já usam backend PHP, sessão persistente e banco. As demais páginas ainda preservam fluxos demonstrativos; consultas, gravações, uploads e rascunhos dessas áreas continuam simulados.
