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
                    nome = ?, razao_social = ?, cnpj = ?, inscricao_estadual = ?, crt = ?,
                    tipo_emissao = ?, finalidade_emissao = ?, indicador_presenca = ?, tipo_impressao_danfe = ?, serie_nfce = ?, ultimo_numero_nfce = ?,
                    logradouro = ?, numero = ?, complemento = ?, bairro = ?, municipio = ?, codigo_municipio = ?, uf = ?, codigo_uf = ?, cep = ?,
                    telefone = ?, email = ?,
                    csc_id = ?, csc_token = ?, ambiente = ? ";
            $params = [
                $data['nome'], $data['razao_social'] ?? null, $data['cnpj'], $data['inscricao_estadual'], $data['crt'] ?? 1,
                $data['tipo_emissao'] ?? 'Normal', $data['finalidade_emissao'] ?? 'Normal', $data['indicador_presenca'] ?? 'Operacao presencial', $data['tipo_impressao_danfe'] ?? 'NFC-e', $data['serie_nfce'] ?? 1, $data['ultimo_numero_nfce'] ?? 0,
                $data['logradouro'], $data['numero'], $data['complemento'] ?? null, $data['bairro'], $data['municipio'], $data['codigo_municipio'] ?? null, $data['uf'], $data['codigo_uf'] ?? null, $data['cep'],
                $data['telefone'] ?? null, $data['email'] ?? null,
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
                        nome, razao_social, cnpj, inscricao_estadual, crt, 
                        tipo_emissao, finalidade_emissao, indicador_presenca, tipo_impressao_danfe, serie_nfce, ultimo_numero_nfce,
                        logradouro, numero, complemento, bairro, municipio, codigo_municipio, uf, codigo_uf, cep,
                        telefone, email,
                        csc_id, csc_token, ambiente, certificado_pfx, certificado_senha, principal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            return $this->query($sql, [
                $data['nome'], $data['razao_social'] ?? null, $data['cnpj'], $data['inscricao_estadual'], $data['crt'] ?? 1,
                $data['tipo_emissao'] ?? 'Normal', $data['finalidade_emissao'] ?? 'Normal', $data['indicador_presenca'] ?? 'Operacao presencial', $data['tipo_impressao_danfe'] ?? 'NFC-e', $data['serie_nfce'] ?? 1, $data['ultimo_numero_nfce'] ?? 0,
                $data['logradouro'], $data['numero'], $data['complemento'] ?? null, $data['bairro'], $data['municipio'], $data['codigo_municipio'] ?? null, $data['uf'], $data['codigo_uf'] ?? null, $data['cep'],
                $data['telefone'] ?? null,  $data['email'] ?? null,
                $data['csc_id'], $data['csc_token'], $data['ambiente'] ?? 2,
                $data['certificado_pfx'] ?? null, $data['certificado_senha'] ?? null,
                0 // Filiais aren't Matriz by default when created via this form
            ]);
        }
    }
}
