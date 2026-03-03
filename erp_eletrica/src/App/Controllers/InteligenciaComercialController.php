<?php
namespace App\Controllers;

use App\Models\Intelligence;
use App\Models\Product;
use App\Services\AuthService;

class InteligenciaComercialController extends BaseController {
    private $intelligence;

    public function __construct() {
        AuthService::checkPermission('inteligencia', 'visualizar');
        $this->intelligence = new Intelligence();
    }

    public function topProdutos() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $inicio = $_GET['inicio'] ?? date('Y-m-01');
        $fim = $_GET['fim'] ?? date('Y-m-d');
        
        $produtos = $this->intelligence->getTopProducts($filial_id, $inicio, $fim);

        $this->render('inteligencia/top_produtos', [
            'produtos' => $produtos,
            'inicio' => $inicio,
            'fim' => $fim,
            'pageTitle' => 'Top 10 Produtos Mais Vendidos'
        ]);
    }

    public function produtosEncalhados() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $dias = $_GET['dias'] ?? 60;
        
        $produtos = $this->intelligence->getStagnantProducts($filial_id, (int)$dias);

        $this->render('inteligencia/encalhados', [
            'produtos' => $produtos,
            'dias' => $dias,
            'pageTitle' => 'Produtos sem Giro (Encalhados)'
        ]);
    }

    public function curvaABC() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $mes_referencia = $_GET['periodo'] ?? date('Y-m');
        
        $sql = "SELECT p.nome, abc.classificacao, p.estoque
                FROM produto_curva_abc abc
                JOIN produtos p ON abc.produto_id = p.id
                WHERE abc.filial_id = ? AND abc.periodo_referencia = ?
                ORDER BY abc.classificacao ASC";
        $dados = $this->db->query($sql, [$filial_id, $mes_referencia])->fetchAll();

        $this->render('inteligencia/curva_abc', [
            'dados' => $dados,
            'periodo' => $mes_referencia,
            'pageTitle' => 'Análise de Curva ABC (Faturamento)'
        ]);
    }

    public function recalcularCurvaABC() {
        AuthService::checkPermission('inteligencia', 'recalcular');
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $mes = date('Y-m');
        $inicio = date('Y-m-01', strtotime("-1 month"));
        $fim = date('Y-m-t', strtotime("-1 month"));

        $calculo = $this->intelligence->calculateABC($filial_id, $inicio, $fim);
        
        foreach ($calculo as $item) {
            $sql = "INSERT INTO produto_curva_abc (produto_id, filial_id, classificacao, periodo_referencia)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE classificacao = VALUES(classificacao)";
            $this->db->query($sql, [$item['produto_id'], $filial_id, $item['classificacao'], $mes]);
        }

        $audit = new \App\Services\AuditLogService();
        $audit->record('recalculo_abc', 'produto_curva_abc');

        setFlash('success', 'Curva ABC recalculada com base no faturamento do mês anterior.');
        $this->redirect('inteligencia.php?action=curvaABC');
    }

    public function sugestaoReposicao() {
        $filial_id = $_SESSION['filial_id'] ?? 1;
        $sugestoes = $this->intelligence->getReplenishmentAlerts($filial_id);

        // Opcional: Persistir alertas se não existirem
        foreach ($sugestoes as $s) {
            $msg = "O estoque de {$s['nome']} ({$s['estoque']}) está abaixo da média mensal de vendas (" . round($s['media_mensal'], 2) . "). Sugerimos reposição.";
            $sql = "INSERT INTO alertas_estoque (produto_id, filial_id, tipo, mensagem) VALUES (?, ?, 'reposicao', ?)
                    ON DUPLICATE KEY UPDATE mensagem = VALUES(mensagem)";
            $this->db->query($sql, [$s['id'], $filial_id, $msg]);
        }

        $this->render('inteligencia/reposicao', [
            'sugestoes' => $sugestoes,
            'pageTitle' => 'Sugestões Automáticas de Reposição'
        ]);
    }
}
