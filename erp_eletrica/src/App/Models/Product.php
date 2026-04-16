<?php
namespace App\Models;

class Product extends BaseModel {
    protected $table = 'produtos';

    public function all($order = "nome ASC") {
        $filialId = $_SESSION['filial_id'] ?? 1;
        // Só a Matriz (ID 1) vê o catálogo global completo. Filiais veem apenas seu estoque local.
        $join = ((int)$filialId === 1) ? "LEFT JOIN" : "INNER JOIN";

        $sql = "SELECT p.*, p.id as id, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo
                FROM {$this->table} p
                $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                ORDER BY p.$order";
        
        return $this->query($sql, [$filialId])->fetchAll();
    }

    public function paginate($perPage = 15, $currentPage = 1, $order = "id DESC", $filters = []) {
        $filialId = $_SESSION['filial_id'] ?? 1;
        $join = ((int)$filialId === 1) ? "LEFT JOIN" : "INNER JOIN";
        $offset = ($currentPage - 1) * $perPage;
        
        $where = " WHERE 1=1";
        $paramsCount = [$filialId];
        $paramsQuery = [$filialId];
        
        if (!empty($filters['q'])) {
            $where .= " AND (p.nome LIKE ? OR p.codigo LIKE ?)";
            $paramsCount[] = "%{$filters['q']}%";
            $paramsCount[] = "%{$filters['q']}%";
            $paramsQuery[] = "%{$filters['q']}%";
            $paramsQuery[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $where .= " AND p.categoria = ?";
            $paramsCount[] = $filters['categoria'];
            $paramsQuery[] = $filters['categoria'];
        }
        
        // Ensure count uses the same JOIN context
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} p $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ? $where");
        $totalStmt->execute($paramsCount);
        $total = $totalStmt->fetchColumn();
        
        $pages = ceil($total / $perPage);
        
        // Handle $order carefully - it might contain multiple columns
        $orderParts = explode(',', $order);
        foreach ($orderParts as &$part) {
            $part = trim($part);
            if (!str_contains($part, '.')) {
                $part = "p." . $part;
            }
        }
        $finalOrder = implode(', ', $orderParts);

        $sql = "SELECT p.*, p.id as id, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo
                FROM {$this->table} p
                $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                $where 
                ORDER BY $finalOrder LIMIT $perPage OFFSET $offset";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsQuery);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => $pages,
            'current' => $currentPage,
            'per_page' => $perPage
        ];
    }

    public function getCategories() {
        return ['Fios e Cabos', 'Iluminação', 'Disjuntores', 'Tomadas e Interruptores', 'Eletrodutos', 'Ferramentas', 'Outros'];
    }

    public function getCriticalStock($filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;
        
        $sql = "SELECT p.*, p.id as id, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo_atual
                FROM {$this->table} p
                INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE ef.quantidade <= COALESCE(ef.estoque_minimo, p.estoque_minimo) 
                AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
        
        return $this->query($sql, [$filialId])->fetchAll();
    }

    public function getLowStock($filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;

        $sql = "SELECT p.*, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo_atual
                FROM {$this->table} p
                INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE ef.quantidade > COALESCE(ef.estoque_minimo, p.estoque_minimo) 
                AND ef.quantidade <= (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) 
                AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
        
        return $this->query($sql, [$filialId])->fetchAll();
    }

    public function getOkStock($filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;

        $sql = "SELECT p.*, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo_atual
                FROM {$this->table} p
                INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE (ef.quantidade > (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) 
                OR COALESCE(ef.estoque_minimo, p.estoque_minimo) = 0)";
        
        return $this->query($sql, [$filialId])->fetchAll();
    }

    public function getStockStats($filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;

        $sqlBase = "FROM {$this->table} p INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?";
        
        $critical = $this->query("SELECT COUNT(*) $sqlBase WHERE ef.quantidade <= COALESCE(ef.estoque_minimo, p.estoque_minimo) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0", [$filialId])->fetchColumn();
        $low      = $this->query("SELECT COUNT(*) $sqlBase WHERE ef.quantidade > COALESCE(ef.estoque_minimo, p.estoque_minimo) AND ef.quantidade <= (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0", [$filialId])->fetchColumn();
        $ok       = $this->query("SELECT COUNT(*) $sqlBase WHERE (ef.quantidade > (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) OR COALESCE(ef.estoque_minimo, p.estoque_minimo) = 0)", [$filialId])->fetchColumn();

        return [
            'critical' => (int)$critical,
            'low'      => (int)$low,
            'ok'       => (int)$ok,
            'total'    => (int)($critical + $low + $ok)
        ];
    }

    public function searchStockAlarms($filters, $filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;

        $sql = "SELECT p.*, COALESCE(ef.quantidade, 0) as quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo_atual
                FROM {$this->table} p
                INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE 1=1";
        $params = [$filialId];

        if (!empty($filters['q'])) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $sql .= " AND p.categoria = ?";
            $params[] = $filters['categoria'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'CRITICO') {
                $sql .= " AND ef.quantidade <= COALESCE(ef.estoque_minimo, p.estoque_minimo) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
            } elseif ($filters['status'] === 'BAIXO') {
                $sql .= " AND ef.quantidade > COALESCE(ef.estoque_minimo, p.estoque_minimo) AND ef.quantidade <= (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
            } elseif ($filters['status'] === 'OK') {
                $sql .= " AND (ef.quantidade > (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) OR COALESCE(ef.estoque_minimo, p.estoque_minimo) = 0)";
            }
        }

        $sql .= " ORDER BY (ef.quantidade - COALESCE(ef.estoque_minimo, p.estoque_minimo)) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function paginateStockAlarms($filters, $page = 1, $perPage = 15, $filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;
        
        $where = " WHERE 1=1";
        $params = [$filialId];

        if (!empty($filters['q'])) {
            $where .= " AND (p.nome LIKE ? OR p.codigo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $where .= " AND p.categoria = ?";
            $params[] = $filters['categoria'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'CRITICO') {
                $where .= " AND ef.quantidade <= COALESCE(ef.estoque_minimo, p.estoque_minimo) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
            } elseif ($filters['status'] === 'BAIXO') {
                $where .= " AND ef.quantidade > COALESCE(ef.estoque_minimo, p.estoque_minimo) AND ef.quantidade <= (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) AND COALESCE(ef.estoque_minimo, p.estoque_minimo) > 0";
            } elseif ($filters['status'] === 'OK') {
                $where .= " AND (ef.quantidade > (COALESCE(ef.estoque_minimo, p.estoque_minimo) * 1.5) OR COALESCE(ef.estoque_minimo, p.estoque_minimo) = 0)";
            }
        }

        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} p INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ? $where");
        $totalStmt->execute($params);
        $total = $totalStmt->fetchColumn();

        $pages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*, ef.quantidade, COALESCE(ef.estoque_minimo, p.estoque_minimo) as estoque_minimo_atual 
                FROM {$this->table} p 
                INNER JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ? 
                $where 
                ORDER BY (ef.quantidade - COALESCE(ef.estoque_minimo, p.estoque_minimo)) ASC 
                LIMIT $perPage OFFSET $offset";
        
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

    public function updateStock($id, $qty, $type = 'entrada', $filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;
        $operator = ($type == 'entrada') ? '+' : '-';
        
        // Atualiza estoque específico da filial
        $sql = "INSERT INTO estoque_filiais (produto_id, filial_id, quantidade) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE quantidade = quantidade $operator ?";
        
        $value = ($type == 'entrada') ? (float)$qty : 0; // Se for entrada e novo, começa com qty. Se for saída e novo, vai dar erro ou negativo? 
        // Na verdade, o ON DUPLICATE deve lidar com isso.
        
        if ($type == 'saida') {
             return $this->query("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = ?", [$qty, $id, $filialId]);
        } else {
             return $this->query("INSERT INTO estoque_filiais (produto_id, filial_id, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + ?", [$id, $filialId, $qty, $qty]);
        }
    }

    public function hasEnoughStock($id, $requiredQty, $filialId = null) {
        if (!$filialId) $filialId = $_SESSION['filial_id'] ?? 1;
        
        $stmt = $this->db->prepare("SELECT quantidade FROM estoque_filiais WHERE produto_id = ? AND filial_id = ?");
        $stmt->execute([$id, $filialId]);
        $currentQty = $stmt->fetchColumn();
        
        if ($currentQty === false) {
            // Se não existe na tabela de filiais, tenta ver se existe na produtos (fallback legado ou matriz)
            $stmtM = $this->db->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmtM->execute([$id]);
            $currentQty = $stmtM->fetchColumn();
        }

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

        $filialId = $_SESSION['filial_id'] ?? 1;

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
            $res = $this->query("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params);

            // ATUALIZA ESTOQUE MÍNIMO NA FILIAL
            $this->query("INSERT INTO estoque_filiais (produto_id, filial_id, estoque_minimo) 
                          VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE estoque_minimo = ?", 
                          [$data['id'], $filialId, $data['estoque_minimo'], $data['estoque_minimo']]);

            return $res;

        } else {
            // --- INSERT ---
            $cols   = ['codigo', 'ncm', 'nome', 'unidade', 'categoria',
                       'preco_custo', 'preco_venda', 'quantidade', 'estoque_minimo', 'filial_id'];
            $params = [
                $data['codigo'], $data['ncm'] ?? null, $data['nome'], $data['unidade'],
                $data['categoria'], $data['preco_custo'], $data['preco_venda'],
                $data['quantidade'] ?? 0, $data['estoque_minimo'], $data['filial_id'] ?? $filialId,
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

            $this->query("INSERT INTO {$this->table} ($colList) VALUES ($placeholders)", $params);
            $newId = $this->db->lastInsertId();

            // TAMBÉM INICIALIZA NA TABELA DE FILIAIS
            $this->query("INSERT INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo) 
                          VALUES (?, ?, ?, ?)", 
                          [$newId, $filialId, $data['quantidade'] ?? 0, $data['estoque_minimo']]);

            return $newId;
        }
    }

        // Busca o maior código numérico, mas limita a 10000 para ignorar códigos de barras ou lixos
        $sql = "SELECT codigo FROM {$this->table} 
                WHERE codigo REGEXP '^[0-9]+$' 
                AND CAST(codigo AS UNSIGNED) < 10000
                ORDER BY CAST(codigo AS UNSIGNED) DESC LIMIT 1";
        try {
            $stmt = $this->db->query($sql);
            $lastCode = $stmt->fetchColumn();
            if (!$lastCode) return "3000"; // Reinicia em 3000 se não achar nada curto
            return (int)$lastCode + 1;
        } catch (\Exception $e) {
            $stmt = $this->db->query("SELECT MAX(id) FROM {$this->table}");
            return (int)$stmt->fetchColumn() + 3000; 
        }
    }
}
