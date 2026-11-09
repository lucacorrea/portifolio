# SIGAS — Padrão Visual Global

## Referência oficial

O módulo **Coari Meu Primeiro Emprego** é a referência visual e operacional do SIGAS.

O padrão global define **geometria, espaçamento, hierarquia, interação e responsividade**. Cada módulo preserva sua própria identidade de cor, seus campos, suas regras de negócio e seus tipos de revisão.

## Princípio

Uma pessoa que troca de módulo deve reconhecer imediatamente o mesmo sistema.

Devem permanecer iguais entre os módulos:

- sidebar e topbar;
- breadcrumb;
- cabeçalho da página;
- card principal da área de trabalho;
- hero interno;
- grid e tamanho dos KPIs;
- legenda operacional;
- painel de filtros;
- toolbar da tabela;
- densidade e tipografia da tabela;
- paginação;
- estados vazios;
- modais e central de ações;
- comportamento responsivo;
- clique na linha para abrir ações.

Podem variar entre os módulos:

- cor principal;
- ícone do módulo;
- nome dos indicadores;
- campos de filtro;
- colunas da tabela;
- tipos de pendência/revisão;
- ações permitidas;
- regras de negócio.

## Camadas de CSS

A ordem obrigatória é:

1. `assets/css/style.css` — base estrutural;
2. `assets/css/module-navigation.css` — navegação;
3. `assets/css/frontend-modules.css` — componentes compartilhados;
4. `assets/css/modules/<modulo>.css` — identidade e exceções específicas;
5. estilos extras estritamente necessários da página;
6. `assets/css/sigas-global-layout.css` — normalização visual final.

O arquivo `sigas-global-layout.css` é a autoridade para dimensões e geometria global.

## Tokens de módulo

O layout global usa a variável:

```css
--sigas-ui-accent
```

Ela é derivada automaticamente de:

```css
--module-accent
--frontend-accent
--program-accent
```

Portanto, uma página não deve fixar a cor principal diretamente quando a cor representa identidade do módulo.

Cores semânticas continuam globais:

- sucesso: verde;
- atenção: amarelo/laranja;
- erro/crítico: vermelho;
- informação: azul.

## Classes globais novas

As classes abaixo devem ser preferidas em componentes novos:

```text
sigas-page-header
sigas-page-actions
sigas-workspace-card
sigas-workspace-hero
sigas-kpi-grid
sigas-kpi
sigas-legend
sigas-filter-panel
sigas-filter-search
sigas-filter-clear
sigas-table-card
sigas-table-toolbar
sigas-data-table
sigas-pagination
sigas-action-modal
sigas-empty-state
```

As classes antigas `pe-*`, `cm-*` e `frontend-*` continuam suportadas durante a migração.

## Padrão de página de listagem

A ordem visual obrigatória é:

1. breadcrumb;
2. título da página e ações principais;
3. card operacional;
4. hero/contexto da área;
5. KPIs;
6. KPIs de qualidade/revisão, quando existirem;
7. legenda;
8. filtros;
9. toolbar da tabela;
10. tabela;
11. paginação.

## Filtros

O Primeiro Emprego é a referência de organização.

O painel deve:

- ser compacto;
- exibir pesquisa primeiro;
- usar selects autoexplicativos;
- manter todos os filtros relevantes visíveis em desktop;
- quebrar para duas colunas em tablet;
- quebrar para uma coluna no celular;
- usar botão principal `Filtrar` quando o filtro for server-side;
- possuir ação clara de limpar filtros.

### Filtros de revisão

Cada módulo define a sua própria taxonomia.

Exemplos:

**Primeiro Emprego**
- Revisar CPF;
- Revisar Telefone;
- Revisar Nascimento;
- Revisar Cadastro;
- CPF Duplicado;
- Sem pendência.

**Comida na Mesa**
- Revisar CPF;
- CPF duplicado;
- Revisar Telefone;
- Revisar Polo;
- Revisar Cadastro;
- Sem pendência.

A revisão cadastral deve ser separada do status operacional do programa.

## KPIs

Padrão:

- 4 colunas em desktop;
- 2 em tablet;
- 1 em celular pequeno;
- altura aproximada de 90 px;
- label pequena;
- valor destacado;
- descrição curta;
- linha superior indicando módulo ou semântica.

Não criar formatos de KPI exclusivos por página sem necessidade funcional real.

## Tabelas

As tabelas devem seguir o padrão operacional:

- cabeçalho compacto em caixa alta;
- linhas densas, mas legíveis;
- separadores leves;
- primeira informação visualmente forte;
- status em pills;
- hover discreto usando a cor do módulo;
- clique na linha abre a central de ações;
- ações destrutivas não ficam expostas diretamente na tabela;
- responsividade deve usar rolagem controlada ou cards móveis quando necessário.

## Central de ações

Por padrão, a linha da tabela não executa uma operação destrutiva diretamente.

Fluxo recomendado:

```text
linha → modal de ações → visualizar/editar/operar/histórico
```

A central de ações deve mostrar contexto suficiente antes dos comandos.

## Formulários

Campos devem seguir os mesmos raios, alturas e estados de foco do layout global.

Em telas longas:

- agrupar por seção;
- utilizar grids responsivos;
- manter ação principal previsível;
- não misturar filtros com formulário de cadastro.

## Modais

Padrão global:

- borda arredondada de 18 px em desktop;
- cabeçalho claro;
- kicker/eyebrow com cor do módulo;
- corpo rolável quando necessário;
- rodapé separado;
- botões com hierarquia visual clara;
- no mobile, priorizar aproveitamento vertical sem sobreposição.

## Regra de manutenção

Antes de adicionar CSS específico a uma página, verificar nesta ordem:

1. o comportamento já existe em `sigas-global-layout.css`?
2. pode ser resolvido por uma classe global `sigas-*`?
3. é realmente uma característica exclusiva do módulo?
4. é realmente uma característica exclusiva daquela página?

CSS de página é a última opção.

## Migração

A migração ocorre em três grupos:

### Grupo A — módulos com layout próprio

- Primeiro Emprego;
- Comida na Mesa.

Ambos são normalizados pelo Design System Global mantendo seus componentes existentes.

### Grupo B — módulos que usam componentes `frontend-*`

Recebem o padrão automaticamente pelo layout compartilhado e pelas classes `sigas-*`.

### Grupo C — páginas legadas com HTML próprio

Exemplos atuais incluem Portal, Administração, Unidades e algumas páginas operacionais históricas.

Essas páginas devem ser migradas individualmente para os layouts compartilhados, sem alterar suas regras de negócio.

## Regra final

Nenhum novo módulo deve criar um design system próprio.

O módulo pode criar componentes específicos, mas eles devem utilizar os mesmos tokens, grid, densidade, filtros, tabela e modais do **SIGAS Global UI**.
