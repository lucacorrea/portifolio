# Coari por Elas — MVP Institucional

Protótipo mobile-first em HTML, CSS e JavaScript puro, sem build.

## Como testar

1. Abra `index.html` no navegador.
2. Para testar no celular, suba a pasta em uma hospedagem estática ou use Live Server no VS Code.
3. Cadastre ao menos um contato na tela **Contatos**.
4. Volte para **Início** e toque em **Acionar apoio** ou **Simular shake**.

## O que está funcional na MVP

- Navegação mobile clicável entre telas.
- Cadastro de até 3 contatos em `localStorage`.
- Simulação de GPS com link do Google Maps.
- Simulação de envio para Patrulha Maria da Penha e contatos.
- Simulação de acionamento por shake.
- Histórico local de alertas simulados.
- Tela de apoio institucional.
- Saída rápida para tela neutra.

## Observação importante

Este projeto é apenas front-end demonstrativo. Ele não envia SMS real, não faz ligação real e não coleta GPS real.
Para produção, implementar aplicativo nativo/Flutter com permissões reais, auditoria de segurança, testes de UX em situação de stress e política de privacidade.
