<?php
namespace App\Models;

class Product extends BaseModel {
    protected $table = 'produtos';

    public function all($order = "nome ASC") {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            return $this->query("SELECT * FROM {$this->table} WHERE filial_id = ? ORDER BY $order", [$filialId])->fetchAll();
        }
        return $this->query("SELECT * FROM {$this->table} ORDER BY $order")->fetchAll();
    }

    public function getCategories() {
        return ['Fios e Cabos', 'Iluminação', 'Disjuntores', 'Tomadas e Interruptores', 'Eletrodutos', 'Ferramentas', 'Outros'];
    }

    public function getCriticalStock($filialId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE quantidade <= estoque_minimo AND estoque_minimo > 0";
        $params = [];
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        return $this->query($sql, $params)->fetchAll();
    }

    public function getLowStock($filialId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE quantidade > estoque_minimo AND quantidade <= (estoque_minimo * 1.5) AND estoque_minimo > 0";
        $params = [];
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        return $this->query($sql, $params)->fetchAll();
    }

    public function getOkStock($filialId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE (quantidade > (estoque_minimo * 1.5) OR estoque_minimo = 0)";
        $params = [];
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        return $this->query($sql, $params)->fetchAll();
    }

    public function getStockStats($filialId = null) {
        $where = " WHERE 1=1";
        $params = [];
        if ($filialId) {
            $where .= " AND filial_id = ?";
            $params[] = $filialId;
        }

        $critical = $this->query("SELECT COUNT(*) FROM {$this->table} $where AND quantidade <= estoque_minimo AND estoque_minimo > 0", $params)->fetchColumn();
        $low      = $this->query("SELECT COUNT(*) FROM {$this->table} $where AND quantidade > estoque_minimo AND quantidade <= (estoque_minimo * 1.5) AND estoque_minimo > 0", $params)->fetchColumn();
        $ok       = $this->query("SELECT COUNT(*) FROM {$this->table} $where AND (quantidade > (estoque_minimo * 1.5) OR estoque_minimo = 0)", $params)->fetchColumn();

        return [
            'critical' => (int)$critical,
            'low'      => (int)$low,
            'ok'       => (int)$ok,
            'total'    => (int)($critical + $low + $ok)
        ];
    }

    public function searchStockAlarms($filters, $filialId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $sql .= " AND categoria = ?";
            $params[] = $filters['categoria'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'CRITICO') {
                $sql .= " AND quantidade <= estoque_minimo AND estoque_minimo > 0";
            } elseif ($filters['status'] === 'BAIXO') {
                $sql .= " AND quantidade > estoque_minimo AND quantidade <= (estoque_minimo * 1.5) AND estoque_minimo > 0";
            } elseif ($filters['status'] === 'OK') {
                $sql .= " AND (quantidade > (estoque_minimo * 1.5) OR estoque_minimo = 0)";
            }
        }

        $sql .= " ORDER BY (quantidade - estoque_minimo) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function paginateStockAlarms($filters, $page = 1, $perPage = 15, $filialId = null) {
        $where = " WHERE 1=1";
        $params = [];

        if ($filialId) {
            $where .= " AND filial_id = ?";
            $params[] = $filialId;
        }

        if (!empty($filters['q'])) {
            $where .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $where .= " AND categoria = ?";
            $params[] = $filters['categoria'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'CRITICO') {
                $where .= " AND quantidade <= estoque_minimo AND estoque_minimo > 0";
            } elseif ($filters['status'] === 'BAIXO') {
                $where .= " AND quantidade > estoque_minimo AND quantidade <= (estoque_minimo * 1.5) AND estoque_minimo > 0";
            } elseif ($filters['status'] === 'OK') {
                $where .= " AND (quantidade > (estoque_minimo * 1.5) OR estoque_minimo = 0)";
            }
        }

        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} $where");
        $totalStmt->execute($params);
        $total = $totalStmt->fetchColumn();

        $pages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table} $where ORDER BY (quantidade - estoque_minimo) ASC LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'total' => $total,
            'pages' => $pages,
            'current' => $page,
            'per_page' => $perPage
        ];
    }

    public function updateStock($id, $qty, $type = 'entrada') {
        $operator = ($type == 'entrada') ? '+' : '-';
        return $this->query("UPDATE {$this->table} SET quantidade = quantidade $operator ? WHERE id = ?", [$qty, $id]);
    }

    public function hasEnoughStock($id, $requiredQty) {
        $stmt = $this->db->prepare("SELECT quantidade FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $currentQty = $stmt->fetchColumn();
        return ($currentQty !== false && $currentQty >= $requiredQty);
    }

    public function save($data) {
        // Detect which optional columns exist (forward/backward compat)
        $hasCean          = $this->columnExists('cean');
        $hasCest          = $this->columnExists('cest');
        $hasOrigem        = $this->columnExists('origem');
        $hasCsosn         = $this->columnExists('csosn');
        $hasCfopInterno   = $this->columnExists('cfop_interno');
        $hasCfopExterno   = $this->columnExists('cfop_externo');
        $hasAliquota      = $this->columnExists('aliquota_icms');
        $hasPeso          = $this->columnExists('peso');
        $hasDimensoes     = $this->columnExists('dimensoes');
        $hasTipoProduto   = $this->columnExists('tipo_produto');
        $hasPrecoVenda2   = $this->columnExists('preco_venda_2');
        $hasPrecoVenda3   = $this->columnExists('preco_venda_3');
        $hasPrecoAtacado  = $this->columnExists('preco_venda_atacado');
        $hasImagens       = $this->columnExists('imagens');
        
        if (!$this->columnExists('fornecedor_id')) {
            try {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN fornecedor_id INT NULL");
            } catch (\Exception $e) {}
        }
        $hasFornecedor    = true;

        if (!empty($data['id'])) {
            // --- UPDATE ---
            $sets   = ['codigo = ?', 'ncm = ?', 'nome = ?', 'unidade = ?', 'categoria = ?',
                       'preco_custo = ?', 'preco_venda = ?', 'estoque_minimo = ?'];
            $params = [
                $data['codigo'], $data['ncm'] ?? null, $data['nome'], $data['unidade'],
                $data['categoria'], $data['preco_custo'], $data['preco_venda'], $data['estoque_minimo'],
            ];

            if ($hasCean)        { $sets[] = 'cean = ?';          $params[] = $data['cean'] ?? 'SEM GTIN'; }
            if ($hasCest)        { $sets[] = 'cest = ?';          $params[] = $data['cest'] ?? null; }
            if ($hasOrigem)      { $sets[] = 'origem = ?';        $params[] = $data['origem'] ?? 0; }
            if ($hasCsosn)       { $sets[] = 'csosn = ?';         $params[] = $data['csosn'] ?? '102'; }
            if ($hasCfopInterno) { $sets[] = 'cfop_interno = ?';  $params[] = $data['cfop_interno'] ?? '5102'; }
            if ($hasCfopExterno) { $sets[] = 'cfop_externo = ?';  $params[] = $data['cfop_externo'] ?? '6102'; }
            if ($hasAliquota)    { $sets[] = 'aliquota_icms = ?'; $params[] = $data['aliquota_icms'] ?? 0; }
            if ($hasPeso)        { $sets[] = 'peso = ?';          $params[] = $data['peso'] ?? null; }
            if ($hasDimensoes)   { $sets[] = 'dimensoes = ?';     $params[] = $data['dimensoes'] ?? null; }
            if ($hasTipoProduto) { $sets[] = 'tipo_produto = ?';  $params[] = $data['tipo_produto'] ?? 'simples'; }
            if ($hasPrecoVenda2) { $sets[] = 'preco_venda_2 = ?'; $params[] = ($data['preco_venda_2'] ?? '') === '' ? null : $data['preco_venda_2']; }
            if ($hasPrecoVenda3) { $sets[] = 'preco_venda_3 = ?'; $params[] = ($data['preco_venda_3'] ?? '') === '' ? null : $data['preco_venda_3']; }
            if ($hasPrecoAtacado){ $sets[] = 'preco_venda_atacado = ?'; $params[] = ($data['preco_venda_atacado'] ?? '') === '' ? null : $data['preco_venda_atacado']; }
            if ($hasImagens && isset($data['imagens'])) { $sets[] = 'imagens = ?'; $params[] = $data['imagens']; }
            if ($hasFornecedor)  { $sets[] = 'fornecedor_id = ?'; $params[] = ($data['fornecedor_id'] ?? '') === '' ? null : $data['fornecedor_id']; }
            if ($this->columnExists('descricao')) { $sets[] = 'descricao = ?'; $params[] = $data['descricao'] ?? null; }

            $params[] = $data['id'];
            return $this->query("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params);

        } else {
            // --- INSERT ---
            $cols   = ['codigo', 'ncm', 'nome', 'unidade', 'categoria',
                       'preco_custo', 'preco_venda', 'quantidade', 'estoque_minimo', 'filial_id'];
            $params = [
                $data['codigo'], $data['ncm'] ?? null, $data['nome'], $data['unidade'],
                $data['categoria'], $data['preco_custo'], $data['preco_venda'],
                $data['quantidade'] ?? 0, $data['estoque_minimo'], $data['filial_id'] ?? 1,
            ];

            if ($hasCean)        { $cols[] = 'cean';          $params[] = $data['cean'] ?? 'SEM GTIN'; }
            if ($hasCest)        { $cols[] = 'cest';          $params[] = $data['cest'] ?? null; }
            if ($hasOrigem)      { $cols[] = 'origem';        $params[] = $data['origem'] ?? 0; }
            if ($hasCsosn)       { $cols[] = 'csosn';         $params[] = $data['csosn'] ?? '102'; }
            if ($hasCfopInterno) { $cols[] = 'cfop_interno';  $params[] = $data['cfop_interno'] ?? '5102'; }
            if ($hasCfopExterno) { $cols[] = 'cfop_externo';  $params[] = $data['cfop_externo'] ?? '6102'; }
            if ($hasAliquota)    { $cols[] = 'aliquota_icms'; $params[] = $data['aliquota_icms'] ?? 0; }
            if ($hasPeso)        { $cols[] = 'peso';          $params[] = $data['peso'] ?? null; }
            if ($hasDimensoes)   { $cols[] = 'dimensoes';     $params[] = $data['dimensoes'] ?? null; }
            if ($hasTipoProduto) { $cols[] = 'tipo_produto';  $params[] = $data['tipo_produto'] ?? 'simples'; }
            if ($hasPrecoVenda2) { $cols[] = 'preco_venda_2'; $params[] = ($data['preco_venda_2'] ?? '') === '' ? null : $data['preco_venda_2']; }
            if ($hasPrecoVenda3) { $cols[] = 'preco_venda_3'; $params[] = ($data['preco_venda_3'] ?? '') === '' ? null : $data['preco_venda_3']; }
            if ($hasPrecoAtacado){ $cols[] = 'preco_venda_atacado'; $params[] = ($data['preco_venda_atacado'] ?? '') === '' ? null : $data['preco_venda_atacado']; }
            if ($hasImagens)     { $cols[] = 'imagens';       $params[] = $data['imagens'] ?? null; }
            if ($hasFornecedor)  { $cols[] = 'fornecedor_id'; $params[] = ($data['fornecedor_id'] ?? '') === '' ? null : $data['fornecedor_id']; }
            if ($this->columnExists('descricao')) { $cols[] = 'descricao'; $params[] = $data['descricao'] ?? null; }

            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colList      = implode(', ', $cols);

            return $this->query("INSERT INTO {$this->table} ($colList) VALUES ($placeholders)", $params);
        }
    }
}
