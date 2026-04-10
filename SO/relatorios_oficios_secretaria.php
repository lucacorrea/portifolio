<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
view_check();

$page_title = "Ofícios por Secretaria";

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_money')) {
    function format_money($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

$sec_id         = isset($_GET['sec_id']) ? (int)$_GET['sec_id'] : 0;
$forn_id        = isset($_GET['forn_id']) ? trim((string)$_GET['forn_id']) : '';
$produto        = isset($_GET['produto']) ? trim((string)$_GET['produto']) : '';
$periodo_inicio = isset($_GET['inicio']) ? trim((string)$_GET['inicio']) : '';
$periodo_fim    = isset($_GET['fim']) ? trim((string)$_GET['fim']) : '';

if ($sec_id <= 0) {
    die('Secretaria não informada.');
}

$stmtSec = $pdo->prepare("SELECT id, nome FROM secretarias WHERE id = ?");
$stmtSec->execute([$sec_id]);
$secretaria = $stmtSec->fetch(PDO::FETCH_ASSOC);

if (!$secretaria) {
    die('Secretaria não encontrada.');
}

$where = [];
$params = [];

$where[] = "o.secretaria_id = :sec_id";
$params[':sec_id'] = $sec_id;

if ($forn_id !== '') {
    $where[] = "a.fornecedor_id = :forn_id";
    $params[':forn_id'] = (int)$forn_id;
}

if ($produto !== '') {
    $where[] = "(ia.produto LIKE :produto OR io.produto LIKE :produto)";
    $params[':produto'] = '%' . $produto . '%';
}

if ($periodo_inicio !== '') {
    $where[] = "o.criado_em >= :inicio";
    $params[':inicio'] = $periodo_inicio . ' 00:00:00';
}

if ($periodo_fim !== '') {
    $where[] = "o.criado_em <= :fim";
    $params[':fim'] = $periodo_fim . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        o.id,
        o.numero,
        o.criado_em,
        COALESCE(f.nome, '-') AS fornecedor,
        COALESCE(SUM(io.quantidade), 0) AS quantidade_total,
        COALESCE(SUM(ia.quantidade * ia.valor_unitario), 0) AS valor_total
    FROM oficios o
    LEFT JOIN itens_oficio io ON io.oficio_id = o.id
    LEFT JOIN aquisicoes a ON a.oficio_id = o.id
    LEFT JOIN fornecedores f ON f.id = a.fornecedor_id
    LEFT JOIN itens_aquisicao ia ON ia.aquisicao_id = a.id
    WHERE $whereSql
    GROUP BY o.id, o.numero, o.criado_em, f.nome
    ORDER BY o.criado_em DESC, o.numero DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$oficios = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/layout/header.php';
?>

<style>
    .page-card {
        border: 1px solid #e9edf5;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .page-card+.page-card {
        margin-top: 1.25rem;
    }

    .header-flex {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .title-box {
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .title-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(32, 107, 196, 0.12);
        color: #206bc4;
    }

    .title-box h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .title-box p {
        margin: .15rem 0 0;
        color: #64748b;
        font-size: .88rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        border-radius: 12px;
        padding: .7rem 1rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        font-weight: 700;
        transition: .2s ease;
    }

    .btn-sm {
        padding: .62rem .95rem;
        font-size: .85rem;
    }

    .btn-outline {
        background: #fff;
        border-color: #dbe2ea;
        color: #334155;
    }

    .btn-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .btn-primary {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1a5aa8;
        border-color: #1a5aa8;
        color: #fff;
    }

    .table-wrap {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .table-scroll-x {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .table-scroll-x::-webkit-scrollbar {
        height: 10px;
    }

    .table-scroll-x::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .table-scroll-x::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .modern-table {
        width: 100%;
        min-width: 980px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .modern-table thead th {
        background: #f8fafc;
        color: #334155;
        font-size: .86rem;
        font-weight: 800;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem .9rem;
        white-space: nowrap;
    }

    .modern-table tbody td {
        padding: .95rem .9rem;
        border-bottom: 1px solid #edf2f7;
        background: #fff;
        color: #0f172a;
        white-space: nowrap;
        vertical-align: middle;
    }

    .modern-table tbody tr:hover>td {
        background: #fcfdff;
    }

    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    .text-nowrap {
        white-space: nowrap !important;
    }

    .oficio-number {
        font-weight: 800;
        color: #206bc4;
    }

    .badge-money {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .42rem .75rem;
        border-radius: 999px;
        background: rgba(25, 135, 84, 0.10);
        color: #157347;
        font-weight: 800;
        font-size: .82rem;
        white-space: nowrap;
    }

    .empty-state {
        text-align: center;
        padding: 2.4rem 1rem !important;
        color: #64748b !important;
    }

    @media (max-width: 768px) {
        .modern-table {
            min-width: 900px;
        }
    }

    @media print {

        .no-print,
        .btn {
            display: none !important;
        }

        .table-scroll-x {
            overflow: visible !important;
        }

        .modern-table {
            min-width: 0 !important;
            width: 100% !important;
        }

        .modern-table th,
        .modern-table td {
            white-space: normal !important;
        }
    }
</style>

<div class="page-card">
    <div class="card-body" style="padding:1.5rem;">
        <div class="header-flex">
            <div class="title-box">
                <div class="title-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div>
                    <h3>Ofícios da Secretaria</h3>
                    <p><?php echo h($secretaria['nome']); ?></p>
                </div>
            </div>

            <div class="no-print">
                <a
                    href="relatorios.php"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div class="table-wrap">
            <div class="table-scroll-x">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Número do Ofício</th>
                            <th class="text-nowrap">Fornecedor</th>
                            <th class="text-right text-nowrap">Quantidade</th>
                            <th class="text-right text-nowrap">Valor Total</th>
                            <th class="text-nowrap">Data</th>
                            <th class="text-center text-nowrap" style="width:140px;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($oficios)): ?>
                            <?php foreach ($oficios as $of): ?>
                                <tr>
                                    <td class="oficio-number text-nowrap"><?php echo h($of['numero']); ?></td>
                                    <td class="text-nowrap"><?php echo h($of['fornecedor']); ?></td>
                                    <td class="text-right text-nowrap"><?php echo number_format((float)$of['quantidade_total'], 2, ',', '.'); ?></td>
                                    <td class="text-right text-nowrap">
                                        <span class="badge-money"><?php echo format_money($of['valor_total']); ?></span>
                                    </td>
                                    <td class="text-nowrap"><?php echo !empty($of['criado_em']) ? date('d/m/Y H:i', strtotime($of['criado_em'])) : '-'; ?></td>
                                    <td class="text-center text-nowrap">
                                        <a
                                            href="relatorios_oficio_detalhes.php?id=<?php echo (int)$of['id']; ?>"
                                            class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    Nenhum ofício encontrado para esta secretaria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>