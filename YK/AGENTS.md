# AGENTS.md

- Menus de ações de tabelas devem usar `table-action-dropdown` e o portal global em `assets/js/osmais-app.js`; não prenda `.dropdown-menu` ao overflow da tabela nem resolva removendo `overflow-x` dos painéis.
- Orçamentos não possuem responsável, funcionário ou dupla.
- A equipe operacional é definida somente na Ordem de Serviço.
- Cada OS pode possuir funcionário principal e funcionário de apoio.
- Agenda e Serviços da Semana são projeções das Ordens de Serviço agendadas e nunca devem duplicar o atendimento em tabelas próprias.
- Ordens de Serviço são a fonte única dos atendimentos.
- Agenda e Serviços da Semana projetam dados de ordens_servico.
- Não criar tabelas duplicadas para atendimentos.
- A equipe possui funcionário principal e funcionário de apoio.
- Os dois funcionários devem ser distintos.
- Conflitos de horário são validados pelo ServiceOrderManagementService.
