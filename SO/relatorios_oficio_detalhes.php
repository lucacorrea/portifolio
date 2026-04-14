<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
view_check();

$page_title = "Detalhes do Ofício";

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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Ofício inválido.');
}

$sqlOficio = "
    SELECT
        o.id,
        o.numero,
        o.justificativa,
        o.status,
        o.criado_em,
        s.nome AS secretaria_nome,
        u.nome AS usuario_nome,
        COALESCE(f.nome, '-') AS fornecedor,

        -- valor correto
        (
            SELECT COALESCE(SUM(ia2.quantidade * ia2.valor_unitario), 0)
            FROM aquisicoes a2
            LEFT JOIN itens_aquisicao ia2 ON ia2.aquisicao_id = a2.id
            WHERE a2.oficio_id = o.id
        ) AS valor_total_aquisicao

    FROM oficios o
    INNER JOIN secretarias s ON s.id = o.secretaria_id
    LEFT JOIN usuarios u ON u.id = o.usuario_id
    LEFT JOIN aquisicoes a ON a.oficio_id = o.id
    LEFT JOIN fornecedores f ON f.id = a.fornecedor_id

    WHERE o.id = ?
    LIMIT 1
";

$stmtOficio = $pdo->prepare($sqlOficio);
$stmtOficio->execute([$id]);
$oficio = $stmtOficio->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die('Ofício não encontrado.');
}

$sqlItens = "
    SELECT
        id,
        produto,
        quantidade,
        unidade
    FROM itens_oficio
    WHERE oficio_id = ?
    ORDER BY id ASC
";

$stmtItens = $pdo->prepare($sqlItens);
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

$totalQuantidade = 0;
foreach ($itens as $it) {
    $totalQuantidade += (float)$it['quantidade'];
}

$status = strtoupper((string)($oficio['status'] ?? 'ENVIADO'));
$statusClass = 'status-enviado';
if ($status === 'APROVADO') {
    $statusClass = 'status-aprovado';
} elseif ($status === 'REPROVADO') {
    $statusClass = 'status-reprovado';
} elseif ($status === 'ARQUIVADO') {
    $statusClass = 'status-arquivado';
}

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

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .34rem .68rem;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .status-enviado {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .status-aprovado {
        background: #dcfce7;
        color: #15803d;
    }

    .status-reprovado {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-arquivado {
        background: #e5e7eb;
        color: #4b5563;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .info-card {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        padding: 1rem;
        background: #fff;
    }

    .info-label {
        margin: 0 0 .35rem;
        font-size: .78rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .35px;
    }

    .info-value {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        word-break: break-word;
    }

    .just-card {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        padding: 1rem;
    }

    .just-title {
        margin: 0 0 .45rem;
        font-size: .86rem;
        font-weight: 800;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: .35px;
    }

    .just-text {
        color: #334155;
        line-height: 1.6;
        white-space: pre-line;
        word-break: break-word;
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
        min-width: 760px;
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

    .modern-table tbody td,
    .modern-table tfoot td {
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

    .produto-name {
        font-weight: 800;
        color: #206bc4;
    }

    .tfoot-row td {
        background: #f8fafc !important;
        font-weight: 800;
    }

    .empty-state {
        text-align: center;
        padding: 2.4rem 1rem !important;
        color: #64748b !important;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }

        .modern-table {
            min-width: 720px;
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
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <h3>Detalhes do Ofício</h3>
                    <p><?php echo h($oficio['numero']); ?></p>
                </div>
            </div>

            <div class="no-print">
                <a
                    href="relatorios_oficios_secretaria.php?sec_id=<?php echo urlencode((string)($oficio['id'] ? 0 : 0)); ?>"
                    onclick="history.back(); return false;"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div class="info-grid" style="margin-bottom:1rem;">
            <div class="info-card">
                <p class="info-label">Número do Ofício</p>
                <p class="info-value"><?php echo h($oficio['numero']); ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Status</p>
                <p class="info-value">
                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($status); ?></span>
                </p>
            </div>

            <div class="info-card">
                <p class="info-label">Secretaria</p>
                <p class="info-value"><?php echo h($oficio['secretaria_nome']); ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Fornecedor</p>
                <p class="info-value"><?php echo h($oficio['fornecedor']); ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Data</p>
                <p class="info-value"><?php echo !empty($oficio['criado_em']) ? date('d/m/Y H:i', strtotime($oficio['criado_em'])) : '-'; ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Usuário</p>
                <p class="info-value"><?php echo h($oficio['usuario_nome'] ?: '-'); ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Quantidade Total</p>
                <p class="info-value"><?php echo number_format((int)$totalQuantidade, 0, ',', '.'); ?></p>
            </div>

            <div class="info-card">
                <p class="info-label">Valor Total</p>
                <p class="info-value"><?php echo format_money($oficio['valor_total_aquisicao']); ?></p>
            </div>
        </div>

        <div class="just-card" style="margin-bottom:1rem;">
            <p class="just-title">Justificativa</p>
            <div class="just-text"><?php echo h($oficio['justificativa'] !== null && $oficio['justificativa'] !== '' ? $oficio['justificativa'] : 'Sem justificativa informada.'); ?></div>
        </div>

        <div class="table-wrap">
            <div class="table-scroll-x">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Produto</th>
                            <th class="text-right text-nowrap">Quantidade</th>
                            <th class="text-nowrap">Unidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($itens)): ?>
                            <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td class="produto-name text-nowrap"><?php echo h($item['produto']); ?></td>
                                    <td class="text-right text-nowrap"><?php echo number_format((int)$item['quantidade'], 0, ',', '.'); ?></td>
                                    <td class="text-nowrap"><?php echo h($item['unidade'] ?: 'UN'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">
                                    Nenhum item encontrado para este ofício.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($itens)): ?>
                        <tfoot>
                            <tr class="tfoot-row">
                                <td>TOTAL</td>
                                <td class="text-right"><?php echo number_format((int)$totalQuantidade, 0, ',', '.'); ?>
                                </td>
                                <td>-</td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>