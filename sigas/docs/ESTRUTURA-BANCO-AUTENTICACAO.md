# Estrutura do banco de autenticação

A Parte 1 cria a base do controle de acesso, sem ativar ainda login funcional completo ou painel de suporte.

## Tabelas

- `setores`: setores administrativos e socioassistenciais.
- `niveis_acesso`: níveis como administrador, suporte, gestor, técnico, atendente e leitura.
- `permissoes`: permissões granulares por módulo.
- `nivel_permissoes`: relação N:N entre níveis e permissões.
- `usuarios`: contas de acesso, status, vínculo de setor e nível.
- `auditoria`: trilha de eventos de autenticação e administração.
- `sessoes_usuarios`: controle de sessões autenticadas.
- `arquivos`: metadados de arquivos privados futuros.

Todas as tabelas usam InnoDB, `utf8mb4`, chaves estrangeiras e índices para filtros frequentes.

## Setores iniciais

- `semas-sede`
- `cras-1`
- `cras-2`
- `creas`
- `casa-cidadao`
- `ti-suporte`
- `administracao-sistema`

As regras não devem depender do nome exibido do setor.

## Níveis iniciais

- `administrador`
- `suporte`
- `gestor`
- `tecnico`
- `atendente`
- `leitura`

## Permissões iniciais

O seed cria permissões para dashboard, usuários, auditoria, perfil, prontuários, atendimentos, relatórios, configurações e arquivos.

O administrador recebe todas as permissões.

O suporte recebe permissões de usuários, auditoria de autenticação/usuários, encerramento de sessão e próprio perfil. O suporte não recebe permissões operacionais de prontuários, atendimentos, arquivos ou relatórios.

Gestor, técnico, atendente e leitura recebem permissões operacionais progressivas por setor, sem gestão global de usuários.

## Primeiro administrador

O arquivo público temporário `instalacao/index.php` chama `app/Services/FirstAdminService.php`. Ele exige:

- método POST;
- `INSTALLATION_ENABLED=true`;
- `INSTALLATION_KEY` definida no `.env`;
- ausência do lock privado definido em `INSTALLATION_LOCK_PATH`;
- CSRF;
- inexistência de administrador anterior;
- transação;
- `password_hash`;
- senha marcada para troca obrigatória;
- registro em auditoria.

Após o uso, o lock privado é criado, `INSTALLATION_ENABLED` deve voltar para `false` e a pasta pública `instalacao/` deve ser removida.

## Seed e evolução

`schema.sql` é destinado à criação inicial do banco.

`seed.sql` sincroniza os níveis padrão (`administrador`, `suporte`, `gestor`, `tecnico`, `atendente`, `leitura`) de forma determinística: remove somente os vínculos desses níveis em `nivel_permissoes` e recria a matriz oficial.

Alterações posteriores em produção devem ser feitas por migrations versionadas, não por edição manual destrutiva do schema inicial.
