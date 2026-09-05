SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
('Visualizar Primeiro Emprego', 'primeiro_emprego.visualizar', 'Permite consultar o módulo Coari Meu Primeiro Emprego e suas áreas operacionais.', 'primeiro_emprego', 1),
('Cadastrar candidato no Primeiro Emprego', 'primeiro_emprego.cadastrar', 'Permite cadastrar novos candidatos no programa.', 'primeiro_emprego', 1),
('Editar e acompanhar candidato no Primeiro Emprego', 'primeiro_emprego.editar', 'Permite revisar cadastro, registrar visita social e atualizar ficha cadastral de candidato.', 'primeiro_emprego', 1),
('Excluir candidato no Primeiro Emprego', 'primeiro_emprego.excluir', 'Permite excluir candidato mediante confirmação. Permissão restrita.', 'primeiro_emprego', 1),
('Importar candidatos no Primeiro Emprego', 'primeiro_emprego.importar', 'Permite validar e executar importações de candidatos e conciliações autorizadas.', 'primeiro_emprego', 1),
('Gerenciar vagas do Primeiro Emprego', 'primeiro_emprego.vagas_gerenciar', 'Permite cadastrar, editar e excluir vagas e oportunidades.', 'primeiro_emprego', 1),
('Gerenciar parceiros do Primeiro Emprego', 'primeiro_emprego.parceiros_gerenciar', 'Permite cadastrar, editar e excluir órgãos e instituições parceiras.', 'primeiro_emprego', 1),
('Gerenciar lotações do Primeiro Emprego', 'primeiro_emprego.lotacoes_gerenciar', 'Permite criar e alterar lotações dos participantes.', 'primeiro_emprego', 1),
('Gerenciar encaminhamentos do Primeiro Emprego', 'primeiro_emprego.encaminhamentos_gerenciar', 'Permite registrar e alterar encaminhamentos para oportunidades.', 'primeiro_emprego', 1),
('Gerenciar documentos do Primeiro Emprego', 'primeiro_emprego.documentos_gerenciar', 'Permite enviar, atualizar e remover documentos vinculados ao programa.', 'primeiro_emprego', 1),
('Gerenciar frequência do Primeiro Emprego', 'primeiro_emprego.frequencia_gerenciar', 'Permite registrar e alterar frequência dos participantes.', 'primeiro_emprego', 1),
('Gerenciar bolsas do Primeiro Emprego', 'primeiro_emprego.bolsas_gerenciar', 'Permite registrar e alterar informações de bolsas e pagamentos do programa.', 'primeiro_emprego', 1),
('Gerenciar capacitações do Primeiro Emprego', 'primeiro_emprego.capacitacoes_gerenciar', 'Permite cadastrar e alterar capacitações e participações.', 'primeiro_emprego', 1),
('Gerenciar acompanhamentos do Primeiro Emprego', 'primeiro_emprego.acompanhamentos_gerenciar', 'Permite registrar e alterar acompanhamentos e visitas do programa.', 'primeiro_emprego', 1),
('Gerenciar configurações do Primeiro Emprego', 'primeiro_emprego.configuracoes_gerenciar', 'Permite alterar parâmetros operacionais do módulo Primeiro Emprego.', 'primeiro_emprego', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.modulo = 'primeiro_emprego' AND p.ativo = 1
WHERE n.slug IN ('administrador', 'suporte')
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.visualizar',
    'primeiro_emprego.cadastrar',
    'primeiro_emprego.editar',
    'primeiro_emprego.importar',
    'primeiro_emprego.vagas_gerenciar',
    'primeiro_emprego.parceiros_gerenciar',
    'primeiro_emprego.lotacoes_gerenciar',
    'primeiro_emprego.encaminhamentos_gerenciar',
    'primeiro_emprego.documentos_gerenciar',
    'primeiro_emprego.frequencia_gerenciar',
    'primeiro_emprego.bolsas_gerenciar',
    'primeiro_emprego.capacitacoes_gerenciar',
    'primeiro_emprego.acompanhamentos_gerenciar'
)
WHERE n.slug = 'gestor'
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.visualizar',
    'primeiro_emprego.cadastrar',
    'primeiro_emprego.editar',
    'primeiro_emprego.lotacoes_gerenciar',
    'primeiro_emprego.encaminhamentos_gerenciar',
    'primeiro_emprego.documentos_gerenciar',
    'primeiro_emprego.frequencia_gerenciar',
    'primeiro_emprego.capacitacoes_gerenciar',
    'primeiro_emprego.acompanhamentos_gerenciar'
)
WHERE n.slug = 'tecnico'
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.visualizar',
    'primeiro_emprego.cadastrar',
    'primeiro_emprego.documentos_gerenciar'
)
WHERE n.slug = 'atendente'
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
JOIN permissoes p ON p.slug = 'primeiro_emprego.visualizar'
WHERE n.slug = 'leitura'
  AND n.ativo = 1;

COMMIT;
