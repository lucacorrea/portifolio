# SIGAS Coari — navegação operacional

Sistema institucional da Secretaria Municipal de Assistência Social de Coari/AM.

## Objetivo desta versão

Esta versão concentra a navegação exposta ao operador nos fluxos já funcionais do SIGAS.

- o SIGAS mantém sua própria base de dados;
- o ANEXO é consultado somente para prevenir duplicidade;
- nenhum sistema altera automaticamente o outro;
- o menu principal mostra apenas entradas operacionais prontas para uso;
- as páginas HTML antigas continuam no repositório como referência de prototipação.

## Navegação operacional

- `dashboard.php`: entrada autenticada e atalhos para os fluxos funcionais.
- `consulta-documento.php`: consulta por CPF e registro, cancelamento ou reativação de entrega mensal.
- `modulo.php`: beneficiários, cadastro, competências, documentos e histórico do COARI Comida na Mesa.
- `modulo.php?action=new`: abre a consulta de CPF antes do formulário; com CPF validado, exibe a página de nova inscrição.

## Páginas de prototipação

Arquivos HTML antigos, como `cadastro-anexo.html`, `pessoas.html`, `solicitacoes.html`, `atendimentos.html`, `beneficios.html`, `unidades.html`, `relatorios.html`, `integracao-semth.html`, `usuarios.html`, `configuracoes.html` e `manual-sistema.html`, permanecem no repositório como referências de prototipação. Eles não aparecem no menu operacional enquanto não forem fluxos funcionais.

## Arquivos principais

```text
sigas-coari/
├── index.php
├── dashboard.php
├── sair.php
├── cadastro-anexo.html
├── consulta-documento.php
├── pessoas.html
├── registro.html
├── solicitacoes.html
├── atendimentos.html
├── beneficios.html
├── modulo.php
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

## Consulta por CPF

`consulta-documento.php` consulta CPFs reais no banco do SIGAS, identifica pessoa, família e inscrição no Comida na Mesa, exibe a competência selecionada e permite registrar, cancelar ou reativar a entrega mensal conforme permissões e regras do backend.

## Integração ANEXO

A integração com o ANEXO usa conexão MySQL somente leitura e configuração externa em:

```text
/home/u784961086/configuracao/anexo/conect/.env
```

O arquivo real não deve ser criado dentro do repositório. Use `docs/ANEXO-ENV.example` como modelo seguro e mantenha o usuário MySQL com permissão apenas de `SELECT`.

## Execução

Abra `index.php` em um servidor web com PHP e o `.env` real configurado. Bootstrap, Bootstrap Icons, Chart.js e Google Fonts são carregados por CDN.

## Limitação

O login, o dashboard, `modulo.php` e `consulta-documento.php` usam backend PHP, sessão persistente, permissões e banco. As páginas HTML antigas preservam material demonstrativo fora da navegação operacional.
