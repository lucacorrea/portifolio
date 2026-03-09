<?php
namespace App\Services;

class AuthorizationService extends BaseService {
    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        parent::__construct(new class($db) {
            public $db;
            public function __construct($db) { $this->db = $db; }
            public function create($data) {
                $fields = array_keys($data);
                $placeholders = array_fill(0, count($fields), '?');
                $sql = "INSERT INTO autorizacoes_temporarias (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_values($data));
                return $this->db->lastInsertId();
            }
            public function validate($codigo, $tipo, $filialId) {
                $sql = "SELECT * FROM autorizacoes_temporarias 
                        WHERE codigo = ? AND tipo = ? AND filial_id = ? 
                        AND utilizado = 0 AND validade > NOW() 
                        LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$codigo, $tipo, $filialId]);
                return $stmt->fetch();
            }
            public function markAsUsed($id) {
                return $this->db->prepare("UPDATE autorizacoes_temporarias SET utilizado = 1 WHERE id = ?")
                                ->execute([$id]);
            }
            public function invalidateOldCodes($tipo, $filialId) {
                $sql = "UPDATE autorizacoes_temporarias SET utilizado = 1 
                        WHERE tipo = ? AND filial_id = ? AND utilizado = 0";
                return $this->db->prepare($sql)->execute([$tipo, $filialId]);
            }
        });
    }

    public function generateCode($tipo, $filialId, $usuarioAutorizadorId = null) {
        // Invalidate previous unused codes for this type and branch
        $this->repository->invalidateOldCodes($tipo, $filialId);

        $codigo = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $validade = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Increased validity since it might take a moment to use
        
        $this->repository->create([
            'tipo' => $tipo,
            'codigo' => $codigo,
            'usuario_autorizador_id' => $usuarioAutorizadorId,
            'validade' => $validade,
            'filial_id' => $filialId,
            'utilizado' => 0
        ]);

        return $codigo;
    }

    public function validateAndUse($codigo, $tipo, $filialId) {
        $auth = $this->repository->validate($codigo, $tipo, $filialId);
        if (!$auth) {
            $auth = $this->repository->validate($codigo, 'geral', $filialId);
        }

        if ($auth) {
            $this->repository->markAsUsed($auth['id']);
            return true;
        }
        return false;
    }

    public function validateOnly($codigo, $tipo, $filialId) {
        $auth = $this->repository->validate($codigo, $tipo, $filialId);
        if ($auth) return ['success' => true];

        // Check if it exists for ANOTHER type to provide better error message
        $anyAuth = $this->repository->db->prepare("SELECT tipo FROM autorizacoes_temporarias WHERE codigo = ? AND filial_id = ? AND utilizado = 0 AND validade > NOW() LIMIT 1");
        $anyAuth->execute([$codigo, $filialId]);
        $found = $anyAuth->fetch();

        if ($found) {
            $foundTipo = strtolower($found['tipo'] ?? '');
            if ($foundTipo === 'geral') return ['success' => true];
            
            $tipoDisplay = $foundTipo ?: 'Não Definido';
            return ['success' => false, 'error' => "Este código é para '" . ucfirst($tipoDisplay) . "', não para '" . ucfirst($tipo) . "'."];
        }

        return ['success' => false, 'error' => 'Código inválido, expirado ou já utilizado.'];
    }
}
