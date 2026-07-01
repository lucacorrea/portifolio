<?php

declare(strict_types=1);

/* (Opcional) guard de auth */
@require_once __DIR__ . '/auth/authGuard.php';
if (function_exists('auth_guard')) auth_guard();

/* ===== DEBUG (remova em produção) ===== */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ===== Timezone ===== */
date_default_timezone_set('America/Manaus');

/* ===== Conexão (PDO) ===== */
$pdo = null;

// 1) tenta padrão ANEXO: assets/conexao.php define $pdo
@require_once __DIR__ . '/assets/conexao.php';
if (isset($pdo) && $pdo instanceof PDO) {
    // ok
} else {
    // 2) tenta padrão SIGRelatórios: assets/php/conexao.php com db(): PDO
    @require_once __DIR__ . '/assets/php/conexao.php';
    if (function_exists('db')) {
        $pdo = db();
    }
}

if (!($pdo instanceof PDO)) {
    echo "<div style='padding:12px;background:#fee2e2;border:1px solid #fecaca;border-radius:10px;color:#7f1d1d;font-family:system-ui'>Erro: conexão PDO não encontrada.</div>";
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   HELPERS
   ========================= */
function mesesAteHoje(int $qtd = 12): array
{
    $hoje = new DateTime('first day of this month');
    $out = [];
    for ($i = $qtd - 1; $i >= 0; $i--) $out[] = (clone $hoje)->modify("-{$i} months");
    return $out;
}

function month_label_ptbr(DateTime $dt): string
{
    static $m = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return $m[(int)$dt->format('n') - 1];
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s) ?? '';
}

function mask_cpf(?string $cpf): string
{
    $d = only_digits($cpf);
    if (strlen($d) !== 11) return $cpf ? (string)$cpf : '—';
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

function group_assoc(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $k = (string)($r['k'] ?? '');
        $v = (int)($r['v'] ?? 0);
        $out[$k] = $v;
    }
    return $out;
}

/* =========================
   KPIs (dados reais)
   ========================= */
$incomeExpr = "COALESCE(NULLIF(s.renda_familiar,0), NULLIF(s.total_rendimentos,0), NULLIF(s.renda_individual,0), 0)";

$totalSolicitantes = (int)$pdo->query("SELECT COUNT(*) AS c FROM solicitantes")->fetchColumn();

$mediaRenda = (float)$pdo->query("
  SELECT AVG(NULLIF($incomeExpr,0)) AS m
  FROM solicitantes s
")->fetchColumn();
if (!is_finite($mediaRenda)) $mediaRenda = 0.0;

$idadeMedia = (float)$pdo->query("
  SELECT AVG(TIMESTAMPDIFF(YEAR, s.data_nascimento, CURDATE())) AS m
  FROM solicitantes s
  WHERE s.data_nascimento IS NOT NULL
")->fetchColumn();
if (!is_finite($idadeMedia)) $idadeMedia = 0.0;

$beneficiosQtd = (int)$pdo->query("
  SELECT COUNT(*) AS c
  FROM solicitantes s
  WHERE (
    (s.bpc IS NOT NULL AND TRIM(s.bpc) <> '' AND LOWER(TRIM(s.bpc)) NOT IN ('nao','não','0','n','false'))
    OR (s.pbf IS NOT NULL AND TRIM(s.pbf) <> '' AND LOWER(TRIM(s.pbf)) NOT IN ('nao','não','0','n','false'))
    OR (s.beneficio_municipal IS NOT NULL AND TRIM(s.beneficio_municipal) <> '' AND LOWER(TRIM(s.beneficio_municipal)) NOT IN ('nao','não','0','n','false'))
    OR (s.beneficio_estadual IS NOT NULL AND TRIM(s.beneficio_estadual) <> '' AND LOWER(TRIM(s.beneficio_estadual)) NOT IN ('nao','não','0','n','false'))
    OR (s.beneficio_semas IS NOT NULL AND TRIM(s.beneficio_semas) <> '' AND LOWER(TRIM(s.beneficio_semas)) NOT IN ('nao','não','0','n','false'))
  )
")->fetchColumn();

$percBeneficio = (100 * $beneficiosQtd) / max(1, $totalSolicitantes);

$mediaRenda_fmt    = number_format($mediaRenda, 2, ',', '.');
$idadeMedia_fmt    = number_format($idadeMedia, 1, ',', '.');
$percBeneficio_fmt = number_format($percBeneficio, 0, ',', '.') . '%';

/* =========================
   Séries dos Gráficos (reais)
   ========================= */
$meses = mesesAteHoje(12);
$labelsMes = array_map(fn($d) => month_label_ptbr($d), $meses);
$serieCadMes = array_fill(0, 12, 0);
$mapIdx = [];
foreach ($meses as $i => $d) $mapIdx[$d->format('Y-m')] = $i;

$ymStart = $meses[0]->format('Y-m-01 00:00:00');
$ymEndDt = (clone end($meses))->modify('last day of this month')->setTime(23, 59, 59);
$ymEnd = $ymEndDt->format('Y-m-d H:i:s');

$cadGroup = group_assoc($pdo, "
  SELECT DATE_FORMAT(COALESCE(s.created_at, s.updated_at), '%Y-%m') AS k, COUNT(*) AS v
  FROM solicitantes s
  WHERE COALESCE(s.created_at, s.updated_at) IS NOT NULL
    AND COALESCE(s.created_at, s.updated_at) BETWEEN :ini AND :fim
  GROUP BY k
  ORDER BY k
", [':ini' => $ymStart, ':fim' => $ymEnd]);

foreach ($cadGroup as $ym => $qt) {
    if (isset($mapIdx[$ym])) $serieCadMes[$mapIdx[$ym]] = (int)$qt;
}

/* Renda (faixas) */
$rendaFaixas = ['Até 600', '601–1200', '1201–2400', '2401–3800', '3801+'];
$rendaSeries = array_fill(0, count($rendaFaixas), 0);

$rendaRows = $pdo->query("
  SELECT $incomeExpr AS renda
  FROM solicitantes s
")->fetchAll();

foreach ($rendaRows as $r) {
    $v = (float)($r['renda'] ?? 0);
    $idx = match (true) {
        $v > 0 && $v <= 600  => 0,
        $v <= 1200 => 1,
        $v <= 2400 => 2,
        $v <= 3800 => 3,
        default    => 4,
    };
    $rendaSeries[$idx]++;
}

/* Renda mensal (faixa textual) — substitui a antiga “Escolaridade” */
$faixaMap = group_assoc($pdo, "
  SELECT
    COALESCE(NULLIF(TRIM(s.renda_mensal_faixa),''), 'Não informado') AS k,
    COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$faixaLabels = array_keys($faixaMap ?: ['Não informado' => 0]);
$faixaSeries = array_values($faixaMap ?: ['Não informado' => 0]);

/* Situação do Imóvel */
$sitMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.situacao_imovel),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$sitLabels = array_keys($sitMap ?: ['Não informado' => 0]);
$sitSeries = array_values($sitMap ?: ['Não informado' => 0]);

/* Tipo de moradia */
$morMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.tipo_moradia),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$moradiaLabels = array_keys($morMap ?: ['Não informado' => 0]);
$moradiaSeries = array_values($morMap ?: ['Não informado' => 0]);

/* Abastecimento */
$aguaMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.abastecimento),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$aguaLabels = array_keys($aguaMap ?: ['Não informado' => 0]);
$aguaSeries = array_values($aguaMap ?: ['Não informado' => 0]);

/* Esgoto */
$esgMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.esgoto),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$esgotoLabels = array_keys($esgMap ?: ['Não informado' => 0]);
$esgotoSeries = array_values($esgMap ?: ['Não informado' => 0]);

/* Iluminação */
$iluMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.iluminacao),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$iluLabels = array_keys($iluMap ?: ['Não informado' => 0]);
$iluSeries = array_values($iluMap ?: ['Não informado' => 0]);

/* Lixo */
$lixoMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.lixo),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$lixoLabels = array_keys($lixoMap ?: ['Não informado' => 0]);
$lixoSeries = array_values($lixoMap ?: ['Não informado' => 0]);

/* Entorno */
$entMap = group_assoc($pdo, "
  SELECT COALESCE(NULLIF(TRIM(s.entorno),''), 'Não informado') AS k, COUNT(*) AS v
  FROM solicitantes s
  GROUP BY k
  ORDER BY v DESC, k ASC
");
$entLabels = array_keys($entMap ?: ['Não informado' => 0]);
$entSeries = array_values($entMap ?: ['Não informado' => 0]);

/* Últimos cadastrados (3) — solicitantes NÃO tem email, então mostra CPF/telefone */
$ultimos = $pdo->query("
  SELECT s.id, s.nome, s.cpf, s.telefone, s.created_at
  FROM solicitantes s
  WHERE s.created_at IS NOT NULL
  ORDER BY s.created_at DESC
  LIMIT 3
")->fetchAll() ?: [];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

    <style>
        .avatar.icon-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #eef2f7;
            color: #0d6efd;
            display: grid;
            place-items: center;
            line-height: 0;
            flex: 0 0 48px
        }

        .avatar.icon-avatar i {
            display: block;
            font-size: 1.5rem
        }

        .stats-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            color: #fff;
            font-size: 22px
        }

        .stats-icon.purple {
            background: #6d5efc
        }

        .stats-icon.blue {
            background: #2d8cff
        }

        .stats-icon.green {
            background: #17c964
        }

        .stats-icon.red {
            background: #ff4d4f
        }

        .card.h-100 {
            height: 100%
        }

        .chart-box {
            min-height: 280px;
            width: 100%
        }

        .gap-row>[class*="col-"] {
            margin-bottom: 1rem
        }

        @media (min-width:1200px) {
            .chart-box {
                min-height: 320px
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo"><a href="#"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- MENU (ANEXO padrão) -->
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item active">
                            <a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="pessoasCadastradas.php">Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
                            </ul>
                        </li>

                        <?php
                        $role = $_SESSION['user_role'] ?? '';

                        if ($role === 'prefeito' || $role === 'secretario'):
                        ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-person-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item">
                                        <a href="usuariosPermitidos.php">Permitidos</a>
                                    </li>
                                    <li class="submenu-item">
                                        <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="sidebar-item">
                            <a href="../../gpsemas/index.php" class="sidebar-link"><i class="bi bi-map-fill"></i><span>Rastreamento</span></a>
                        </li>

                        <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'secretario'): ?>
                            <li class="sidebar-item">
                                <a href="../admin/index.php" class="sidebar-link" target="_blank" rel="noopener">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <span>Administrador</span>
                                </a>
                            </li>
                        <?php endif; ?>


                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
                        </li>
                    </ul>
                </div>
                <!-- /MENU -->

            </div>
        </div>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading">
                <h3>Dashboard Estatísticas</h3>
            </div>

            <div class="page-content">
                <section class="row">

                    <!-- KPIs -->
                    <div class="col-12 mb-3">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 kpi">

                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="stats-icon purple"><i class="iconly-boldUser"></i></div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Solicitantes Cadastrados</h6>
                                                <h6 class="mb-0"><?= number_format($totalSolicitantes, 0, ',', '.') ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="stats-icon blue"><i class="iconly-boldWallet"></i></div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Média Renda Familiar</h6>
                                                <h6 class="mb-0">R$ <?= $mediaRenda_fmt ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="stats-icon green"><i class="iconly-boldTime-Circle"></i></div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Idade Média do Responsável</h6>
                                                <h6 class="mb-0"><?= $idadeMedia_fmt ?> anos</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="stats-icon red"><i class="iconly-boldDiscount"></i></div>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">% com Benefício Social</h6>
                                                <h6 class="mb-0"><?= $percBeneficio_fmt ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- CONTEÚDO PRINCIPAL -->
                    <div class="col-12">
                        <div class="row gap-row">

                            <!-- Faixa de renda -->
                            <div class="col-12 col-xl-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Distribuição por Faixa de Renda</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-renda" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Renda mensal (faixa) -->
                            <div class="col-12 col-xl-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Renda Mensal (Faixa)</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-faixa" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Situação do Imóvel -->
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Situação do Imóvel</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-situacao" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tipo de moradia -->
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Tipo de Moradia</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-moradia" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Abastecimento água -->
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Abastecimento de Água</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-agua" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Esgotamento -->
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Esgotamento Sanitário</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-esgoto" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Iluminação pública -->
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Iluminação (Pública)</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-iluminacao" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Coleta de lixo -->
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Coleta de Lixo</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-lixo" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Características do entorno -->
                            <div class="col-12 col-md-12 col-xl-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Características do Entorno</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-entorno" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Últimas Pessoas Cadastradas -->
                            <div class="col-12 col-lg-4">
                                <div class="card d-flex flex-column h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Últimas Pessoas Cadastradas</h4>
                                    </div>
                                    <div class="card-content pb-4">
                                        <?php $icons = ['bi-person-circle', 'bi-person-fill', 'bi-people-fill'];
                                        $i = 0; ?>
                                        <?php foreach ($ultimos as $u): $icon = $icons[$i % count($icons)];
                                            $i++; ?>
                                            <div class="recent-message d-flex align-items-center px-3 py-2 mb-3 flex-wrap">
                                                <div class="avatar icon-avatar flex-shrink-0 mb-2 mb-sm-0"><i class="bi <?= h($icon) ?>"></i></div>
                                                <div class="name ms-sm-3 flex-grow-1 text-truncate">
                                                    <h6 class="mb-1 text-truncate"><?= h((string)($u['nome'] ?? '—')) ?></h6>
                                                    <small class="text-muted text-truncate d-block">
                                                        CPF: <?= h(mask_cpf((string)($u['cpf'] ?? ''))) ?> • Tel: <?= h((string)($u['telefone'] ?? '—')) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="px-3">
                                            <a href="pessoasCadastradas.php" class="btn btn-block btn-light-primary font-bold w-100 mt-2">Visualizar Todas</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cadastros por mês -->
                            <div class="col-12 col-lg-8">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h4 class="mb-0">Cadastros por Mês</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="chart-cad-mes" class="chart-box"></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </section>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = new Date().getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        const CAD_LABELS = <?= json_encode($labelsMes, JSON_UNESCAPED_UNICODE) ?>;
        const CAD_SERIE = <?= json_encode($serieCadMes, JSON_UNESCAPED_UNICODE) ?>;

        const RENDA_LABELS = <?= json_encode($rendaFaixas, JSON_UNESCAPED_UNICODE) ?>;
        const RENDA_SERIE = <?= json_encode($rendaSeries, JSON_UNESCAPED_UNICODE) ?>;

        const FAIXA_LABELS = <?= json_encode($faixaLabels, JSON_UNESCAPED_UNICODE) ?>;
        const FAIXA_SERIE = <?= json_encode($faixaSeries, JSON_UNESCAPED_UNICODE) ?>;

        const SIT_LABELS = <?= json_encode($sitLabels, JSON_UNESCAPED_UNICODE) ?>;
        const SIT_SERIE = <?= json_encode($sitSeries, JSON_UNESCAPED_UNICODE) ?>;

        const MOR_LABELS = <?= json_encode($moradiaLabels, JSON_UNESCAPED_UNICODE) ?>;
        const MOR_SERIE = <?= json_encode($moradiaSeries, JSON_UNESCAPED_UNICODE) ?>;

        const AGUA_LABELS = <?= json_encode($aguaLabels, JSON_UNESCAPED_UNICODE) ?>;
        const AGUA_SERIE = <?= json_encode($aguaSeries, JSON_UNESCAPED_UNICODE) ?>;

        const ESG_LABELS = <?= json_encode($esgotoLabels, JSON_UNESCAPED_UNICODE) ?>;
        const ESG_SERIE = <?= json_encode($esgotoSeries, JSON_UNESCAPED_UNICODE) ?>;

        const ILU_LABELS = <?= json_encode($iluLabels, JSON_UNESCAPED_UNICODE) ?>;
        const ILU_SERIE = <?= json_encode($iluSeries, JSON_UNESCAPED_UNICODE) ?>;

        const LIXO_LABELS = <?= json_encode($lixoLabels, JSON_UNESCAPED_UNICODE) ?>;
        const LIXO_SERIE = <?= json_encode($lixoSeries, JSON_UNESCAPED_UNICODE) ?>;

        const ENT_LABELS = <?= json_encode($entLabels, JSON_UNESCAPED_UNICODE) ?>;
        const ENT_SERIE = <?= json_encode($entSeries, JSON_UNESCAPED_UNICODE) ?>;

        /* Faixa de renda (bar) */
        new ApexCharts(document.querySelector("#chart-renda"), {
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Famílias',
                data: RENDA_SERIE
            }],
            xaxis: {
                categories: RENDA_LABELS
            },
            dataLabels: {
                enabled: false
            }
        }).render();

        /* Renda mensal (faixa) — bar horizontal */
        new ApexCharts(document.querySelector("#chart-faixa"), {
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true
                }
            },
            series: [{
                name: 'Pessoas',
                data: FAIXA_SERIE
            }],
            xaxis: {
                categories: FAIXA_LABELS
            },
            dataLabels: {
                enabled: false
            }
        }).render();

        /* Situação do Imóvel (donut) */
        new ApexCharts(document.querySelector("#chart-situacao"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: SIT_SERIE,
            labels: SIT_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Tipo de moradia (pie) */
        new ApexCharts(document.querySelector("#chart-moradia"), {
            chart: {
                type: 'pie',
                height: 280
            },
            series: MOR_SERIE,
            labels: MOR_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Abastecimento (donut) */
        new ApexCharts(document.querySelector("#chart-agua"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: AGUA_SERIE,
            labels: AGUA_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Esgoto (donut) */
        new ApexCharts(document.querySelector("#chart-esgoto"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: ESG_SERIE,
            labels: ESG_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Iluminação (donut) */
        new ApexCharts(document.querySelector("#chart-iluminacao"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: ILU_SERIE,
            labels: ILU_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Lixo (donut) */
        new ApexCharts(document.querySelector("#chart-lixo"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: LIXO_SERIE,
            labels: LIXO_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Entorno (donut) */
        new ApexCharts(document.querySelector("#chart-entorno"), {
            chart: {
                type: 'donut',
                height: 280
            },
            series: ENT_SERIE,
            labels: ENT_LABELS,
            legend: {
                position: 'bottom'
            }
        }).render();

        /* Cadastros por mês (area) */
        new ApexCharts(document.querySelector("#chart-cad-mes"), {
            chart: {
                type: 'area',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Cadastros',
                data: CAD_SERIE
            }],
            xaxis: {
                categories: CAD_LABELS
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            fill: {
                opacity: .35
            }
        }).render();
    </script>

    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>