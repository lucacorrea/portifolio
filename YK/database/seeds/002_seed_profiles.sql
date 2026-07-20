-- Perfis iniciais e associacoes de permissoes.
-- Seguro para reexecucao: perfis sao atualizados por nome e vinculos usam INSERT IGNORE.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO perfis
    (nome, descricao, protegido, status)
VALUES
    ('Administrador', 'Acesso completo ao sistema', 1, 'ativo'),
    ('Recepção', 'Atendimento, clientes, ordens de serviço, orçamentos e agenda', 0, 'ativo')
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    protegido = VALUES(protegido),
    status = VALUES(status);

INSERT IGNORE INTO perfil_permissoes (
    perfil_id,
    permissao_id
)
SELECT
    perfil.id,
    permissao.id
FROM perfis perfil
CROSS JOIN permissoes permissao
WHERE perfil.nome = 'Administrador'
  AND permissao.status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
   AND permissao.codigo = 'relatorio.comissao.visualizar'
   AND permissao.status = 'ativo';

INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
SELECT perfil.id, permissao.id
  FROM perfis perfil
  JOIN permissoes permissao
 WHERE perfil.nome IN ('Administrador', 'Dono')
   AND permissao.codigo = 'relatorio.meta_comissao.configurar'
   AND permissao.status = 'ativo';

DELETE perfil_permissao
  FROM perfil_permissoes perfil_permissao
  JOIN perfis perfil ON perfil.id = perfil_permissao.perfil_id
  JOIN permissoes permissao ON permissao.id = perfil_permissao.permissao_id
 WHERE permissao.codigo = 'relatorio.meta_comissao.configurar'
   AND perfil.nome NOT IN ('Administrador', 'Dono');

INSERT IGNORE INTO perfil_permissoes (
    perfil_id,
    permissao_id
)
SELECT
    perfil.id,
    permissao.id
FROM perfis perfil
INNER JOIN permissoes permissao
    ON permissao.codigo IN (
        'dashboard.visualizar',
        'dashboard.visualizar_operacional',
        'cliente.visualizar',
        'cliente.criar',
        'cliente.editar',
        'cliente.visualizar_historico',
        'os.visualizar',
        'os.criar',
        'os.editar',
        'os.agendar',
        'os.alterar_equipe',
        'os.imprimir',
        'os.visualizar_valores',
        'orcamento.visualizar',
        'orcamento.criar',
        'orcamento.editar',
        'orcamento.aprovar',
        'orcamento.recusar',
        'orcamento.converter_os',
        'orcamento.imprimir',
        'agenda.visualizar',
        'agenda.criar',
        'agenda.editar',
        'agenda.reagendar',
        'agenda.cancelar',
        'agenda.alterar_dupla',
        'agenda.criar_lembrete',
        'painel_semanal.visualizar',
        'painel_semanal.adicionar',
        'painel_semanal.editar',
        'painel_semanal.alterar_dupla',
        'painel_semanal.alterar_horario',
        'produto.visualizar',
        'produto.visualizar_preco_venda',
        'estoque.visualizar',
        'servico.visualizar',
        'funcionario.visualizar',
        'caixa.visualizar',
        'caixa.registrar_venda',
        'caixa.registrar_recebimento',
        'recibo.visualizar',
        'recibo.emitir',
        'recibo.reimprimir',
        'relatorio.operacional',
        'relatorio.imprimir'
    )
WHERE perfil.nome = 'Recepção'
  AND permissao.status = 'ativo';
