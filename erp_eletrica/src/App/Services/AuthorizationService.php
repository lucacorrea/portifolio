<?php
namespace App\Services;

class AuthorizationService extends BaseService {
    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        parent::__construct(new class($db) {
            private $db;
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
        });
    }

    public function generateCode($tipo, $filialId, $usuarioAutorizadorId = null) {
        $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $validade = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        
        $this->repository->create([
            'tipo' => $tipo,
            'codigo' => $codigo,
            'usuario_autorizador_id' => $usuarioAutorizadorId,
            'validade' => $validade,
            'filial_id' => $filialId
        ]);

        return $codigo;
    }

    public function validateAndUse($codigo, $tipo, $filialId) {
        $auth = $this->repository->validate($codigo, $tipo, $filialId);
        if ($auth) {
            $this->repository->markAsUsed($auth['id']);
            return true;
        }
        return false;
    }
}
