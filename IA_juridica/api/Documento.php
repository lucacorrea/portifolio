<?php
/**
 * Documento Management Class
 */

require_once __DIR__ . '/../config/database.php';

class Documento {
    private $pdo;

    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }

    public function getNextNumber($type) {
        $year = date('Y');
        $stmt = $this->pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero_documento, '/', 1) AS UNSIGNED)) as last_num 
                                    FROM documentos 
                                    WHERE tipo_documento = ? AND numero_documento LIKE ?");
        $stmt->execute([$type, "%/$year"]);
        $row = $stmt->fetch();
        
        $nextNum = ($row['last_num'] ?? 0) + 1;
        return str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    public function save($data) {
        $sql = "INSERT INTO documentos (tipo_documento, numero_documento, destinatario, assunto, conteudo, responsavel, cargo, cidade, data_documento) 
                VALUES (:tipo, :numero, :destinatario, :assunto, :conteudo, :responsavel, :cargo, :cidade, :data_doc)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $data['tipo_documento'],
            ':numero' => $data['numero_documento'],
            ':destinatario' => $data['destinatario'],
            ':assunto' => $data['assunto'],
            ':conteudo' => $data['conteudo'],
            ':responsavel' => $data['responsavel'],
            ':cargo' => $data['cargo'],
            ':cidade' => $data['cidade'],
            ':data_doc' => $data['data_documento']
        ]);

        return $this->pdo->lastInsertId();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll($filters = []) {
        $sql = "SELECT * FROM documentos WHERE 1=1";
        $params = [];

        if (!empty($filters['tipo'])) {
            $sql .= " AND tipo_documento = ?";
            $params[] = $filters['tipo'];
        }

        if (!empty($filters['busca'])) {
            $sql .= " AND (assunto LIKE ? OR destinatario LIKE ?)";
            $params[] = "%{$filters['busca']}%";
            $params[] = "%{$filters['busca']}%";
        }

        $sql .= " ORDER BY data_criacao DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStats() {
        $stats = [
            'total' => 0,
            'por_tipo' => [],
            'recentes' => []
        ];

        // Total
        $stats['total'] = $this->pdo->query("SELECT COUNT(*) FROM documentos")->fetchColumn();

        // Por tipo
        $stmt = $this->pdo->query("SELECT tipo_documento, COUNT(*) as qtd FROM documentos GROUP BY tipo_documento ORDER BY qtd DESC");
        $stats['por_tipo'] = $stmt->fetchAll();

        // Recentes
        $stmt = $this->pdo->query("SELECT * FROM documentos ORDER BY data_criacao DESC LIMIT 5");
        $stats['recentes'] = $stmt->fetchAll();

        return $stats;
    }
}
