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
        
        // Paginação e Query manual
        $db = \App\Config\Database::getInstance()->getConnection();
        $isMaster = ($_SESSION['usuario_nivel'] ?? '') === 'master';
        
        $where = "1=1";
        $params = [];
        if (!$isMaster) {
            $where .= " AND c.filial_id = ?";
            $params[] = $filialId;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $sqlTotal = "SELECT COUNT(*) FROM caixas c WHERE $where";
        $stmtTotal = $db->prepare($sqlTotal);
        $stmtTotal->execute($params);
        $totalItems = (int)$stmtTotal->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);

        $sqlCaixas = "
            SELECT c.*, 
                   u.nome as operador_nome,
                   f.nome as filial_nome,
                   f.principal as filial_principal
            FROM caixas c
            LEFT JOIN usuarios u ON c.operador_id = u.id
            LEFT JOIN filiais f ON c.filial_id = f.id
            WHERE $where
            ORDER BY c.data_abertura DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmtCaixas = $db->prepare($sqlCaixas);
        $stmtCaixas->execute($params);
        $caixas = $stmtCaixas->fetchAll();

        $caixaAberto = $cashierModel->getOpenForFilial($filialId);
        
        $summary = null;
        $detailedSummary = null;
        if ($caixaAberto) {
            $summary = $cashierModel->getSummary($caixaAberto['id']);
            $detailedSummary = $cashierModel->getDetailedSummary($caixaAberto['id']);
        }

        $this->render('caixa/index', [
            'caixas' => $caixas,
            'caixaAberto' => $caixaAberto,
            'summary' => $summary,
            'detailedSummary' => $detailedSummary,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems
            ],
            'title' => 'Controle de Caixa',
            'pageTitle' => 'Gestão de Caixa'
        ]);

    }

    public function abrir() {
        AuthService::checkPermission('caixa', 'abrir');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $valorAbertura = $_POST['valor_abertura'] ?? 0;
            $cashierModel = new Cashier();
            
            $this->executeSafe(function() use ($valorAbertura, $cashierModel) {
                // Valida se já existe aberto
                $existente = $cashierModel->getOpenForFilial($_SESSION['filial_id']);
                if ($existente) {
                    throw new \Exception("Você já possui um caixa aberto.");
                }

                $caixaId = $cashierModel->create([
                    'filial_id' => $_SESSION['filial_id'],
                    'operador_id' => $_SESSION['usuario_id'],
                    'valor_abertura' => $valorAbertura,
                    'status' => 'aberto',
                    'data_abertura' => date('Y-m-d H:i:s')
                ]);

                if ($valorAbertura > 0) {
                    try {
                        $db = \App\Config\Database::getInstance()->getConnection();
                        $db->exec("ALTER TABLE caixa_movimentacoes MODIFY tipo VARCHAR(50)");
                    } catch (\Exception $e) {}

                    $movementModel = new CashierMovement();
                    $movementModel->create([
                        'caixa_id' => $caixaId,
                        'tipo' => 'entrada',
                        'valor' => $valorAbertura,
                        'motivo' => 'Abertura de Caixa',
                        'operador_id' => $_SESSION['usuario_id']
                    ]);
                }

                $this->logAction('abertura_caixa', 'caixas', null, null, ['valor' => $valorAbertura]);
                setFlash('success', 'Caixa aberto com sucesso.');
                $this->redirect('caixa.php');
            }, false); // Not AJAX, use redirects
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
                $userModel = new \App\Models\User();
                $authorized = false;

                // 1. Try One-time Auth Code (6-digit)
                if ($authCode && ($authService->validateAndUse($authCode, $tipo, $_SESSION['filial_id']) || $authService->validateAndUse($authCode, 'geral', $_SESSION['filial_id']))) {
                    $authorized = true;
                } 
                // 2. Try Admin Password/PIN as a fallback (using either auth_code or auth_password input)
                else if ($authCode || $authPassword) {
                    $credentialToTest = $authCode ?: $authPassword;
                    $admins = $userModel->findAdmins();
                    foreach ($admins as $adm) {
                        if ($userModel->validateAuth($adm['id'], $credentialToTest)) {
                            $authorized = true;
                            break;
                        }
                    }
                }

                if (!$authorized) {
                    header('Location: caixa.php?error=Autorização administrativa inválida ou expirada.');
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
            $breakdownInformed = $_POST['breakdown'] ?? [];

            $cashierModel = new Cashier();
            $caixa = $cashierModel->find($caixaId);

            if (!$caixa || $caixa['status'] !== 'aberto') {
                header('Location: caixa.php?error=Caixa inválido ou já fechado.');
                exit;
            }

            $detailed = $cashierModel->getDetailedSummary($caixaId);
            $resumoFinal = [];
            $totalInformado = 0;

            foreach ($detailed['breakdown'] as $metodo => $calculado) {
                $informado = (float)($breakdownInformed[$metodo] ?? 0);
                $diferenca = $informado - $calculado;
                $resumoFinal[$metodo] = [
                    'calculado' => $calculado,
                    'informado' => $informado,
                    'diferenca' => $diferenca
                ];
                $totalInformado += $informado;
            }

            // Total informado deve bater com o que foi digitado no "Valor Físico" ou ser usado o acumulado
            // Se o usuário mandou o breakdown, confiamos nele para a soma total também
            $totalSistema = $caixa['valor_abertura'] + $detailed['saldo']; // Saldo inclui suprimento/sangria
            $diferencaTotal = $totalInformado - $totalSistema;

            $cashierModel->update($caixaId, [
                'valor_fechamento' => $totalInformado,
                'status' => 'fechado',
                'data_fechamento' => date('Y-m-d H:i:s'),
                'observacao' => $justificativa,
                'resumo_fechamento' => json_encode($resumoFinal)
            ]);

            if ($diferencaTotal != 0) {
                $this->logAction('divergencia_caixa', 'caixas', $caixaId, null, [
                    'sistema' => $totalSistema,
                    'informado' => $totalInformado,
                    'diferenca' => $diferencaTotal,
                    'justificativa' => $justificativa,
                    'detalhes' => $resumoFinal
                ]);
            }

            $this->logAction('fechamento_caixa', 'caixas', $caixaId);
            header('Location: caixa.php?success=Caixa fechado com sucesso.&print_id=' . $caixaId);
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

    public function detalhes() {
        AuthService::checkPermission('caixa', 'visualizar');

        $id = $_GET['id'] ?? null;
        if (!$id) {
            header('Location: caixa.php?error=Sessão não encontrada.');
            exit;
        }

        try {
            // Fix database column constraint for existing sessions immediately on viewing details
            $db = \App\Config\Database::getInstance()->getConnection();
            $db->exec("ALTER TABLE caixa_movimentacoes MODIFY tipo VARCHAR(50)");
            $db->exec("UPDATE caixa_movimentacoes SET tipo = 'entrada' WHERE (tipo = '' OR tipo IS NULL) AND motivo = 'Abertura de Caixa'");
        } catch (\Exception $e) {}

        $cashierModel = new Cashier();
        $details = $cashierModel->getSessionDetails($id);

        if (!$details) {
            header('Location: caixa.php?error=Sessão não encontrada.');
            exit;
        }

        $this->render('caixa/detalhes', [
            'details' => $details,
            'title' => 'Detalhes da Sessão',
            'pageTitle' => 'Detalhes da Sessão #' . $id
        ]);
    }

    protected function logAction(string $action, ?string $table = null, ?int $id = null, $old = null, $new = null) {
        $audit = new \App\Services\AuditLogService();
        $audit->record($action, $table, $id, $old, $new);
    }
}
