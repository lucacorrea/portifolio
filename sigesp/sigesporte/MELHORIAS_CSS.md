# Melhorias visuais — SIGESP

Atualização aplicada ao front-end demonstrativo em 03/08/2026.

## Principais melhorias

- hierarquia visual mais forte no dashboard e nas páginas internas;
- tipografia e espaçamentos ampliados para melhorar a leitura;
- cabeçalhos de página com identidade institucional própria;
- cards de indicadores com melhor contraste e profundidade;
- gráficos de barras corrigidos e visíveis;
- filtros distribuídos corretamente em toda a largura disponível;
- tabelas com cabeçalho fixo, hover e melhor densidade;
- formulários, stepper e áreas de upload refinados;
- sidebar e topbar modernizados;
- páginas de detalhe, modais, badges, timeline e atalhos aprimorados;
- comportamento mobile corrigido, sem margem lateral da sidebar;
- versão nos assets para evitar que o navegador mantenha CSS antigo em cache.

## Arquivos principais alterados

- `public/assets/css/app.css`
- `public/assets/css/theme.css` — novo
- `app/Views/layouts/app.php`
- `app/Views/layouts/auth.php`
- `app/Views/dashboard/index.php`
- `app/Views/components/demo-detail-page.php`

## Publicação

Substitua a pasta atual do SIGESP mantendo a estrutura existente. Após enviar os arquivos, limpe o cache do navegador ou abra a página em uma janela anônima. O parâmetro de versão dos assets também força a atualização do CSS.
