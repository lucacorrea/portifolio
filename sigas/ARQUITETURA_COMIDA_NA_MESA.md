# Coari Comida na Mesa — arquitetura modular

## Regra de interface

O módulo usa o padrão operacional do SIGAS:

1. cabeçalho da página;
2. indicadores/KPIs;
3. filtros específicos do domínio;
4. tabela compacta e responsiva;
5. clique na linha para central de ações;
6. CRUD e operações em modais;
7. cor do módulo isolada em verde.

## Separação de responsabilidades

- `comida-mesa/*.php`: rotas públicas leves.
- `frontend/modules/comida-mesa/pages/`: composição de cada tela.
- `frontend/modules/comida-mesa/lib/bootstrap.php`: autenticação, permissões, CSRF e helpers.
- `frontend/modules/comida-mesa/lib/repository.php`: consultas analíticas/listagens exclusivas da UI.
- `frontend/modules/comida-mesa/lib/forms.php`: modais reutilizáveis.
- `frontend/modules/comida-mesa/lib/list-ui.php`: KPIs, cabeçalhos e modal de ações.
- `app/Repositories/ComidaMesaRepository.php`: persistência do domínio.
- `app/Services/ComidaMesaService.php`: regras de negócio.
- `api/comida-mesa/`: endpoints de escrita/consulta.

## Fonte real dos dados

Nenhuma página operacional nova depende de dados demonstrativos. As consultas são feitas nas tabelas reais do programa.

## Histórico e exclusão

Operações com impacto histórico não usam exclusão física quando isso comprometeria rastreabilidade. Polos são desativados; entregas são canceladas com justificativa; o histórico permanece somente leitura.
