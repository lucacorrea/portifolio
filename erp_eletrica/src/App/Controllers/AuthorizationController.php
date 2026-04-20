<?php
namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\AuditLogService;

class AuthorizationController extends BaseController {
    public function generate() {
        // Apenas admin ou master pode gerar códigos para outros
        if (($_SESSION['usuario_nivel'] ?? '') !== 'admin' && ($_SESSION['usuario_nivel'] ?? '') !== 'master') {
            echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $tipo = $data['tipo'] ?? 'desconto';
            
            if (!in_array($tipo, ['desconto', 'sangria', 'suprimento', 'cancelamento'])) {
                echo json_encode(['success' => false, 'error' => 'Tipo de autorização inválido.']);
                exit;
            }

            $service = new AuthorizationService();
            $codigo = $service->generateCode($tipo, $_SESSION['filial_id'] ?? 1, $_SESSION['usuario_id']);

            $audit = new AuditLogService();
            $audit->record("Geração de código $tipo", 'autorizacoes_temporarias', null, null, [
                'codigo' => $codigo,
                'gerado_por' => $_SESSION['usuario_id']
            ]);

            echo json_encode(['success' => true, 'codigo' => $codigo]);
            exit;
        }
    }
}
