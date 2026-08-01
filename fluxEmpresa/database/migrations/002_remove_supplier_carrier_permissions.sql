START TRANSACTION;

DELETE pp
FROM perfil_permissoes pp
INNER JOIN permissoes p
    ON p.id = pp.permissao_id
WHERE p.modulo IN ('fornecedor', 'transportadora');

DELETE FROM permissoes
WHERE modulo IN ('fornecedor', 'transportadora');

DELETE pp
FROM perfil_permissoes pp
INNER JOIN permissoes p
    ON p.id = pp.permissao_id
WHERE p.codigo IN (
    'funcionario.desativar',
    'funcionario.visualizar_produtividade',
    'funcionario.visualizar_comissao'
);

DELETE FROM permissoes
WHERE codigo IN (
    'funcionario.desativar',
    'funcionario.visualizar_produtividade',
    'funcionario.visualizar_comissao'
);

COMMIT;
