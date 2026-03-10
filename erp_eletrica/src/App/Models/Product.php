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
        $sql = "SELECT * FROM {$this->table} WHERE quantidade <= estoque_minimo";
        $params = [];
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        return $this->query($sql, $params)->fetchAll();
    }

    public function updateStock($id, $qty, $type = 'entrada') {
        $operator = ($type == 'entrada') ? '+' : '-';
        return $this->query("UPDATE {$this->table} SET quantidade = quantidade $operator ? WHERE id = ?", [$qty, $id]);
    }

    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET 
                    codigo = ?, ncm = ?, cean = ?, cest = ?, origem = ?, csosn = ?, 
                    cfop_interno = ?, cfop_externo = ?, aliquota_icms = ?,
                    nome = ?, unidade = ?, categoria = ?, tipo_produto = ?,
                    peso = ?, dimensoes = ?, descricao = ?,
                    preco_custo = ?, preco_venda = ?, preco_venda_2 = ?, preco_venda_3 = ?, preco_venda_atacado = ?, 
                    estoque_minimo = ? ";
            $params = [
                $data['codigo'], $data['ncm'], $data['cean'] ?? 'SEM GTIN', $data['cest'], $data['origem'], $data['csosn'],
                $data['cfop_interno'], $data['cfop_externo'], $data['aliquota_icms'],
                $data['nome'], $data['unidade'], $data['categoria'], $data['tipo_produto'] ?? 'simples',
                $data['peso'] ?? null, $data['dimensoes'] ?? null, $data['descricao'] ?? null,
                $data['preco_custo'], $data['preco_venda'], 
                $data['preco_venda_2'] === '' ? null : $data['preco_venda_2'], 
                $data['preco_venda_3'] === '' ? null : $data['preco_venda_3'], 
                $data['preco_venda_atacado'] === '' ? null : $data['preco_venda_atacado'],
                $data['estoque_minimo']
            ];

            if (isset($data['imagens'])) {
                $sql .= ", imagens = ? ";
                $params[] = $data['imagens'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $data['id'];
            return $this->query($sql, $params);
        } else {
            $sql = "INSERT INTO {$this->table} (
                        codigo, ncm, cean, cest, origem, csosn, 
                        cfop_interno, cfop_externo, aliquota_icms,
                        nome, unidade, categoria, tipo_produto, peso, dimensoes, descricao,
                        preco_custo, preco_venda, preco_venda_2, preco_venda_3, preco_venda_atacado, 
                        quantidade, estoque_minimo, filial_id, imagens
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            return $this->query($sql, [
                $data['codigo'], $data['ncm'], $data['cean'] ?? 'SEM GTIN', $data['cest'] ?? null, $data['origem'] ?? 0, 
                $data['csosn'] ?? '102', $data['cfop_interno'] ?? '5102', 
                $data['cfop_externo'] ?? '6102', $data['aliquota_icms'] ?? 0,
                $data['nome'], $data['unidade'], $data['categoria'], $data['tipo_produto'] ?? 'simples',
                $data['peso'] ?? null, $data['dimensoes'] ?? null, $data['descricao'] ?? null,
                $data['preco_custo'], $data['preco_venda'], 
                $data['preco_venda_2'] === '' ? null : $data['preco_venda_2'], 
                $data['preco_venda_3'] === '' ? null : $data['preco_venda_3'], 
                $data['preco_venda_atacado'] === '' ? null : $data['preco_venda_atacado'],
                $data['quantidade'] ?? 0,
                $data['estoque_minimo'], 
                $data['filial_id'] ?? 1, $data['imagens'] ?? null
            ]);
        }
    }
}
