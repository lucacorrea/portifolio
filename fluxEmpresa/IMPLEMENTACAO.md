# Plano de Implementação do MVP FluxEmpresa

## Branch

`mvp/fluxempresa-base`

## Regra principal

O sistema será multiempresa. Toda tabela operacional precisa carregar `empresa_id` e todo acesso deve filtrar por empresa, exceto quando o usuário for `SUPER_ADMIN`.

## Área Super Admin L&J

O usuário `SUPER_ADMIN` pertence à L&J e possui `empresa_id = NULL`.

Esse perfil poderá:

- cadastrar empresas clientes;
- ativar/desativar empresas;
- criar administradores para cada empresa;
- trocar o contexto de empresa visualizada;
- auditar dados e logs;
- acessar suporte sem usar senha do cliente.

A troca de contexto deve ser registrada em log.

## Ordem correta para o Codex

### Fase 1 — Base segura

- Implementar login real em `public/login.php`.
- Buscar usuário por `usuario`.
- Validar senha com `password_verify`.
- Bloquear usuário inativo.
- Atualizar `ultimo_login`.
- Gravar log de login.
- Implementar logout.

### Fase 2 — Layout administrativo

- Criar layout reutilizável em `app/Views/layout`.
- Criar sidebar com menus por perfil.
- Criar dashboard responsivo.
- Criar alertas flash.

### Fase 3 — Super Admin

- CRUD de empresas.
- CRUD de usuários por empresa.
- Seletor de empresa ativa para suporte.
- Middleware de isolamento por empresa.

### Fase 4 — Cadastros da empresa

- Clientes.
- Produtos e serviços.
- Categorias simples.

### Fase 5 — Solicitações e orçamento

- Criar solicitação.
- Adicionar itens.
- Calcular total.
- Gerar orçamento.
- Gerar PDF.
- Abrir envio por WhatsApp.

### Fase 6 — Execução e prestação de contas

- Registrar execução.
- Anexar fotos/documentos.
- Marcar concluído.
- Gerar relatório de prestação de contas.

### Fase 7 — Pagamentos

- Registrar valor total.
- Registrar valor pago.
- Status: pendente, parcial, pago, atrasado, cancelado.
- Relatório financeiro básico.

## Checklist de segurança

- Sem credenciais no GitHub.
- `.env` obrigatório localmente.
- PDO com prepared statements.
- CSRF em todos os POSTs.
- `session_regenerate_id` no login.
- Upload com validação de MIME real.
- Bloquear upload de PHP, JS, HTML e executáveis.
- Logs para login, criação, edição, exclusão e troca de contexto.
- Nenhuma ação destrutiva por GET.

## Prompt inicial para o Codex

Use este texto no Codex:

```txt
Você está trabalhando no repositório lucacorrea/portifolio, branch mvp/fluxempresa-base, pasta fluxEmpresa.

Objetivo: implementar o MVP FluxEmpresa em PHP puro + MySQL, com segurança e organização profissional.

Preserve a estrutura já criada. Não coloque credenciais reais no repositório. Use .env. Toda ação POST precisa de CSRF. Toda consulta operacional precisa respeitar empresa_id, exceto SUPER_ADMIN.

Primeira tarefa:
1. Implementar login real em public/login.php.
2. Criar public/logout.php.
3. Criar função de log em app/Core/Audit.php.
4. Validar usuário ativo.
5. Usar password_verify.
6. Atualizar ultimo_login.
7. Registrar LOGIN_SUCESSO e LOGIN_FALHA.
8. Redirecionar para dashboard.php após login.
9. Manter layout simples e responsivo.
10. Não implementar CRUDs ainda.

Ao finalizar, liste arquivos alterados e riscos de segurança revisados.
```
