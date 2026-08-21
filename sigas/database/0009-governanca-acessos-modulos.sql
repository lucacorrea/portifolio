-- SIGAS Coari — Governança de acessos por setor, usuário e catálogo de cargos
-- Executar uma vez após backup. Não remove tabelas nem dados existentes.

CREATE TABLE IF NOT EXISTS cargos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cargos_slug (slug),
    KEY idx_cargos_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS setor_modulos (
    setor_id BIGINT UNSIGNED NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    permitido TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setor_id, modulo),
    KEY idx_setor_modulos_modulo (modulo),
    CONSTRAINT fk_setor_modulos_setor FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_setor_modulos_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_modulo_excecoes (
    usuario_id BIGINT UNSIGNED NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    permitido TINYINT(1) NOT NULL,
    motivo VARCHAR(255) NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, modulo),
    KEY idx_usuario_modulo_excecoes_modulo (modulo),
    CONSTRAINT fk_usuario_modulo_excecoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_modulo_excecoes_operador FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cargos (nome, slug, descricao, ativo) VALUES
('Assistente Social', 'assistente-social', 'Profissional responsável por acompanhamento e parecer social.', 1),
('Psicólogo(a)', 'psicologo', 'Profissional técnico da rede socioassistencial.', 1),
('Atendente', 'atendente', 'Atendimento, recepção e cadastro básico.', 1),
('Coordenador(a)', 'coordenador', 'Coordenação de unidade, setor ou programa.', 1),
('Gerente', 'gerente', 'Gestão administrativa e operacional.', 1),
('Técnico(a)', 'tecnico', 'Atuação técnica conforme atribuições do setor.', 1),
('Administrador do Sistema', 'administrador-sistema', 'Governança e administração técnica do SIGAS.', 1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), ativo=VALUES(ativo);

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
('Visualizar governança de acessos', 'governanca.visualizar', 'Acessar o módulo de governança e acessos.', 'governanca', 1),
('Gerenciar setores', 'governanca.setores', 'Criar e alterar setores.', 'governanca', 1),
('Gerenciar cargos', 'governanca.cargos', 'Criar e alterar catálogo de cargos.', 'governanca', 1),
('Gerenciar perfis', 'governanca.perfis', 'Criar e alterar níveis/perfis de acesso.', 'governanca', 1),
('Gerenciar permissões', 'governanca.permissoes', 'Alterar matriz de permissões.', 'governanca', 1),
('Gerenciar módulos por setor', 'governanca.modulos', 'Definir módulos visíveis por setor.', 'governanca', 1),
('Visualizar Kit Maternidade', 'kit_maternidade.visualizar', 'Acessar o módulo Kit Maternidade.', 'kit_maternidade', 1),
('Cadastrar Kit Maternidade', 'kit_maternidade.cadastrar', 'Cadastrar e vincular gestantes ao programa.', 'kit_maternidade', 1),
('Registrar acompanhamento Kit Maternidade', 'kit_maternidade.acompanhar', 'Registrar visitas, reuniões e acompanhamento.', 'kit_maternidade', 1),
('Avaliar Kit Maternidade', 'kit_maternidade.avaliar', 'Emitir avaliação e decisão de contemplação.', 'kit_maternidade', 1),
('Entregar Kit Maternidade', 'kit_maternidade.entregar', 'Registrar entrega de kit.', 'kit_maternidade', 1),
('Visualizar Aluguel Social', 'aluguel_social.visualizar', 'Acessar o módulo Aluguel Social.', 'aluguel_social', 1),
('Gerenciar Aluguel Social', 'aluguel_social.gerenciar', 'Gerenciar solicitação, vistoria, parecer e concessão.', 'aluguel_social', 1),
('Gerenciar pagamentos Aluguel Social', 'aluguel_social.pagamentos', 'Gerenciar competências e pagamentos.', 'aluguel_social', 1),
('Visualizar Benefícios Eventuais', 'beneficios_eventuais.visualizar', 'Acessar o módulo Benefícios Eventuais.', 'beneficios_eventuais', 1),
('Gerenciar Benefícios Eventuais', 'beneficios_eventuais.gerenciar', 'Gerenciar solicitações, análises e concessões.', 'beneficios_eventuais', 1),
('Entregar Benefícios Eventuais', 'beneficios_eventuais.entregar', 'Registrar entrega ou concessão.', 'beneficios_eventuais', 1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), modulo=VALUES(modulo), ativo=VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id FROM niveis_acesso n CROSS JOIN permissoes p
WHERE n.slug IN ('administrador','suporte') AND p.ativo=1;

-- Matriz inicial por setor. Depois pode ser ajustada pelo módulo Governança e Acessos.
INSERT INTO setor_modulos (setor_id, modulo, permitido)
SELECT s.id, m.modulo, 1
FROM setores s
JOIN (
    SELECT 'planejamento-gestao' modulo UNION ALL
    SELECT 'vigilancia-socioassistencial' UNION ALL
    SELECT 'protecao-social-basica' UNION ALL
    SELECT 'protecao-social-especial' UNION ALL
    SELECT 'kit-maternidade' UNION ALL
    SELECT 'aluguel-social' UNION ALL
    SELECT 'beneficios-eventuais' UNION ALL
    SELECT 'comida-mesa' UNION ALL
    SELECT 'primeiro-emprego'
) m
WHERE s.slug IN ('semas-sede','ti-suporte','administracao-sistema')
ON DUPLICATE KEY UPDATE permitido=VALUES(permitido);

INSERT INTO setor_modulos (setor_id, modulo, permitido)
SELECT s.id, m.modulo, 1
FROM setores s
JOIN (
    SELECT 'protecao-social-basica' modulo UNION ALL
    SELECT 'kit-maternidade' UNION ALL
    SELECT 'aluguel-social' UNION ALL
    SELECT 'beneficios-eventuais' UNION ALL
    SELECT 'comida-mesa'
) m
WHERE s.slug IN ('cras-1','cras-2')
ON DUPLICATE KEY UPDATE permitido=VALUES(permitido);

INSERT INTO setor_modulos (setor_id, modulo, permitido)
SELECT s.id, 'protecao-social-especial', 1
FROM setores s WHERE s.slug='creas'
ON DUPLICATE KEY UPDATE permitido=VALUES(permitido);

INSERT INTO setor_modulos (setor_id, modulo, permitido)
SELECT s.id, 'protecao-social-basica', 1
FROM setores s WHERE s.slug='casa-cidadao'
ON DUPLICATE KEY UPDATE permitido=VALUES(permitido);

-- Perfis operacionais recebem apenas as permissões necessárias aos novos programas.
INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n JOIN permissoes p ON p.slug IN (
    'kit_maternidade.visualizar','kit_maternidade.cadastrar','kit_maternidade.acompanhar','kit_maternidade.avaliar','kit_maternidade.entregar',
    'aluguel_social.visualizar','aluguel_social.gerenciar','aluguel_social.pagamentos',
    'beneficios_eventuais.visualizar','beneficios_eventuais.gerenciar','beneficios_eventuais.entregar'
)
WHERE n.slug='gestor';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n JOIN permissoes p ON p.slug IN (
    'kit_maternidade.visualizar','kit_maternidade.cadastrar','kit_maternidade.acompanhar','kit_maternidade.avaliar','kit_maternidade.entregar',
    'aluguel_social.visualizar','aluguel_social.gerenciar',
    'beneficios_eventuais.visualizar','beneficios_eventuais.gerenciar','beneficios_eventuais.entregar'
)
WHERE n.slug='tecnico';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n JOIN permissoes p ON p.slug IN (
    'kit_maternidade.visualizar','kit_maternidade.cadastrar',
    'aluguel_social.visualizar',
    'beneficios_eventuais.visualizar'
)
WHERE n.slug='atendente';

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n JOIN permissoes p ON p.slug IN (
    'kit_maternidade.visualizar','aluguel_social.visualizar','beneficios_eventuais.visualizar'
)
WHERE n.slug='leitura';
