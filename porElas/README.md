# Coari por Elas — MVP Institucional

Protótipo mobile-first clicável em HTML, CSS e JavaScript puro.

## Como executar

1. Extraia o ZIP.
2. Abra `index.html` no navegador ou use a extensão Live Server no VS Code.
3. Para testar no celular, hospede a pasta ou acesse pelo IP local do computador.

## Telas principais

- Início institucional
- Acionamento de ajuda demonstrativo
- Rede de apoio
- Cadastro de até 3 contatos
- Localização
- Histórico local
- Orientações
- Mais / privacidade / saída rápida

## Observações técnicas

- Esta versão é somente front-end demonstrativo.
- Não envia SMS real.
- Não realiza ligação real.
- Usa `localStorage` apenas para simular persistência local.
- Usa `navigator.geolocation` quando disponível, com coordenada de fallback para demonstração.

Para produção, recomenda-se Flutter ou app nativo, com permissões reais de SMS, localização, telefonia, criptografia local, logs mínimos e política rigorosa de privacidade.
