# OBRAS360 — protótipo visual interativo (apresentação)

Pacote completo com código-fonte **HTML, CSS e JavaScript**, sem backend e sem banco de dados. Conceito de interface para a Secretaria Municipal de Obras e Planejamento — Coari, Amazonas.

## Como apresentar

Extraia o pacote, abra `apresentacao.html` e navegue pelo painel. O menu lateral agora tem **Mapa interativo** e **Central de formulários**. Cada botão de novo cadastro/editar direciona para uma página completa e responsiva. Você também pode abrir `mapa-interativo.html`, `formularios.html` ou `index.html` diretamente.

## Arquivos

- `assets/app.js`: dados fictícios, renderização dos módulos, telas de formulários, gerenciamento local e funções cartográficas.
- `assets/styles.css`: visual base já existente.
- `assets/interactive.css`: estilos dos novos mapas, páginas de formulários e comportamento responsivo.
- `mapa-interativo.html`: visualização integrada de obras, ordens, fiscalização e imóveis.
- `formularios.html`: acesso a oito fichas de cadastro e três fluxos especiais.
- `cadastro-*.html`: formulários dedicados, com edição por `?editar=ID`.
- `cadastro-marco.html`, `movimentacao-estoque.html`, `encaminhamento-protocolo.html`: telas de fluxos especiais.
- Demais páginas `.html`: módulos pré-existentes preservados.

## Mapa e dependências

Na presença de internet, o protótipo carrega Leaflet 1.9.4 via CDN e os tiles do OpenStreetMap. Sem conexão ou quando o carregamento falhar, o sistema mantém uma representação **esquemática, interativa e ilustrativa** com pontos fictícios. O mapa online possui controle de zoom, deslocamento, popups e filtros. O código da interface não exige instalação.

O centro aproximado de referência é Coari/AM. **Todas as posições de marcadores de registros são simuladas e não correspondem a endereços reais**, mesmo quando mostradas sobre a base OpenStreetMap. Seleções de coordenadas por formulário são ilustrativas e ficam somente no navegador.

## Demonstração, não produção

Ações nos formulários usam `localStorage` do navegador (se disponível), sem comunicação com servidor. A área de anexos **não envia nem salva arquivos**. A tela de acesso é conceitual. Não há georreferenciamento validado, autorização por perfil, assinatura eletrônica, upload, auditoria, integração oficial, ou dados da Prefeitura. Use **Administrador → Restaurar dados originais** para recuperar os exemplos.

Para produção seriam necessários autenticação, backend, banco de dados, georreferenciamento de locais verificados, controles de acesso, auditoria, arquivos reais e homologação administrativa/técnica.
