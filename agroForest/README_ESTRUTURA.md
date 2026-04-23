# Agro Forest Amazon — Estrutura organizada do sistema

## Objetivo
Base organizada para um sistema de protocolo com três áreas principais:
- Recepção
- Administrativo
- Dono

## Arquitetura prática
- `public/` é a única pasta pública do navegador.
- `app/Views/` guarda apenas telas.
- `app/Controllers/` concentra regras de entrada.
- `app/Models/` guarda acesso a dados.
- `app/Services/` concentra regras de negócio.
- `app/Helpers/` guarda funções utilitárias.
- `storage/uploads/` recebe anexos.
- `database/` guarda schema, migrações e seeds.

## Fluxo recomendado
1. Recepção cria protocolo.
2. Administrativo recebe o protocolo e cria orçamento.
3. Dono acompanha tudo e gerencia usuários, permissões e relatórios.

## Como acessar
Ambiente simples:
- `public/index.php?area=recepcao&pagina=dashboard`
- `public/index.php?area=recepcao&pagina=novoProtocolo`
- `public/index.php?area=administrativo&pagina=dashboard`
- `public/index.php?area=dono&pagina=dashboard`

Com `.htaccess`, você pode evoluir depois para URLs mais bonitas.
