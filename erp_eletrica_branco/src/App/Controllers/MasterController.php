<?php
namespace App\Controllers;

use App\Models\Filial;
use App\Models\Sale;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class MasterController extends BaseController {
    public function index() {
        $filialModel = new Filial();
        $saleModel = new Sale();
        
        $branches = $filialModel->all();
        $recentSales = $saleModel->getRecent(15);
        
        $billingByBranch = $this->db->query("
            SELECT f.nome, SUM(v.valor_total) as total 
            FROM vendas v 
            JOIN filiais f ON v.filial_id = f.id 
            WHERE v.status = 'concluido'
            GROUP BY v.filial_id
            ORDER BY total DESC
        ")->fetchAll();

        $this->render('master/dashboard', [
            'branches' => $branches,
            'recentSales' => $recentSales,
            'billingByBranch' => $billingByBranch,
            'title' => 'Painel Master Global',
            'pageTitle' => 'Visão Estratégica Multi-Unidades'
        ]);
    }

    public function permissions() {
        $niveis = ['vendedor', 'tecnico', 'gerente', 'admin'];
        $permissoes = $this->db->query("SELECT * FROM permissoes ORDER BY modulo, acao")->fetchAll();
        
        // Get mapping: level => [perm_id, perm_id, ...]
        $mapping = [];
        $res = $this->db->query("SELECT nivel, permissao_id FROM permissao_nivel")->fetchAll();
        foreach ($res as $row) {
            $mapping[$row['nivel']][] = $row['permissao_id'];
        }

        $this->render('master/permissions', [
            'niveis' => $niveis,
            'permissoes' => $permissoes,
            'mapping' => $mapping,
            'title' => 'Gestão de Permissões Global',
            'pageTitle' => 'Controle de Acessos RBAC'
        ]);
    }

    public function savePermissions() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->db->beginTransaction();
            try {
                // Clear all current level permissions (except master which is virtual all-access)
                $this->db->exec("DELETE FROM permissao_nivel WHERE nivel != 'master'");
                
                if (isset($_POST['perms'])) {
                    $stmt = $this->db->prepare("INSERT INTO permissao_nivel (nivel, permissao_id) VALUES (?, ?)");
                    foreach ($_POST['perms'] as $nivel => $perms) {
                        foreach ($perms as $permId) {
                            $stmt->execute([$nivel, $permId]);
                        }
                    }
                }
                
                $this->db->commit();
                $this->redirect('master.php?action=permissions&msg=Permissões atualizadas com sucesso');
            } catch (\Exception $e) {
                $this->db->rollBack();
                $this->redirect('master.php?action=permissions&msg=Erro ao salvar: ' . $e->getMessage());
            }
        }
    }

    public function dre() {
        $year = $_GET['year'] ?? date('Y');
        
        $receitas = $this->db->query("
            SELECT MONTH(data_venda) as mes, SUM(valor_total) as total 
            FROM vendas 
            WHERE status = 'concluido' AND YEAR(data_venda) = $year
            GROUP BY mes
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $despesas = $this->db->query("
            SELECT MONTH(data_pagamento) as mes, SUM(valor) as total 
            FROM contas_pagar 
            WHERE status = 'pago' AND YEAR(data_pagamento) = $year
            GROUP BY mes
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->render('master/dre', [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'year' => $year,
            'title' => 'DRE Global Consolidado',
            'pageTitle' => 'Demonstrativo de Resultados de Todas as Unidades'
        ]);
    }
}
