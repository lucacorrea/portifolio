<?php
namespace App\Models;

class Filial extends BaseModel {
    protected $table = 'filiais';

    public function getPrincipal() {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE principal = 1 LIMIT 1");
        return $stmt->fetch();
    }

    public function getAllBranches() {
        return $this->all();
    }

    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET 
                    nome = ?, cnpj = ?, inscricao_estadual = ?, 
                    logradouro = ?, numero = ?, bairro = ?, municipio = ?, uf = ?, cep = ?,
                    csc_id = ?, csc_token = ?, ambiente = ? ";
            $params = [
                $data['nome'], $data['cnpj'], $data['inscricao_estadual'],
                $data['logradouro'], $data['numero'], $data['bairro'], $data['municipio'], $data['uf'], $data['cep'],
                $data['csc_id'], $data['csc_token'], $data['ambiente']
            ];

            if (!empty($data['certificado_pfx'])) {
                $sql .= ", certificado_pfx = ?, certificado_senha = ? ";
                $params[] = $data['certificado_pfx'];
                $params[] = $data['certificado_senha'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $data['id'];
            return $this->query($sql, $params);
        } else {
            $sql = "INSERT INTO {$this->table} (
                        nome, cnpj, inscricao_estadual, logradouro, numero, bairro, municipio, uf, cep,
                        csc_id, csc_token, ambiente, certificado_pfx, certificado_senha
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            return $this->query($sql, [
                $data['nome'], $data['cnpj'], $data['inscricao_estadual'],
                $data['logradouro'], $data['numero'], $data['bairro'], $data['municipio'], $data['uf'], $data['cep'],
                $data['csc_id'], $data['csc_token'], $data['ambiente'] ?? 2,
                $data['certificado_pfx'] ?? null, $data['certificado_senha'] ?? null
            ]);
        }
    }
}
