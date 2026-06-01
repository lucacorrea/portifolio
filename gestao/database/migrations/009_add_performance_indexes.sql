SET NAMES utf8mb4;

ALTER TABLE produtos
    ADD INDEX idx_produtos_empresa_ativo_nome (empresa_id, ativo, nome),
    ADD INDEX idx_produtos_empresa_ativo_validade (empresa_id, ativo, validade),
    ADD INDEX idx_produtos_empresa_codigo (empresa_id, codigo_barras);

ALTER TABLE vendas
    ADD INDEX idx_vendas_empresa_status_data (empresa_id, status, criado_em);

ALTER TABLE pagamentos
    ADD INDEX idx_pagamentos_metodo (metodo),
    ADD INDEX idx_pagamentos_venda_metodo (venda_id, metodo);

ALTER TABLE clientes
    ADD INDEX idx_clientes_empresa_nome (empresa_id, nome),
    ADD INDEX idx_clientes_empresa_telefone (empresa_id, telefone);

ALTER TABLE cliente_contas
    ADD INDEX idx_contas_cliente_status_vencimento (cliente_id, status, vencimento),
    ADD INDEX idx_contas_empresa_status_vencimento (empresa_id, status, vencimento);
