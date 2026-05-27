# Coari por Elas — MVP com mapa real

MVP front-end em HTML, CSS e JavaScript para demonstração mobile.

## O que foi adicionado

- Mapa real com Leaflet + OpenStreetMap.
- Geolocalização do dispositivo pelo navegador.
- Busca de unidades próximas via Overpass/OpenStreetMap.
- Filtros para UBS/Saúde, CRAS/Social, Segurança e Agências.
- Lista de unidades próximas com distância aproximada.
- Botões para copiar rota e abrir rota no Google Maps.
- Fallback visual com pontos demonstrativos quando o OpenStreetMap não retorna dados próximos.

## Como testar

1. Abra o `index.html` em um servidor local ou hospedagem HTTPS.
2. No celular, permita o acesso à localização.
3. Acesse **Mais > Localização compartilhável** ou toque no card **Mapa de apoio** na tela inicial.
4. Use os filtros e o botão **Iniciar rota**.

## Observação técnica

Para uma versão de produção, o correto é manter uma base oficial própria de UBS, CRAS, CREAS, Delegacia, Patrulha Maria da Penha e demais órgãos, validada pela prefeitura/secretaria. O OpenStreetMap é útil para demonstração, mas pode estar incompleto ou desatualizado em algumas regiões.
