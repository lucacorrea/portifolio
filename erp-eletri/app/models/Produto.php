<?php
// app/models/Produto.php

class Produto extends Model {
    
    public function getAll($limit = 100) {
        $stmt = $this->db->prepare("SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.nome LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM produtos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function search($term) {
        $term = "%$term%";
        $stmt = $this->db->prepare("SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.nome LIKE :term OR p.codigo_interno LIKE :term OR p.codigo_barras LIKE :term LIMIT 20");
        $stmt->execute(['term' => $term]);
        return $stmt->fetchAll();
    }

    public function create($data) {
        $sql = "INSERT INTO produtos (categoria_id, nome, codigo_interno, codigo_barras, ncm, unidade, preco_custo, preco_venda, preco_prefeitura, preco_avista) 
                VALUES (:categoria_id, :nome, :codigo_interno, :codigo_barras, :ncm, :unidade, :preco_custo, :preco_venda, :preco_prefeitura, :preco_avista)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function update($id, $data) {
        $data['id'] = $id;
        $sql = "UPDATE produtos SET 
                categoria_id = :categoria_id, 
                nome = :nome, 
                codigo_interno = :codigo_interno, 
                codigo_barras = :codigo_barras, 
                ncm = :ncm, 
                unidade = :unidade, 
                preco_custo = :preco_custo, 
                preco_venda = :preco_venda, 
                preco_prefeitura = :preco_prefeitura, 
                preco_avista = :preco_avista 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function getEstoque($produto_id) {
        $stmt = $this->db->prepare("SELECT e.*, f.nome as filial_nome FROM estoque e JOIN filiais f ON e.filial_id = f.id WHERE e.produto_id = :id");
        $stmt->execute(['id' => $produto_id]);
        return $stmt->fetchAll();
    }
    
    public function getCategories() {
        $stmt = $this->db->query("SELECT * FROM categorias ORDER BY nome");
        return $stmt->fetchAll();
    }
}
