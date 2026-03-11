<?php
namespace App\Models;

class Filial extends BaseModel {
    protected $table = 'filiais';

    public function getPrincipal() {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE principal = 1 LIMIT 1");
        return $stmt->fetch();
    }

    public function isPrincipal($id) {
        $stmt = $this->query("SELECT principal FROM {$this->table} WHERE id = ?", [$id]);
        $res = $stmt->fetch();
        return ($res && $res['principal'] == 1);
    }

    public function getAllBranches($branchLimitId = null) {
        if ($branchLimitId) {
            $stmt = $this->query("SELECT * FROM {$this->table} WHERE id = ?", [$branchLimitId]);
            return $stmt->fetchAll();
        }
        return $this->all();
    }

    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET 
                    nome = ?, razao_social = ?, nome_fantasia = ?, cnpj = ?, 
                    inscricao_estadual = ?, inscricao_municipal = ?, 
                    cep = ?, logradouro = ?, numero_endereco = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?, codigo_uf = ?, codigo_municipio = ?,
                    telefone = ?, email = ?,
                    ambiente = ?, regime_tributario = ?, serie_nfce = ?, ultimo_numero_nfce = ?, 
                    csc = ?, csc_id = ?, tipo_emissao = ?, finalidade = ?, ind_pres = ?, tipo_impressao = ? ";
            
            $params = [
                $data['nome'], $data['razao_social'] ?? null, $data['nome_fantasia'] ?? $data['nome'], $data['cnpj'],
                $data['inscricao_estadual'], $data['inscricao_municipal'] ?? null,
                $data['cep'], $data['logradouro'], $data['numero_endereco'] ?? ($data['numero'] ?? null), $data['complemento'] ?? null, $data['bairro'], $data['cidade'] ?? ($data['municipio'] ?? null), $data['uf'], $data['codigo_uf'] ?? null, $data['codigo_municipio'] ?? null,
                $data['telefone'] ?? null, $data['email'] ?? null,
                $data['ambiente'] ?? 2, $data['regime_tributario'] ?? ($data['crt'] ?? 1), $data['serie_nfce'] ?? 1, $data['ultimo_numero_nfce'] ?? 0,
                $data['csc'] ?? ($data['csc_token'] ?? null), $data['csc_id'] ?? null, $data['tipo_emissao'] ?? 1, $data['finalidade'] ?? 1, $data['ind_pres'] ?? 1, $data['tipo_impressao'] ?? 4
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
                        nome, razao_social, nome_fantasia, cnpj, 
                        inscricao_estadual, inscricao_municipal, 
                        cep, logradouro, numero_endereco, complemento, bairro, cidade, uf, codigo_uf, codigo_municipio,
                        telefone, email,
                        ambiente, regime_tributario, serie_nfce, ultimo_numero_nfce, 
                        csc, csc_id, tipo_emissao, finalidade, ind_pres, tipo_impressao,
                        certificado_pfx, certificado_senha, principal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['nome'], $data['razao_social'] ?? null, $data['nome_fantasia'] ?? $data['nome'], $data['cnpj'],
                $data['inscricao_estadual'], $data['inscricao_municipal'] ?? null,
                $data['cep'], $data['logradouro'], $data['numero_endereco'] ?? ($data['numero'] ?? null), $data['complemento'] ?? null, $data['bairro'], $data['cidade'] ?? ($data['municipio'] ?? null), $data['uf'], $data['codigo_uf'] ?? null, $data['codigo_municipio'] ?? null,
                $data['telefone'] ?? null, $data['email'] ?? null,
                $data['ambiente'] ?? 2, $data['regime_tributario'] ?? ($data['crt'] ?? 1), $data['serie_nfce'] ?? 1, $data['ultimo_numero_nfce'] ?? 0,
                $data['csc'] ?? ($data['csc_token'] ?? null), $data['csc_id'] ?? null, $data['tipo_emissao'] ?? 1, $data['finalidade'] ?? 1, $data['ind_pres'] ?? 1, $data['tipo_impressao'] ?? 4,
                $data['certificado_pfx'] ?? null, $data['certificado_senha'] ?? null,
                0
            ];
            
            return $this->query($sql, $params);
        }
    }
}
