# SECOM Coari — UI v4 Demonstração Completa

Esta versão preserva a estrutura física completa do protótipo v3 e adiciona uma camada de demonstração funcional.

## Estrutura

- `login.html`
- `index.html` — Dashboard
- `pages/agenda.html`
- `pages/pautas.html`
- `pages/pauta-detalhe.html`
- `pages/coberturas.html`
- `pages/demandas.html`
- `pages/producoes.html`
- `pages/materias.html`
- `pages/aprovacoes.html`
- `pages/publicacoes.html`
- `pages/acervo.html`
- `pages/originais.html`
- `pages/masters.html`
- `pages/preservacao.html`
- `pages/equipe.html`
- `pages/secretarias.html`
- `pages/relatorios.html`
- `pages/auditoria.html`
- `pages/usuarios.html`
- `pages/configuracoes.html`
- `pages/perfil.html`
- `assets/css/app.css`
- `assets/css/demo.css`
- `assets/js/app.js`
- `assets/js/demo.js`

## Recursos demonstrativos

- Login funcional de demonstração.
- Dashboard e navegação entre páginas reais.
- Agenda com modal de detalhes dos compromissos.
- Modal de cadastro de compromisso com responsável, equipe, tema, horário, local e briefing.
- Modais de cadastro para pauta, demanda, produção, matéria, publicação, profissional, secretaria e usuário.
- Modal de upload demonstrativo para o acervo.
- Visualização contextual de registros e arquivos.
- Ações de revisão, visualização, download demonstrativo e links.
- Relatórios clicáveis e exportação CSV.
- Verificação simulada de integridade do acervo.
- Perfis e permissões.
- Sidebar responsiva e recolhível.
- As páginas continuam renderizando mesmo se a camada de demonstração JavaScript falhar.

## Arquitetura futura

Para produção, a recomendação é manter as páginas/views e migrar a lógica para PHP 8.x OOP:

`Route -> Controller -> Service -> Repository -> MySQL / Storage`

Arquivos originais devem ficar em storage protegido, sem sobrescrita e com checksum SHA-256.
