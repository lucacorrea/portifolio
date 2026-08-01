-- Permissão dedicada para importação em lote de clientes pelo relatório PDF do A7.
-- Seguro para reexecução e concedido inicialmente apenas ao perfil Administrador.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO permissoes
    (grupo, modulo, codigo, nome, descricao, ordem, status)
VALUES
    ('Clientes', 'cliente', 'cliente.importar', 'Importar clientes',
     'Permite importar clientes em lote a partir do relatório PDF do A7.', 125, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo),
    modulo = VALUES(modulo),
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    ordem = VALUES(ordem),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
FROM perfis perfil
INNER JOIN permissoes permissao ON permissao.codigo = 'cliente.importar'
WHERE perfil.nome = 'Administrador';
