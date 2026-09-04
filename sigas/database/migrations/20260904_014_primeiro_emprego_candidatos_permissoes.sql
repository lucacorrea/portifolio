SET NAMES utf8mb4;

START TRANSACTION;

-- Os slugs da tentativa anterior permanecem temporariamente para permitir
-- aplicar esta matriz antes do deploy do código sem interromper o sistema.
-- A nova camada usa exclusivamente os slugs candidatos.* definidos abaixo.

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
('Consultar candidatos', 'primeiro_emprego.candidatos.visualizar', 'Permite consultar a lista e os dados básicos dos candidatos.', 'primeiro_emprego', 1),
('Cadastrar candidato', 'primeiro_emprego.candidatos.cadastrar', 'Permite cadastrar candidato pela triagem social.', 'primeiro_emprego', 1),
('Revisar cadastro de candidato', 'primeiro_emprego.candidatos.revisar', 'Permite corrigir ou confirmar CPF, telefone e data de nascimento.', 'primeiro_emprego', 1),
('Consultar visitas de candidato', 'primeiro_emprego.candidatos.visitas_visualizar', 'Permite consultar o histórico de visitas e pareceres sociais.', 'primeiro_emprego', 1),
('Registrar visita de candidato', 'primeiro_emprego.candidatos.visitas_registrar', 'Permite registrar visita social, parecer técnico e decisão.', 'primeiro_emprego', 1),
('Consultar ficha de candidato', 'primeiro_emprego.candidatos.ficha_visualizar', 'Permite consultar a ficha complementar do candidato.', 'primeiro_emprego', 1),
('Atualizar ficha de candidato', 'primeiro_emprego.candidatos.ficha_atualizar', 'Permite atualizar escolaridade, atuação e foto do candidato.', 'primeiro_emprego', 1),
('Importar candidatos', 'primeiro_emprego.candidatos.importar', 'Permite validar e executar as importações da base de candidatos.', 'primeiro_emprego', 1),
('Excluir candidato', 'primeiro_emprego.candidatos.excluir', 'Permite excluir candidato e seus vínculos relacionados.', 'primeiro_emprego', 1)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

DELETE np
FROM nivel_permissoes np
INNER JOIN permissoes p ON p.id = np.permissao_id
WHERE p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.cadastrar',
    'primeiro_emprego.candidatos.revisar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.visitas_registrar',
    'primeiro_emprego.candidatos.ficha_visualizar',
    'primeiro_emprego.candidatos.ficha_atualizar',
    'primeiro_emprego.candidatos.importar',
    'primeiro_emprego.candidatos.excluir'
);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.cadastrar',
    'primeiro_emprego.candidatos.revisar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.visitas_registrar',
    'primeiro_emprego.candidatos.ficha_visualizar',
    'primeiro_emprego.candidatos.ficha_atualizar',
    'primeiro_emprego.candidatos.importar',
    'primeiro_emprego.candidatos.excluir'
) AND p.ativo = 1
WHERE n.slug IN ('administrador', 'suporte')
  AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.cadastrar',
    'primeiro_emprego.candidatos.revisar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.visitas_registrar',
    'primeiro_emprego.candidatos.ficha_visualizar',
    'primeiro_emprego.candidatos.ficha_atualizar',
    'primeiro_emprego.candidatos.importar'
)
WHERE n.slug = 'gestor' AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.cadastrar',
    'primeiro_emprego.candidatos.revisar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.visitas_registrar',
    'primeiro_emprego.candidatos.ficha_visualizar',
    'primeiro_emprego.candidatos.ficha_atualizar'
)
WHERE n.slug = 'tecnico' AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.cadastrar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.ficha_visualizar',
    'primeiro_emprego.candidatos.ficha_atualizar'
)
WHERE n.slug = 'atendente' AND n.ativo = 1;

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug IN (
    'primeiro_emprego.candidatos.visualizar',
    'primeiro_emprego.candidatos.visitas_visualizar',
    'primeiro_emprego.candidatos.ficha_visualizar'
)
WHERE n.slug = 'leitura' AND n.ativo = 1;

COMMIT;
