<?php
// autoErp/public/lavajato/pages/lavagensMes.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ==== Conexão ==== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) {
    require_once $pathCon;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Conexão indisponível.');
}

require_once __DIR__ . '/../../../lib/util.php';
$empresaNome = empresa_nome_logada($pdo);

/* ==== Controller DIA ==== */
$ctrlDia = __DIR__ . '/../controllers/lavagensDiaController.php';
$ctrlAntigo = __DIR__ . '/../controllers/lavagensController.php';

if (file_exists($ctrlDia)) {
    require_once $ctrlDia;
} elseif (file_exists($ctrlAntigo)) {
    require_once $ctrlAntigo;
} else {
    http_response_code(500);
    die('Controller não encontrado.');
}

/* ==== Inputs ==== */
$mes = isset($_GET['mes']) ? (string)$_GET['mes'] : '';
$q   = trim((string)($_GET['q'] ?? ''));

if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
    header('Location: lavagens.php?msg=Par%C3%A2metro%20de%20m%C3%AAs%20inv%C3%A1lido.&err=1');
    exit;
}

try {
    if (!function_exists('lavagens_mes_por_dia_viewmodel')) {
        throw new RuntimeException('Função lavagens_mes_por_dia_viewmodel() não encontrada.');
    }

    $vm = lavagens_mes_por_dia_viewmodel($pdo, [
        'mes' => $mes,
        'q'   => $q,
    ]);
} catch (Throwable $e) {
    $vm = [
        'ok' => false,
        'err' => true,
        'msg' => 'Erro: ' . $e->getMessage(),
        'dias' => [],
        'resumo' => [
            'qtd' => 0,
            'total' => 0.0,
            'lavadores' => 0,
        ],
    ];
}

/* ==== Helpers ==== */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

$labelMes = function (string $ym): string {
    [$y, $m] = explode('-', $ym);
    $meses = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return ($meses[(int)$m] ?? $ym) . '/' . $y;
};

$backToLista = function () use ($q) {
    $args = ['range' => '365'];
    if ($q !== '') {
        $args['q'] = $q;
    }
    return 'lavagens.php?' . http_build_query($args);
};

$currentUrl = function () use ($mes) {
    return 'lavagensMes.php?mes=' . urlencode($mes);
};
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP — Lavagens de <?= h($labelMes($mes)) ?></title>

    <link rel="icon" type="image/png" href="../../assets/images/dashboard/icon.png">
    <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
    <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
    <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
    <link rel="stylesheet" href="../../assets/css/dark.min.css">
    <link rel="stylesheet" href="../../assets/css/customizer.min.css">
    <link rel="stylesheet" href="../../assets/css/customizer.css">
    <link rel="stylesheet" href="../../assets/css/rtl.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        .table thead th {
            white-space: nowrap;
        }

        .search-input-compact {
            max-width: 360px;
        }

        .stat {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .75rem;
            border-radius: 999px;
            background: #f3f6fb;
            border: 1px solid #e8eef7;
            font-weight: 600;
        }

        .stat i {
            opacity: .9;
        }

        .card-soft {
            border: 1px solid #edf1f7;
            box-shadow: 0 8px 24px rgba(18, 38, 63, .04);
        }
    </style>
</head>

<body>
    <?php
    $menuAtivo = 'lavagensMes';
    include '../../layouts/sidebar.php';
    ?>

    <main class="main-content">
        <div class="position-relative iq-banner">
            <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="../../dashboard.php" class="navbar-brand">
                        <h4 class="logo-title">AutoERP</h4>
                    </a>

                    <div class="ms-auto d-flex align-items-center gap-2">
                        <a href="<?= h($backToLista()) ?>" class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
                <div class="container-fluid iq-container">
                    <h1 class="mb-0">Lavagens de <?= h($labelMes($mes)) ?></h1>
                    <p>Resumo diário do mês, com total de lavagens e quantidade de lavadores por dia.</p>

                    <?php if (!empty($vm['msg'])): ?>
                        <div class="mt-2">
                            <div class="alert alert-<?= !empty($vm['err']) ? 'danger' : 'success' ?> py-2 mb-0">
                                <?= h((string)$vm['msg']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="iq-header-img">
                    <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100 animated-scaleX" alt="">
                </div>
            </div>
        </div>

        <div class="container-fluid content-inner mt-n3 py-0">
            <div class="row">
                <div class="col-12">

                    <div class="card card-soft">
                        <div class="card-header">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <h4 class="card-title mb-1">Dias do mês</h4>
                                    <div class="text-muted small">
                                        Visualize cada dia com total de lavagens, quantidade de lavadores e faturamento.
                                    </div>
                                </div>

                                <form method="get" action="<?= h($currentUrl()) ?>" class="d-flex gap-2">
                                    <input type="hidden" name="mes" value="<?= h($mes) ?>">
                                    <input
                                        type="text"
                                        name="q"
                                        value="<?= h($q) ?>"
                                        class="form-control form-control-sm search-input-compact"
                                        placeholder="Buscar por lavador, CPF, placa, modelo ou serviço">
                                    <button class="btn btn-sm btn-primary">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <?php if ($q !== ''): ?>
                                        <a href="<?= h($currentUrl()) ?>" class="btn btn-sm btn-outline-secondary">
                                            Limpar
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="stat">
                                    <i class="bi bi-calendar-event"></i>
                                    Dias com movimento: <strong><?= count((array)($vm['dias'] ?? [])) ?></strong>
                                </span>
                                <span class="stat">
                                    <i class="bi bi-cart-check"></i>
                                    Lavagens: <strong><?= (int)($vm['resumo']['qtd'] ?? 0) ?></strong>
                                </span>
                                <span class="stat">
                                    <i class="bi bi-people"></i>
                                    Lavadores: <strong><?= (int)($vm['resumo']['lavadores'] ?? 0) ?></strong>
                                </span>
                                <span class="stat">
                                    <i class="bi bi-cash-coin"></i>
                                    Total do mês: <strong><?= money((float)($vm['resumo']['total'] ?? 0)) ?></strong>
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Dia</th>
                                            <th class="text-center" style="width: 140px;">Lavagens</th>
                                            <th class="text-center" style="width: 140px;">Lavadores</th>
                                            <th class="text-end" style="width: 160px;">Faturado</th>
                                            <th class="text-end" style="width: 140px;">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($vm['dias'])): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    Nenhuma lavagem encontrada neste mês.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($vm['dias'] as $diaRow): ?>
                                                <?php
                                                $viewArgs = [
                                                    'mes' => $mes,
                                                    'dia' => (string)$diaRow['dia'],
                                                ];
                                                if ($q !== '') {
                                                    $viewArgs['q'] = $q;
                                                }
                                                $diaUrl = 'lavagensDia.php?' . http_build_query($viewArgs);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= h((string)$diaRow['label']) ?></div>
                                                        <div class="small text-muted"><?= h((string)$diaRow['dia']) ?></div>
                                                    </td>
                                                    <td class="text-center"><?= (int)($diaRow['qtd'] ?? 0) ?></td>
                                                    <td class="text-center"><?= (int)($diaRow['lavadores'] ?? 0) ?></td>
                                                    <td class="text-end"><?= money((float)($diaRow['total'] ?? 0)) ?></td>
                                                    <td class="text-end">
                                                        <a href="<?= h($diaUrl) ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Ver dia
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="footer-body d-flex justify-content-between align-items-center">
                <div class="left-panel">
                    © <script>document.write(new Date().getFullYear())</script> <?= h((string)$empresaNome) ?>
                </div>
                <div class="right-panel">
                    Desenvolvido por Lucas de S. Correa.
                </div>
            </div>
        </footer>
    </main>

    <script src="../../assets/js/core/libs.min.js"></script>
    <script src="../../assets/js/core/external.min.js"></script>
    <script src="../../assets/vendor/aos/dist/aos.js"></script>
    <script src="../../assets/js/hope-ui.js" defer></script>
</body>

</html>