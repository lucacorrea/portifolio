<?php
declare(strict_types=1);
namespace Sigesp\Modules\Dashboard\Controllers;
use Sigesp\Core\{Controller,Database,Request,Response};
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('atletas.visualizar'); $db = Database::connection();
        $stats = ['total' => (int) $db->query('SELECT COUNT(*) FROM atletas WHERE deleted_at IS NULL')->fetchColumn(), 'ativos' => (int) $db->query("SELECT COUNT(*) FROM atletas WHERE status='ativo' AND deleted_at IS NULL")->fetchColumn(), 'pendentes' => (int) $db->query("SELECT COUNT(*) FROM atletas_documentos WHERE status IN ('pendente','em_analise')")->fetchColumn(), 'modalidades' => (int) $db->query("SELECT COUNT(*) FROM modalidades WHERE status='ativo'")->fetchColumn()];
        $chart = $db->query("SELECT m.nome, COUNT(am.atleta_id) total FROM modalidades m LEFT JOIN atletas_modalidades am ON am.modalidade_id=m.id AND am.ativo=1 WHERE m.status='ativo' GROUP BY m.id,m.nome ORDER BY total DESC")->fetchAll();
        return $this->render('dashboard/index', compact('stats', 'chart'));
    }
}
