<?php
namespace App\Models;

class Sale extends BaseModel {
    protected $table = 'vendas';

    public function create($data) {
        $hasAvulso      = $this->columnExists('nome_cliente_avulso');
        $hasTipoNota    = $this->columnExists('tipo_nota');
        $hasValorRecebido = $this->columnExists('valor_recebido');
        $hasCpfCliente  = $this->columnExists('cpf_cliente');
        $hasClienteNome = $this->columnExists('cliente_nome');
        $hasTaxaCartao  = $this->columnExists('taxa_cartao');

        $cols   = ['cliente_id', 'usuario_id', 'filial_id', 'valor_total', 'desconto_total', 'autorizado_por', 'forma_pagamento', 'status'];
        $params = [
            $data['cliente_id'] ?? null,
            $data['usuario_id'],
            $data['filial_id'],
            $data['valor_total'],
            $data['desconto_total'] ?? 0,
            $data['autorizado_por'] ?? null,
            $data['forma_pagamento'],
            'concluido'
        ];

        if ($hasAvulso) {
            array_splice($cols, 1, 0, ['nome_cliente_avulso']);
            array_splice($params, 1, 0, [$data['nome_cliente_avulso'] ?? null]);
        }

        if ($hasCpfCliente) {
            $cols[] = 'cpf_cliente';
            $params[] = $data['cpf_cliente'] ?? null;
        }

        if ($hasClienteNome) {
            $cols[] = 'cliente_nome';
            $params[] = $data['cliente_nome'] ?? null;
        }

        if ($hasTipoNota) {
            $cols[]   = 'tipo_nota';
            $params[] = $data['tipo_nota'] ?? 'nao_fiscal';
        }

        if ($hasValorRecebido) {
            $cols[]   = 'valor_recebido';
            $params[] = isset($data['valor_recebido']) ? (float)$data['valor_recebido'] : null;
            $cols[]   = 'troco';
            $params[] = isset($data['troco']) ? (float)$data['troco'] : null;
        }

        if ($hasTaxaCartao) {
            $cols[]   = 'taxa_cartao';
            $params[] = isset($data['taxa_cartao']) ? (float)$data['taxa_cartao'] : 0.00;
        }

        // Camada 2: Sync tracking quando rodando no servidor local
        if (defined('IS_LOCAL_SERVER') && IS_LOCAL_SERVER) {
            if ($this->columnExists('sync_origin') && !isset($data['sync_origin'])) {
                $cols[] = 'sync_origin';
                $params[] = 'local';
            }
            if ($this->columnExists('sync_id') && !isset($data['sync_id'])) {
                $cols[] = 'sync_id';
                $params[] = 'L-vendas-' . time() . '-' . bin2hex(random_bytes(4));
            }
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList      = implode(', ', $cols);

        $sql = "INSERT INTO {$this->table} ($colList) VALUES ($placeholders)";
        $this->query($sql, $params);
        return $this->db->lastInsertId();
    }

    public function getRecent($limit = 10) {
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE v.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';

        return $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            $where
            ORDER BY v.data_venda DESC LIMIT $limit
        ", $params)->fetchAll();
    }

    public function getRecentPaginated($page = 1, $perPage = 4) {
        $offset = ($page - 1) * $perPage;
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE v.filial_id = ?" : "";
        $params = $filialId ? [$filialId] : [];
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';

        return $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome 
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            $where
            ORDER BY v.data_venda DESC LIMIT $perPage OFFSET $offset
        ", $params)->fetchAll();
    }

    public function getTotalCount() {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            return $this->query("SELECT COUNT(*) FROM {$this->table} WHERE filial_id = ?", [$filialId])->fetchColumn();
        }
        return $this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    public function findById($id) {
        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';
        $sale = $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome,
                   (SELECT status_sefaz FROM nfce_emitidas WHERE venda_id = v.id ORDER BY id DESC LIMIT 1) as nf_status,
                   (SELECT chave FROM nfce_emitidas WHERE venda_id = v.id ORDER BY id DESC LIMIT 1) as chave_acesso
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE v.id = ?
        ", [$id])->fetch();

        if ($sale) {
            $sale['itens'] = $this->query("
                SELECT i.*, p.nome as produto_nome, p.unidade 
                FROM vendas_itens i 
                JOIN produtos p ON i.produto_id = p.id 
                WHERE i.venda_id = ?
            ", [$id])->fetchAll();
        }
        return $sale;
    }

    public function updateStatus($id, $status) {
        return $this->query("UPDATE {$this->table} SET status = ? WHERE id = ?", [$status, $id]);
    }

    public function getFiltered($filters = [], $page = 1, $perPage = 9) {
        $offset = ($page - 1) * $perPage;
        $filialId = $this->getFilialContext();
        
        $where = "WHERE 1=1";
        $params = [];

        if ($filialId) {
            $where .= " AND v.filial_id = ?";
            $params[] = $filialId;
        }

        if (!empty($filters['status'])) {
            $where .= " AND v.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['tipo_nota'])) {
            $where .= " AND v.tipo_nota = ?";
            $params[] = $filters['tipo_nota'];
        }

        if (!empty($filters['forma_pagamento'])) {
            $where .= " AND v.forma_pagamento = ?";
            $params[] = $filters['forma_pagamento'];
        }

        if (!empty($filters['data_inicio'])) {
            $where .= " AND DATE(v.data_venda) >= ?";
            $params[] = $filters['data_inicio'];
        }

        if (!empty($filters['data_fim'])) {
            $where .= " AND DATE(v.data_venda) <= ?";
            $params[] = $filters['data_fim'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (v.id = ? OR c.nome LIKE ? OR v.nome_cliente_avulso LIKE ? OR v.cpf_cliente LIKE ?)";
            $params[] = $filters['search'];
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $nameField = $this->columnExists('nome_cliente_avulso') ? 'v.nome_cliente_avulso' : 'NULL';

        return $this->query("
            SELECT v.*, IFNULL(c.nome, $nameField) as cliente_nome, u.nome as vendedor_nome,
                   (SELECT chave FROM nfce_emitidas WHERE venda_id = v.id ORDER BY id DESC LIMIT 1) as chave_acesso
            FROM {$this->table} v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            $where
            ORDER BY v.data_venda DESC LIMIT $perPage OFFSET $offset
        ", $params)->fetchAll();
    }

    public function getTotalFiltered($filters = []) {
        $filialId = $this->getFilialContext();
        $where = "WHERE 1=1";
        $params = [];

        if ($filialId) {
            $where .= " AND v.filial_id = ?";
            $params[] = $filialId;
        }
        
        // Add same filters as getFiltered
        if (!empty($filters['status'])) {
            $where .= " AND v.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['tipo_nota'])) {
            $where .= " AND v.tipo_nota = ?";
            $params[] = $filters['tipo_nota'];
        }
        if (!empty($filters['forma_pagamento'])) {
            $where .= " AND v.forma_pagamento = ?";
            $params[] = $filters['forma_pagamento'];
        }
        if (!empty($filters['data_inicio'])) {
            $where .= " AND DATE(v.data_venda) >= ?";
            $params[] = $filters['data_inicio'];
        }
        if (!empty($filters['data_fim'])) {
            $where .= " AND DATE(v.data_venda) <= ?";
            $params[] = $filters['data_fim'];
        }
        if (!empty($filters['search'])) {
            $where .= " AND (v.id = ? OR c.nome LIKE ? OR v.nome_cliente_avulso LIKE ? OR v.cpf_cliente LIKE ?)";
            $params[] = $filters['search'];
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $join = "LEFT JOIN clientes c ON v.cliente_id = c.id";

        return $this->query("SELECT COUNT(*) FROM {$this->table} v $join $where", $params)->fetchColumn();
    }
}
