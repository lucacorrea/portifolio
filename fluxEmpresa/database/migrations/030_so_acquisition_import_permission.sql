/* =====================================================================
 * MIGRATION 030 — PERMISSÃO DE IMPORTAÇÃO DE AQUISIÇÃO DO SO
 * ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO permissoes (
    grupo,
    modulo,
    codigo,
    nome,
    descricao,
    ordem,
    status
) VALUES (
    'Integrações',
    'so_aquisicao',
    'so_aquisicao.importar',
    'Importar aquisição do SO',
    'Permite converter uma aquisição do SO em ordem de serviço da empresa atendida.',
    2310,
    'ativo'
)
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = 'ativo';
