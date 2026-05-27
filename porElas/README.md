# Coari por Elas — MVP com mapa real corrigida para mobile

MVP front-end em HTML, CSS e JavaScript para demonstração mobile.

## Correções aplicadas

- Troca dos botões externos do Google Maps por links `<a>` reais, evitando bloqueio de `window.open()` no celular.
- Remoção de `window.open()` depois de GPS/Promise.
- JavaScript refeito com maior compatibilidade mobile, sem `async/await`, `optional chaining`, `replaceAll`, `globalThis` e outros recursos que podem quebrar em WebViews antigos.
- Correção de `z-index` para o mapa Leaflet não bloquear botões, menu inferior ou cabeçalho.
- Links de rota sincronizados automaticamente com a localização e com o destino selecionado.
- Fallback de pontos demonstrativos caso o OpenStreetMap/Overpass não retorne dados próximos.

## Recursos

- Mapa real com Leaflet + OpenStreetMap.
- Geolocalização do dispositivo pelo navegador.
- Busca de unidades próximas via Overpass/OpenStreetMap.
- Filtros para UBS/Saúde, CRAS/Social, Segurança e Agências.
- Lista de unidades próximas com distância aproximada.
- Botões para copiar rota e abrir rota no Google Maps.

## Como testar

1. Suba a pasta em hospedagem HTTPS ou rode em um servidor local.
2. No celular, permita a localização.
3. Acesse **Mapa de apoio** pela tela inicial ou por **Mais > Localização compartilhável**.
4. Toque nos filtros, selecione um local e use **Abrir no Maps** ou **Iniciar rota**.

## Observação técnica

Para produção, mantenha uma base oficial própria de UBS, CRAS, CREAS, Delegacia, Patrulha Maria da Penha e demais órgãos, validada pela prefeitura/secretaria. O OpenStreetMap é útil para demonstração, mas pode estar incompleto ou desatualizado em algumas regiões.
