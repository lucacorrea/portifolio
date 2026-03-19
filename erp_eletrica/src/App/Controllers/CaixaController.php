<?php
namespace App\Controllers;

use App\Models\Cashier;
use App\Models\CashierMovement;
use App\Services\AuthService;

class CaixaController extends BaseController {
    public function index() {
        AuthService::checkPermission('caixa', 'visualizar');
        
        $cashierModel = new Cashier();
        $filialId = $_SESSION['filial_id'];
        
        // Se for Master, pode ver todos. Se não, apenas da filial.
        // O BaseModel já lida com o filtro de filial_id automaticamente se não for Master.
        $caixas = $cashierModel->all("data_abertura DESC");

        $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $filialId);
        
        $summary = null;
        if ($caixaAberto) {
            $summary = $cashierModel->getSummary($caixaAberto['id']);
        }

        $this->render('caixa/index', [
            'caixas' => $caixas,
            'caixaAberto' => $caixaAberto,
            'summary' => $summary,
            'title' => 'Controle de Caixa',
            'pageTitle' => 'Gestão de Caixa'
        ]);
    }

    public function abrir() {
        AuthService::checkPermission('caixa', 'abrir');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $valorAbertura = $_POST['valor_abertura'] ?? 0;
            $cashierModel = new Cashier();
            
            // Valida se já existe aberto
            $existente = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id']);
            if ($existente) {
                header('Location: caixa.php?error=Você já possui um caixa aberto.');
                exit;
            }

            $cashierModel->create([
                'filial_id' => $_SESSION['filial_id'],
                'operador_id' => $_SESSION['usuario_id'],
                'valor_abertura' => $valorAbertura,
                'status' => 'aberto',
                'data_abertura' => date('Y-m-d H:i:s')
            ]);

            $this->logAction('abertura_caixa', 'caixas', null, null, ['valor' => $valorAbertura]);
            header('Location: caixa.php?success=Caixa aberto com sucesso.');
            exit;
        }
    }

    public function registrarMovimentacao() {
        AuthService::checkPermission('caixa', 'movimentar');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tipo = $_POST['tipo'];
            $valor = $_POST['valor'];
            $motivo = $_POST['motivo'];
            $caixaId = $_POST['caixa_id'];
            $authCode = $_POST['auth_code'] ?? null;
            $authPassword = $_POST['auth_password'] ?? null;
            $isAdmin = in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'master']);

            if (!$isAdmin) {
                $authService = new \App\Services\AuthorizationService();
                $userService = new \App\Models\User();
                $authorized = false;

                // 1. Try One-time Auth Code
                if ($authCode && ($authService->validateAndUse($authCode, $tipo, $_SESSION['filial_id']) || $authService->validateAndUse($authCode, 'geral', $_SESSION['filial_id']))) {
                    $authorized = true;
                } 
                // 2. Try Admin Password (any admin of the branch)
                else if ($authPassword) {
                    $admins = $userService->findAdmins($_SESSION['filial_id']);
                    foreach ($admins as $adm) {
                        if (password_verify($authPassword, $adm['senha'])) {
                            $authorized = true;
                            break;
                        }
                    }
                }

                if (!$authorized) {
                    header('Location: caixa.php?error=Autorização administrativa obrigatória para esta operação.');
                    exit;
                }

                $audit = new \App\Services\AuditLogService();
                $audit->record('Autorização de Movimentação de Caixa', 'caixa_movimentacoes', null, null, [
                    'tipo' => $tipo,
                    'valor' => $valor,
                    'operador' => $_SESSION['usuario_id'],
                    'metodo' => $authCode ? 'codigo' : 'senha'
                ]);
            }

            $movementModel = new CashierMovement();
            $movementModel->create([
                'caixa_id' => $caixaId,
                'tipo' => $tipo,
                'valor' => $valor,
                'motivo' => $motivo,
                'operador_id' => $_SESSION['usuario_id']
            ]);

            $this->logAction($tipo, 'caixa_movimentacoes', null, null, [
                'valor' => $valor, 
                'motivo' => $motivo,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'horario' => date('H:i:s')
            ]);
            header('Location: caixa.php?success=Movimentação registrada.');
            exit;
        }
    }

    public function fechar() {
        AuthService::checkPermission('caixa', 'fechar');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $caixaId = $_POST['caixa_id'];
            $valorInformado = $_POST['valor_fechamento'] ?? 0;
            $justificativa = $_POST['justificativa'] ?? '';

            $cashierModel = new Cashier();
            $caixa = $cashierModel->find($caixaId);

            if (!$caixa || $caixa['status'] !== 'aberto') {
                header('Location: caixa.php?error=Caixa inválido ou já fechado.');
                exit;
            }

            $summary = $cashierModel->getSummary($caixaId);
            $totalSistema = $caixa['valor_abertura'] + $summary['vendas_dinheiro'] + $summary['suprimentos'] - $summary['sangrias'];
            $diferenca = $valorInformado - $totalSistema;

            $cashierModel->update($caixaId, [
                'valor_fechamento' => $valorInformado,
                'status' => 'fechado',
                'data_fechamento' => date('Y-m-d H:i:s'),
                'observacao' => $justificativa
            ]);

            if ($diferenca != 0) {
                $this->logAction('divergencia_caixa', 'caixas', $caixaId, null, [
                    'sistema' => $totalSistema,
                    'informado' => $valorInformado,
                    'diferenca' => $diferenca,
                    'justificativa' => $justificativa
                ]);
            }

            $this->logAction('fechamento_caixa', 'caixas', $caixaId);
            header('Location: caixa.php?success=Caixa fechado com sucesso.');
            exit;
        }
    }

    public function validate_code() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                
                if (!$data) {
                    echo json_encode(['success' => false, 'error' => 'Dados de entrada inválidos.']);
                    exit;
                }

                $code = $data['code'] ?? '';
                $tipo = $data['tipo'] ?? 'geral';
                $filialId = $_SESSION['filial_id'] ?? null;

                if (!$filialId) {
                    echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.']);
                    exit;
                }

                $authService = new \App\Services\AuthorizationService();
                $result = $authService->validateOnly($code, $tipo, $filialId);
                
                echo json_encode($result);
                exit;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro interno ao validar: ' . $e->getMessage()]);
            exit;
        }
    }

    protected function logAction(string $action, string $table = null, int $id = null, $old = null, $new = null) {
        $audit = new \App\Services\AuditLogService();
        $audit->record($action, $table, $id, $old, $new);
    }
}
