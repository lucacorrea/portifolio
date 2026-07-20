-- Migration 014 - Meta mensal global de comissao com historico de configuracoes.
-- Reentrante para aplicacao automatica depois de 013_create_suppliers_accounts_payable.sql.
-- Compatibilidade alvo: MariaDB 10.4, InnoDB, utf8mb4.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS metas_comissao_mensais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competencia DATE NOT NULL,
    versao INT UNSIGNED NOT NULL,
    valor_meta DECIMAL(12,2) NOT NULL,
    percentual_comissao DECIMAL(5,2) NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criada_por INT UNSIGNED NOT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    desativada_por INT UNSIGNED NULL,
    desativada_em DATETIME NULL,
    configuracao_ativa_chave DATE
        GENERATED ALWAYS AS (CASE WHEN ativa = 1 THEN competencia ELSE NULL END) PERSISTENT,
    UNIQUE KEY uq_meta_comissao_competencia_versao (competencia, versao),
    UNIQUE KEY uq_meta_comissao_competencia_ativa (configuracao_ativa_chave),
    KEY idx_meta_comissao_competencia (competencia, ativa),
    CONSTRAINT chk_meta_comissao_competencia_inicio_mes
        CHECK (DAYOFMONTH(competencia) = 1),
    CONSTRAINT chk_meta_comissao_valor_positivo CHECK (valor_meta > 0),
    CONSTRAINT chk_meta_comissao_percentual CHECK (percentual_comissao > 0 AND percentual_comissao <= 100),
    CONSTRAINT chk_meta_comissao_desativacao
        CHECK ((ativa = 1 AND desativada_por IS NULL AND desativada_em IS NULL)
            OR (ativa = 0 AND desativada_em IS NOT NULL)),
    CONSTRAINT fk_meta_comissao_criada_usuario FOREIGN KEY (criada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_meta_comissao_desativada_usuario FOREIGN KEY (desativada_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem) VALUES
('Relatorios', 'relatorio', 'relatorio.comissao.visualizar', 'Visualizar comissoes', 'Permite visualizar metas, producao e comissoes dos funcionarios.', 1855),
('Relatorios', 'relatorio', 'relatorio.meta_comissao.configurar', 'Configurar meta de comissao', 'Permite criar uma nova versao da meta e do percentual mensal de comissao.', 1856);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo = 'relatorio.comissao.visualizar';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo = 'relatorio.meta_comissao.configurar';

DELETE perfil_permissao
  FROM perfil_permissoes perfil_permissao
  JOIN perfis perfil ON perfil.id = perfil_permissao.perfil_id
  JOIN permissoes permissao ON permissao.id = perfil_permissao.permissao_id
 WHERE permissao.codigo = 'relatorio.meta_comissao.configurar'
   AND perfil.nome NOT IN ('Administrador', 'Dono');
