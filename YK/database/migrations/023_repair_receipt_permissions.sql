-- Migration 023 - Repara permissoes de recibo em bancos atualizados por migrations.
-- Garante que recibos pagos aparecam nos menus sem depender dos seeds de instalacao nova.

SET NAMES utf8mb4;

INSERT INTO permissoes (grupo, modulo, codigo, nome, descricao, ordem, status) VALUES
('Fiscal', 'recibo', 'recibo.visualizar', 'Visualizar recibos', 'Permite consultar o historico de recibos.', 1640, 'ativo'),
('Fiscal', 'recibo', 'recibo.emitir', 'Emitir recibos', 'Permite emitir recibos vinculados a pagamentos ou avulsos.', 1650, 'ativo'),
('Fiscal', 'recibo', 'recibo.reimprimir', 'Reimprimir recibos', 'Permite abrir e reimprimir recibos emitidos.', 1660, 'ativo'),
('Fiscal', 'recibo', 'recibo.cancelar', 'Cancelar recibos', 'Permite cancelar recibos preservando o historico.', 1670, 'ativo')
ON DUPLICATE KEY UPDATE
    grupo = VALUES(grupo), modulo = VALUES(modulo), nome = VALUES(nome),
    descricao = VALUES(descricao), ordem = VALUES(ordem), status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN (
      'recibo.visualizar', 'recibo.emitir', 'recibo.reimprimir', 'recibo.cancelar'
  )
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao ON permissao.codigo IN (
      'recibo.visualizar', 'recibo.emitir', 'recibo.reimprimir'
  )
 WHERE perfil.nome = 'Recep??o'
   AND permissao.status = 'ativo';
