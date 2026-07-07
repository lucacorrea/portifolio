SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo)
VALUES
    ('Visualizar Comida na Mesa', 'comida_mesa.visualizar', 'Permite visualizar o módulo Comida na Mesa.', 'comida_mesa', 1),
    ('Consultar CPF no Comida na Mesa', 'comida_mesa.consultar_cpf', 'Permite consultar CPF para operações do programa.', 'comida_mesa', 1),
    ('Cadastrar no Comida na Mesa', 'comida_mesa.cadastrar', 'Permite cadastrar famílias e inscrições do programa.', 'comida_mesa', 1),
    ('Editar Comida na Mesa', 'comida_mesa.editar', 'Permite editar cadastros e inscrições do programa.', 'comida_mesa', 1),
    ('Registrar entrega do Comida na Mesa', 'comida_mesa.entregar', 'Permite registrar entregas do programa.', 'comida_mesa', 1),
    ('Cancelar entrega do Comida na Mesa', 'comida_mesa.cancelar_entrega', 'Permite cancelar entregas do programa.', 'comida_mesa', 1),
    ('Visualizar documentos do Comida na Mesa', 'comida_mesa.documentos_visualizar', 'Permite visualizar documentos vinculados ao programa.', 'comida_mesa', 1),
    ('Enviar documentos do Comida na Mesa', 'comida_mesa.documentos_enviar', 'Permite enviar documentos vinculados ao programa.', 'comida_mesa', 1),
    ('Visualizar histórico do Comida na Mesa', 'comida_mesa.historico_visualizar', 'Permite visualizar histórico do programa.', 'comida_mesa', 1),
    ('Gerenciar competências do Comida na Mesa', 'comida_mesa.competencias_gerenciar', 'Permite gerenciar competências do programa.', 'comida_mesa', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.modulo = 'comida_mesa'
WHERE n.slug IN ('administrador', 'suporte', 'gestor');

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.consultar_cpf',
    'comida_mesa.cadastrar',
    'comida_mesa.editar',
    'comida_mesa.entregar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.documentos_enviar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'tecnico';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.consultar_cpf',
    'comida_mesa.cadastrar',
    'comida_mesa.entregar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.documentos_enviar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'atendente';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'comida_mesa.visualizar',
    'comida_mesa.documentos_visualizar',
    'comida_mesa.historico_visualizar'
)
WHERE n.slug = 'leitura';

COMMIT;
