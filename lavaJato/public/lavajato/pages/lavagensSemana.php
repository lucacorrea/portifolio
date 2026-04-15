<?php
// autoErp/public/lavajato/pages/lavagensSemana.php
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

/* ==== Controller semanal real ==== */
$ctrlSemana = __DIR__ . '/../controllers/lavagensSemanaController.php';
if (!file_exists($ctrlSemana)) {
    http_response_code(500);
    die('Controller semanal não encontrado.');
}
require_once $ctrlSemana;

/* ==== Inputs ==== */
$weekRef = trim((string)($_GET['week_ref'] ?? ''));
$ini     = trim((string)($_GET['ini'] ?? ''));
$fim     = trim((string)($_GET['fim'] ?? ''));
$q       = trim((string)($_GET['q'] ?? ''));

if ($weekRef === '' && ($ini === '' || $fim === '')) {
    header('Location: lavagens.php?msg=Semana%20n%C3%A3o%20informada.&err=1');
    exit;
}

try {
    if (!function_exists('lavagens_semana_por_lavador_viewmodel')) {
        throw new RuntimeException('Função lavagens_semana_por_lavador_viewmodel() não encontrada.');
    }

    $args = ['q' => $q];
    if ($weekRef !== '') {
        $args['week_ref'] = $weekRef;
    } else {
        $args['ini'] = $ini;
        $args['fim'] = $fim;
    }

    $vm = lavagens_semana_por_lavador_viewmodel($pdo, $args);
} catch (Throwable $e) {
    $vm = [
        'ok' => false,
        'err' => true,
        'msg' => 'Erro: ' . $e->getMessage(),
        'week_ref' => $weekRef,
        'periodo_ini' => '',
        'periodo_fim' => '',
        'ini' => $ini,
        'fim' => $fim,
        'periodo_label' => '',
        'lavadores' => [],
        'detalhe' => null,
        'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
        'q' => $q,
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

$backLista = function () use ($q) {
    $args = ['range' => '365'];
    if ($q !== '') {
        $args['q'] = $q;
    }
    return 'lavagens.php?' . http_build_query($args);
};

$weekRefOut = (string)($vm['week_ref'] ?? $weekRef);
$iniOut     = (string)($vm['ini'] ?? $ini);
$fimOut     = (string)($vm['fim'] ?? $fim);
$periodoLabel = (string)($vm['periodo_label'] ?? '');
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP — Semana <?= h($periodoLabel !== '' ? $periodoLabel : ($iniOut . ' – ' . $fimOut)) ?></title>

    <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/icon.png">
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">
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
    $menuAtivo = 'lavagensSemana';
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
                        <a href="<?= h($backLista()) ?>" class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height:150px; margin-bottom:50px;">
                <div class="container-fluid iq-container">
                    <h1 class="mb-0">Lavadores da Semana</h1>
                    <p>
                        <strong><?= h($periodoLabel !== '' ? $periodoLabel : ($iniOut . ' – ' . $fimOut)) ?></strong>
                    </p>

                    <?php if (!empty($vm['msg'])): ?>
                        <div class="mt-2">
                            <div class="alert alert-<?= !empty($vm['err']) ? 'danger' : 'success' ?> py-2 mb-0">
                                <?= h((string)$vm['msg']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="iq-header-img">
                    <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100" alt="">
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
                                    <h4 class="card-title mb-1">Resumo da semana por lavador</h4>
                                    <div class="text-muted small">
                                        Consulte quantidade de lavagens e faturamento por lavador dentro da semana real.
                                    </div>
                                </div>

                                <form method="get" action="lavagensSemana.php" class="d-flex gap-2 flex-wrap">
                                    <?php if ($weekRefOut !== ''): ?>
                                        <input type="hidden" name="week_ref" value="<?= h($weekRefOut) ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="ini" value="<?= h($iniOut) ?>">
                                        <input type="hidden" name="fim" value="<?= h($fimOut) ?>">
                                    <?php endif; ?>

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
                                        <?php
                                        $clearArgs = [];
                                        if ($weekRefOut !== '') {
                                            $clearArgs['week_ref'] = $weekRefOut;
                                        } else {
                                            $clearArgs['ini'] = $iniOut;
                                            $clearArgs['fim'] = $fimOut;
                                        }
                                        $clearUrl = 'lavagensSemana.php?' . http_build_query($clearArgs);
                                        ?>
                                        <a href="<?= h($clearUrl) ?>" class="btn btn-sm btn-outline-secondary">
                                            Limpar
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="d-flex flex-wrap gap-2 mb-3">
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
                                    Total da semana: <strong><?= money((float)($vm['resumo']['total'] ?? 0)) ?></strong>
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Lavador</th>
                                            <th class="text-center" style="width: 140px;">Lavagens</th>
                                            <th class="text-end" style="width: 160px;">Total</th>
                                            <th class="text-end" style="width: 140px;">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($vm['lavadores'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    Nenhum lavador encontrado nesta semana.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($vm['lavadores'] as $row): ?>
                                                <?php
                                                $resumoArgs = [
                                                    'lav' => (string)$row['lav_key'],
                                                ];
                                                if ($weekRefOut !== '') {
                                                    $resumoArgs['week_ref'] = $weekRefOut;
                                                } else {
                                                    $resumoArgs['ini'] = $iniOut;
                                                    $resumoArgs['fim'] = $fimOut;
                                                }
                                                if ($q !== '') {
                                                    $resumoArgs['q'] = $q;
                                                }
                                                $resumoUrl = 'lavagensResumo.php?' . http_build_query($resumoArgs);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= h((string)$row['lavador']) ?></div>
                                                        <div class="small text-muted"><?= h((string)$row['lav_key']) ?></div>
                                                    </td>
                                                    <td class="text-center"><?= (int)($row['qtd'] ?? 0) ?></td>
                                                    <td class="text-end"><?= money((float)($row['total'] ?? 0)) ?></td>
                                                    <td class="text-end">
                                                        <a href="<?= h($resumoUrl) ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Ver resumo
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
                <div class="left-panel">© <script>
                        document.write(new Date().getFullYear())
                    </script> <?= htmlspecialchars((string)$empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="right-panel">Desenvolvido por L&J Soluções Tecnológicas.</div>
            </div>
        </footer>
    </main>

    <script src="../../assets/js/core/libs.min.js"></script>
    <script src="../../assets/js/core/external.min.js"></script>
    <script src="../../assets/vendor/aos/dist/aos.js"></script>
    <script src="../../assets/js/hope-ui.js" defer></script>
</body>

</html>