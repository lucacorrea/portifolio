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

            $movementModel = new CashierMovement();
            $movementModel->create([
                'caixa_id' => $caixaId,
                'tipo' => $tipo,
                'valor' => $valor,
                'motivo' => $motivo,
                'operador_id' => $_SESSION['usuario_id']
            ]);

            $this->logAction($tipo, 'caixa_movimentacoes', null, null, ['valor' => $valor, 'motivo' => $motivo]);
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

            if ($diferenca != 0 && empty($justificativa)) {
                // Em cenário real, retornar para a view com erro, aqui simplificamos
                header('Location: caixa.php?error=Justificativa obrigatória para divergência.');
                exit;
            }

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
}
