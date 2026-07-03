SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO setores (nome, slug, descricao, ativo) VALUES
('SEMAS — Sede Administrativa', 'semas-sede', 'Sede administrativa da Secretaria Municipal de Assistência Social.', 1),
('CRAS 1', 'cras-1', 'Centro de Referência de Assistência Social 1.', 1),
('CRAS 2', 'cras-2', 'Centro de Referência de Assistência Social 2.', 1),
('CREAS', 'creas', 'Centro de Referência Especializado de Assistência Social.', 1),
('Casa do Cidadão', 'casa-cidadao', 'Unidade de atendimento integrado ao cidadão.', 1),
('TI / Suporte', 'ti-suporte', 'Setor técnico de suporte ao sistema.', 1),
('Administração do Sistema', 'administracao-sistema', 'Administração geral do SIGAS.', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ativo = VALUES(ativo);

INSERT INTO niveis_acesso (nome, slug, descricao, prioridade, ativo) VALUES
('Administrador', 'administrador', 'Acesso integral aos módulos e configurações do sistema.', 10, 1),
('Suporte', 'suporte', 'Superusuário técnico com acesso global aos módulos operacionais, usuários, auditoria, configurações e diagnósticos.', 20, 1),
('Gestor', 'gestor', 'Gestão operacional do próprio setor.', 30, 1),
('Técnico', 'tecnico', 'Atuação técnica nos registros do próprio setor.', 40, 1),
('Atendente', 'atendente', 'Atendimento e cadastro básico no próprio setor.', 50, 1),
('Leitura', 'leitura', 'Consulta limitada a dados autorizados.', 60, 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    prioridade = VALUES(prioridade),
    ativo = VALUES(ativo);

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
('Visualizar dashboard', 'dashboard.visualizar', 'Permite acessar o painel inicial autorizado.', 'dashboard', 1),
('Visualizar usuários', 'usuarios.visualizar', 'Permite visualizar contas de usuários.', 'usuarios', 1),
('Aprovar usuários', 'usuarios.aprovar', 'Permite aprovar solicitações de acesso.', 'usuarios', 1),
('Rejeitar usuários', 'usuarios.rejeitar', 'Permite rejeitar solicitações de acesso.', 'usuarios', 1),
('Editar usuários', 'usuarios.editar', 'Permite editar dados administrativos de usuários.', 'usuarios', 1),
('Bloquear usuários', 'usuarios.bloquear', 'Permite bloquear contas de usuários.', 'usuarios', 1),
('Desbloquear usuários', 'usuarios.desbloquear', 'Permite desbloquear contas de usuários.', 'usuarios', 1),
('Alterar setor de usuários', 'usuarios.alterar_setor', 'Permite alterar o setor vinculado ao usuário.', 'usuarios', 1),
('Alterar nível de usuários', 'usuarios.alterar_nivel', 'Permite alterar o nível de acesso do usuário.', 'usuarios', 1),
('Redefinir senha', 'usuarios.redefinir_senha', 'Permite iniciar redefinição de senha de usuários.', 'usuarios', 1),
('Encerrar sessão de usuário', 'usuarios.encerrar_sessao', 'Permite revogar sessões de usuários.', 'usuarios', 1),
('Promover administrador', 'usuarios.promover_administrador', 'Permite atribuir nível de administrador.', 'usuarios', 1),
('Visualizar auditoria de autenticação', 'auditoria.autenticacao_visualizar', 'Permite consultar eventos de autenticação.', 'auditoria', 1),
('Visualizar auditoria de usuários', 'auditoria.usuarios_visualizar', 'Permite consultar eventos administrativos de usuários.', 'auditoria', 1),
('Visualizar perfil', 'perfil.visualizar', 'Permite visualizar o próprio perfil.', 'perfil', 1),
('Editar perfil', 'perfil.editar', 'Permite editar dados básicos do próprio perfil.', 'perfil', 1),
('Alterar senha própria', 'perfil.alterar_senha', 'Permite alterar a própria senha.', 'perfil', 1),
('Visualizar prontuários', 'prontuarios.visualizar', 'Permite consultar prontuários autorizados por setor.', 'prontuarios', 1),
('Criar prontuários', 'prontuarios.criar', 'Permite criar prontuários autorizados por setor.', 'prontuarios', 1),
('Editar prontuários', 'prontuarios.editar', 'Permite editar prontuários autorizados por setor.', 'prontuarios', 1),
('Visualizar atendimentos', 'atendimentos.visualizar', 'Permite consultar atendimentos autorizados por setor.', 'atendimentos', 1),
('Criar atendimentos', 'atendimentos.criar', 'Permite criar atendimentos autorizados por setor.', 'atendimentos', 1),
('Editar atendimentos', 'atendimentos.editar', 'Permite editar atendimentos autorizados por setor.', 'atendimentos', 1),
('Visualizar relatórios', 'relatorios.visualizar', 'Permite consultar relatórios autorizados.', 'relatorios', 1),
('Visualizar configurações', 'configuracoes.visualizar', 'Permite visualizar configurações do sistema.', 'configuracoes', 1),
('Editar configurações', 'configuracoes.editar', 'Permite alterar configurações do sistema.', 'configuracoes', 1),
('Visualizar arquivos', 'arquivos.visualizar', 'Permite visualizar arquivos autorizados.', 'arquivos', 1),
('Enviar arquivos', 'arquivos.enviar', 'Permite enviar arquivos autorizados.', 'arquivos', 1),
('Remover arquivos', 'arquivos.remover', 'Permite remover arquivos autorizados.', 'arquivos', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

DELETE np
FROM nivel_permissoes np
INNER JOIN niveis_acesso n ON n.id = np.nivel_id
WHERE n.slug IN ('administrador', 'suporte', 'gestor', 'tecnico', 'atendente', 'leitura');

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
CROSS JOIN permissoes p
WHERE n.slug = 'administrador'
  AND p.ativo = 1;

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
CROSS JOIN permissoes p
WHERE n.slug = 'suporte'
  AND n.ativo = 1
  AND p.ativo = 1;

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'dashboard.visualizar',
    'perfil.visualizar',
    'perfil.editar',
    'perfil.alterar_senha',
    'prontuarios.visualizar',
    'prontuarios.criar',
    'prontuarios.editar',
    'atendimentos.visualizar',
    'atendimentos.criar',
    'atendimentos.editar',
    'relatorios.visualizar',
    'arquivos.visualizar',
    'arquivos.enviar',
    'arquivos.remover'
)
WHERE n.slug = 'gestor';

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'dashboard.visualizar',
    'perfil.visualizar',
    'perfil.editar',
    'perfil.alterar_senha',
    'prontuarios.visualizar',
    'prontuarios.criar',
    'prontuarios.editar',
    'atendimentos.visualizar',
    'atendimentos.criar',
    'atendimentos.editar',
    'arquivos.visualizar',
    'arquivos.enviar'
)
WHERE n.slug = 'tecnico';

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'dashboard.visualizar',
    'perfil.visualizar',
    'perfil.editar',
    'perfil.alterar_senha',
    'prontuarios.visualizar',
    'prontuarios.criar',
    'atendimentos.visualizar',
    'atendimentos.criar',
    'arquivos.visualizar',
    'arquivos.enviar'
)
WHERE n.slug = 'atendente';

INSERT INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'dashboard.visualizar',
    'perfil.visualizar',
    'prontuarios.visualizar',
    'atendimentos.visualizar',
    'arquivos.visualizar'
)
WHERE n.slug = 'leitura';

COMMIT;
