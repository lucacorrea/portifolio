<?php
namespace App\Controllers;

use App\Models\CostCenter;
use App\Models\CostEntry;
use App\Models\Sale;
use App\Models\Product;
use App\Services\AuthService;

class CentroCustoController extends BaseController {
    private $costCenter;
    private $costEntry;

    public function __construct() {
        AuthService::checkPermission('custos', 'visualizar');
        $this->costCenter = new CostCenter();
        $this->costEntry = new CostEntry();
    }

    public function index() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $centros = $this->costCenter->where(['filial_id' => $filial_id]);
        $mes_atual = date('Y-m');
        $lancamentos = $this->costEntry->getDetailedByMonth($filial_id, $mes_atual);
        $resumo = $this->costEntry->getMonthlyTotals($filial_id, $mes_atual);

        $this->render('custos/index', [
            'centros' => $centros,
            'lancamentos' => $lancamentos,
            'resumo' => $resumo,
            'mes_referencia' => $mes_atual,
            'pageTitle' => 'Gestão de Centro de Custos'
        ]);
    }

    public function criar() {
        AuthService::checkPermission('custos', 'gerenciar');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'filial_id' => $_SESSION['filial_id'] ?? 1,
                'nome' => $_POST['nome'],
                'tipo' => $_POST['tipo'],
                'ativo' => 1
            ];
            $id = $this->costCenter->create($data);
            $this->logAction('criacao_centro_custo', 'centros_custo', $id);
            setFlash('success', 'Centro de custo criado com sucesso!');
            $this->redirect('custos.php');
        }
    }

    public function lancarCusto() {
        AuthService::checkPermission('custos', 'gerenciar');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'filial_id' => $_SESSION['filial_id'] ?? 1,
                'centro_custo_id' => $_POST['centro_custo_id'],
                'descricao' => $_POST['descricao'],
                'valor' => $_POST['valor'],
                'data_lancamento' => $_POST['data_lancamento']
            ];
            $id = $this->costEntry->create($data);
            $this->logAction('concluido_lancamento_custo', 'lancamentos_custos', $id);
            setFlash('success', 'Lançamento de custo registrado!');
            $this->redirect('custos.php');
        }
    }

    public function relatorioMensal() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $mes = $_GET['mes'] ?? date('Y-m');
        $mesAnterior = date('Y-m', strtotime($mes . " -1 month"));

        $atual = $this->costEntry->getMonthlyTotals($filial_id, $mes);
        $anterior = $this->costEntry->getMonthlyTotals($filial_id, $mesAnterior);

        $this->render('custos/relatorio_mensal', [
            'atual' => $atual,
            'anterior' => $anterior,
            'mes' => $mes,
            'pageTitle' => 'Comparativo Mensal de Custos'
        ]);
    }

    public function margemProdutos() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $sql = "SELECT p.nome, p.preco_venda, p.preco_custo,
                ((p.preco_venda - p.preco_custo) / p.preco_venda * 100) as margem
                FROM produtos p
                WHERE p.filial_id = ? AND p.preco_venda > 0
                ORDER BY margem DESC";
        $produtos = $this->db->query($sql, [$filial_id])->fetchAll();

        $this->render('custos/margem_produtos', [
            'produtos' => $produtos,
            'pageTitle' => 'Margem de Lucro por Produto'
        ]);
    }

    public function rentabilidadeFilial() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $mes = $_GET['mes'] ?? date('Y-m');

        // Receita Total (Dinheiro + Outros no período)
        $sqlReceita = "SELECT SUM(valor_total) FROM vendas WHERE filial_id = ? AND status = 'concluido' AND data_venda LIKE ?";
        $receita = $this->db->query($sqlReceita, [$filial_id, $mes . '%'])->fetchColumn();

        // Custos do período
        $custos = $this->costEntry->getMonthlyTotals($filial_id, $mes);
        $totalCustos = 0;
        foreach ($custos as $c) $totalCustos += $c['total'];

        $lucroOperacional = $receita - $totalCustos;
        $percentual = $receita > 0 ? ($lucroOperacional / $receita * 100) : 0;

        $this->render('custos/rentabilidade', [
            'receita' => $receita,
            'totalCustos' => $totalCustos,
            'lucro' => $lucroOperacional,
            'percentual' => $percentual,
            'mes' => $mes,
            'pageTitle' => 'Rentabilidade Operacional da Unidade'
        ]);
    }

    protected function logAction(string $action, string $table = null, int $id = null) {
        $audit = new \App\Services\AuditLogService();
        $audit->record($action, $table, $id);
    }
}
