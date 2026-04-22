# Estrutura base do sistema de protocolo em PHP

## Visão do fluxo
- **Recepção**: cadastra cliente, registra o tipo de serviço, abre protocolo e encaminha para o administrativo.
- **Administrativo**: analisa a solicitação, monta o orçamento, devolve status e acompanha aprovação.
- **Dono**: acessa todas as áreas, dashboards e permissões do sistema.

## Níveis de usuário sugeridos
- `recepcao`
- `administrativo`
- `dono`

## Estrutura profissional recomendada

```text
projeto_protocolo_php/
├── app/
│   ├── Config/
│   │   ├── app.php
│   │   ├── database.php
│   │   └── permissions.php
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── View.php
│   │   ├── Session.php
│   │   ├── Auth.php
│   │   └── Csrf.php
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── RecepcaoController.php
│   │   ├── AdministrativoController.php
│   │   └── DonoController.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   └── RoleMiddleware.php
│   ├── Models/
│   │   ├── Usuario.php
│   │   ├── Cliente.php
│   │   ├── Servico.php
│   │   ├── Protocolo.php
│   │   ├── Orcamento.php
│   │   ├── MovimentacaoProtocolo.php
│   │   └── Anexo.php
│   ├── Services/
│   │   ├── ProtocoloService.php
│   │   ├── OrcamentoService.php
│   │   └── NotificacaoService.php
│   ├── Helpers/
│   │   ├── url.php
│   │   ├── flash.php
│   │   ├── auth.php
│   │   └── format.php
│   └── Views/
│       ├── layouts/
│       │   ├── header.php
│       │   ├── footer.php
│       │   ├── sidebar-recepcao.php
│       │   ├── sidebar-administrativo.php
│       │   └── sidebar-dono.php
│       ├── auth/
│       │   └── login.php
│       ├── recepcao/
│       │   ├── dashboard.php
│       │   ├── novo-protocolo.php
│       │   ├── clientes.php
│       │   └── protocolos.php
│       ├── administrativo/
│       │   ├── dashboard.php
│       │   ├── orcamentos.php
│       │   └── analises.php
│       └── dono/
│           ├── dashboard.php
│           ├── usuarios.php
│           └── relatorios.php
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       │   ├── global.css
│       │   ├── recepcao-dashboard.css
│       │   ├── administrativo-dashboard.css
│       │   └── dono-dashboard.css
│       ├── js/
│       └── img/
├── routes/
│   └── web.php
├── storage/
│   ├── logs/
│   ├── uploads/
│   └── cache/
├── database/
│   ├── migrations/
│   └── schema.sql
└── README_ESTRUTURA.md
```

## Tabelas principais sugeridas
- `usuarios`
- `clientes`
- `servicos`
- `protocolos`
- `orcamentos`
- `movimentacoes_protocolo`
- `anexos`

## Regras importantes da arquitetura
1. A **recepção não faz orçamento**. Ela apenas cadastra, protocola e encaminha.
2. O **administrativo recebe apenas o que a recepção enviou** e trabalha o orçamento.
3. O **dono pode acessar tudo** e também executar as funções dos outros setores.
4. Cada perfil deve ter **dashboard próprio**, com cards, fila de trabalho e ações coerentes com sua função.
5. CSS separado por módulo deixa manutenção mais limpa e profissional.

## Entrega incluída nesta base
- View inicial da **Recepção** em PHP
- CSS separado da view
- Layout com sidebar, topbar, indicadores, tabela e fluxo operacional
- Estrutura base para evolução do sistema
