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

### Leitura local de CPF por OCR

A página `consulta-documento.php` também permite ler automaticamente o CPF impresso em identidade, CIN, RG, CNH ou imagem selecionada pelo operador. O reconhecimento usa Tesseract.js `7.0.0` carregado somente nessa página e executa todo o OCR no navegador.

O scanner rápido processa apenas uma faixa horizontal centralizada onde o operador posiciona a linha do CPF, sem analisar a identidade inteira. A câmera faz tentativas contínuas sobre essa região e consulta automaticamente o SIGAS quando encontra um CPF matematicamente válido.

A fotografia não é enviada ao servidor, não é armazenada e não é adicionada a `FormData`. O texto bruto reconhecido também não é persistido. Somente um CPF matematicamente válido preenche o mesmo campo da consulta manual e segue pelo endpoint existente em modo `entrega_rapida`, que evita carregar relações completas do ANEXO quando a pessoa já está inscrita no SIGAS.

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
