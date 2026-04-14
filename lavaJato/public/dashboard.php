<?php
// autoErp/public/dashboard.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/auth_guard.php';
ensure_logged_in(['dono', 'funcionario']);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===========================================================
   Sessão
   =========================================================== */
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$cpfSess  = preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? ''));

/* ===========================================================
   Conexão
   =========================================================== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) require_once $pathConexao;
if (!($pdo instanceof PDO)) die('Conexão indisponível.');

/* ===========================================================
   Controller opcional
   =========================================================== */
$empresaNome = $empresaNome ?? '';
$ctrl = __DIR__ . '/controllers/dashboardController.php';
if (is_file($ctrl)) require_once $ctrl;

/* ===========================================================
   Guarda/empresa
   =========================================================== */
$empresaPendente = false;
$msgCompletar = '';
$canEditEmpresa = (($_SESSION['user_perfil'] ?? '') === 'dono');
$empresaRow = null;
try {
    if (!empty($cnpjSess)) {
        $st = $pdo->prepare("SELECT * FROM empresas_peca WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','')=:c LIMIT 1");
        $st->execute([':c' => $cnpjSess]);
        $empresaRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) {
    $empresaRow = null;
}

$camposObrigatorios = ['nome_fantasia' => 'Nome Fantasia', 'email' => 'E-mail', 'telefone' => 'Telefone', 'endereco' => 'Endereço', 'cidade' => 'Cidade', 'estado' => 'UF', 'cep' => 'CEP'];
$faltando = [];
if (!$empresaRow) {
    $empresaPendente = true;
    $msgCompletar = 'Sua empresa ainda não está cadastrada. Complete as informações para aproveitar todos os recursos.';
} else {
    foreach ($camposObrigatorios as $k => $rot) {
        $val = trim((string)($empresaRow[$k] ?? ''));
        if ($val === '') $faltando[] = $rot;
    }
    if ($faltando) {
        $empresaPendente = true;
        $msgCompletar = 'Algumas informações da empresa estão faltando: ' . implode(', ', $faltando) . '.';
    }
}

/* ===========================================================
   Cabeçalho
   =========================================================== */
$nomeUser     = $_SESSION['user_nome']         ?? ($nomeUser     ?? 'Usuário');
$empresaNome  = $empresaNome                   ?? ($_SESSION['empresa_nome'] ?? 'sua empresa');
$perfil       = strtolower($_SESSION['user_perfil'] ?? 'funcionario');
$tipo         = strtolower($_SESSION['user_tipo']   ?? '');

$rotTipo = ['administrativo' => 'Administrativo', 'caixa' => 'Caixa', 'estoque' => 'Estoque', 'lavajato' => 'Lava Jato'];
$tipoLabel = $rotTipo[$tipo] ?? 'Colaborador';
$fraseHeader = $perfil === 'dono'
    ? 'Você é o dono. Gerencie sua empresa, cadastre sua equipe e mantenha tudo em dia.'
    : "Você está logado como {$tipoLabel}. " . (
        $tipo === 'administrativo' ? 'Acompanhe o financeiro, cadastre produtos e dê suporte à operação.' : ($tipo === 'caixa' ? 'Abra vendas rápidas, finalize pagamentos e agilize o atendimento.' : ($tipo === 'estoque' ? 'Gerencie entradas e saídas, controle níveis e mantenha o estoque organizado.' : ($tipo === 'lavajato' ? 'Registre lavagens, acompanhe status e mantenha o fluxo do box.' :
            'Bem-vindo ao sistema. Use o menu ao lado para começar.')))
    );

/* ===========================================================
   EXPRESSÕES SQL (campos corretos da DDL)
   - Data: v.criado_em (timestamp) -> DATE()
   - Valor: v.total_liquido; fallback soma de itens se vier 0/NULL
   - Status válido para faturamento: 'fechada'
   =========================================================== */
$DATE_NORM = "DATE(v.criado_em)";
$TOTAL_NUM = "CAST(
  CASE
    WHEN v.total_liquido IS NOT NULL AND v.total_liquido > 0
      THEN v.total_liquido
    ELSE (
      SELECT COALESCE(SUM(vi.valor_total),0)
      FROM venda_itens_peca vi
      WHERE vi.venda_id = v.id
    )
  END AS DECIMAL(15,2)
)";
$CNPJ_NORM = "REPLACE(REPLACE(REPLACE(v.empresa_cnpj,'.',''),'-',''),'/','')";

/* ===========================================================
   Séries do gráfico + cálculo do card Faturamento (30 dias)
   =========================================================== */
$dailyLabels = [];
$dailySeries = [];
$monthlyLabels = [];
$monthlySeries = [];
$faturamento30d_calc = 0.0;

try {
    if (!empty($cnpjSess)) {

        // --------- DIÁRIA (30 dias) ---------
        $dias = [];
        $hoje = new DateTime('today');
        for ($i = 29; $i >= 0; $i--) {
            $d = (clone $hoje)->modify("-{$i} days");
            $dias[$d->format('Y-m-d')] = 0.0;
        }

        $sqlDia = "
      SELECT $DATE_NORM AS dia, SUM($TOTAL_NUM) AS total
      FROM vendas_peca v
      WHERE $CNPJ_NORM = :c
        AND $DATE_NORM >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND v.status = 'fechada'
      GROUP BY $DATE_NORM
      ORDER BY $DATE_NORM
    ";
        $st = $pdo->prepare($sqlDia);
        $st->execute([':c' => $cnpjSess]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = (string)$r['dia'];
            $dias[$k] = (float)($r['total'] ?? 0);
        }

        $acum = 0.0;
        foreach ($dias as $k => $v) {
            $d = DateTime::createFromFormat('Y-m-d', $k);
            $dailyLabels[] = $d ? $d->format('d/m') : $k;
            $acum += $v;
            $dailySeries[] = $acum;
        }
        $faturamento30d_calc = $acum;

        // --------- MENSAL (12 meses) ---------
        $meses = [];
        $agora = new DateTime('first day of this month 00:00:00');
        for ($i = 11; $i >= 0; $i--) {
            $d = (clone $agora)->modify("-{$i} months");
            $meses[$d->format('Y-m')] = 0.0;
        }

        $sqlMes = "
      SELECT DATE_FORMAT($DATE_NORM,'%Y-%m') AS ym, SUM($TOTAL_NUM) AS total
      FROM vendas_peca v
      WHERE $CNPJ_NORM = :c
        AND $DATE_NORM >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND v.status = 'fechada'
      GROUP BY DATE_FORMAT($DATE_NORM,'%Y-%m')
      ORDER BY ym
    ";
        $st2 = $pdo->prepare($sqlMes);
        $st2->execute([':c' => $cnpjSess]);
        foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $meses[(string)$r['ym']] = (float)($r['total'] ?? 0);
        }

        $acumM = 0.0;
        foreach ($meses as $k => $v) {
            $d = DateTime::createFromFormat('Y-m', $k);
            $monthlyLabels[] = $d ? $d->format('m/y') : $k;
            $acumM += $v;
            $monthlySeries[] = $acumM;
        }
    }
} catch (Throwable $e) {
    error_log('ERRO DASH GRAFICO: ' . $e->getMessage());
}

/* ===========================================================
   Cards (mantém variáveis do controller quando existirem)
   =========================================================== */
$vendasPct       = $vendasPct       ?? 0;
$vendasQtde      = $vendasQtde      ?? 0;
$estoquePct      = $estoquePct      ?? 0;
$itensEstoque    = $itensEstoque    ?? 0;
$faturamentoPct  = $faturamentoPct  ?? 0;

/* Card faturamento usa o valor calculado acima
   (mesma base do gráfico) */
$faturamento30d  = $faturamento30d_calc;

$despesasPct     = $despesasPct     ?? 0;
$despesas30d     = $despesas30d     ?? 0.0;

// Lavagens (se o controller já preencheu, mantém)
$lavagens = $lavagens ?? [];
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoERP - Dashboard</title>

    <link rel="icon" type="image/png" sizes="512x512" href="./assets/images/dashboard/icon.png">
    <link rel="shortcut icon" href="./assets/images/favicon.ico">

    <link rel="stylesheet" href="./assets/css/core/libs.min.css">
    <link rel="stylesheet" href="./assets/vendor/aos/dist/aos.css">
    <link rel="stylesheet" href="./assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="./assets/css/custom.min.css?v=4.0.0">
    <link rel="stylesheet" href="./assets/css/dark.min.css">
    <link rel="stylesheet" href="./assets/css/customizer.min.css">
    <link rel="stylesheet" href="./assets/css/customizer.css">
    <link rel="stylesheet" href="./assets/css/rtl.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .btn-range.active {
            pointer-events: none
        }
    </style>
</head>
<?php
function fmt_compacto_brl(float $v): string
{
    $abs = abs($v);
    if ($abs >= 1_000_000_000) return 'R$ ' . number_format($v / 1_000_000_000, 1, ',', '.') . 'B';
    if ($abs >= 1_000_000)     return 'R$ ' . number_format($v / 1_000_000,     1, ',', '.') . 'M';
    if ($abs >= 1_000)         return 'R$ ' . number_format($v / 1_000,         1, ',', '.') . 'K';
    return 'R$ ' . number_format($v, 2, ',', '.');
}

// Decide se usa compacto
$fat_full    = 'R$ ' . number_format((float)$faturamento30d, 2, ',', '.');
$fat_compact = fmt_compacto_brl((float)$faturamento30d);
$usa_compacto = (mb_strlen($fat_full) > 12); // limiar ajustável
?>
<style>
    /* encolhe suavemente se mesmo abreviado ficar grande */
    .fat-valor {
        display: inline-block;
        max-width: 100%;
        font-weight: 700;
        line-height: 1.1;
        /* reduz se precisar, mas mantém legível */
        font-size: clamp(0.95rem, 2.4vw, 1.25rem);
        white-space: nowrap;
    }

    /* versão “normal” um pouco maior quando couber */
    .fat-valor--normal {
        font-size: clamp(1.1rem, 2.8vw, 1.35rem);
    }
</style>

<body>
    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $menuAtivo = 'dashboard';
    include './layouts/dashboard.php';
    ?>
    <main class="main-content">
        <?php if (!empty($empresaPendente)): ?>
            <div class="modal fade" id="modalEmpresaIncompleta" tabindex="-1" aria-labelledby="modalEmpresaIncompletaLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning-subtle">
                            <h5 class="modal-title" id="modalEmpresaIncompletaLabel"><i class="bi bi-exclamation-triangle me-2"></i> Completar cadastro da empresa</h5>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0"><?= htmlspecialchars($msgCompletar, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="modal-footer text-center">
                            <?php if (!empty($canEditEmpresa)): ?>
                                <a href="./configuracao/pages/empresa.php" class="btn btn-primary w-100"><i class="bi bi-building me-1"></i> Ir para Dados da Empresa</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-secondary" disabled title="Peça ao dono para completar o cadastro"><i class="bi bi-lock me-1"></i> Somente o dono pode editar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var el = document.getElementById('modalEmpresaIncompleta');
                    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
                    new bootstrap.Modal(el, {
                        backdrop: 'static',
                        keyboard: false
                    }).show();
                });
            </script>
        <?php endif; ?>

        <div class="position-relative iq-banner">
            <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="./dashboard.php" class="navbar-brand">
                        <h4 class="logo-title">AutoERP</h4>
                    </a>
                    <div class="sidebar-toggle" data-toggle="sidebar" data-active="true"><i class="icon">
                            <svg width="20px" class="icon-20" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z" />
                            </svg>
                        </i></div>
                    <div class="input-group search-input">
                        <span class="input-group-text" id="search-input">
                            <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none">
                                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"></circle>
                                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"></path>
                            </svg>
                        </span>
                        <input type="search" class="form-control" placeholder="Pesquisar...">
                    </div>
                </div>
            </nav>

            <div class="iq-navbar-header" style="height: 215px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h1>Bem-vindo, <?= htmlspecialchars($nomeUser, ENT_QUOTES, 'UTF-8') ?>!</h1>
                                    <p><?= htmlspecialchars($fraseHeader, ENT_QUOTES, 'UTF-8') ?> Empresa: <strong><?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="iq-header-img"><img src="./assets/images/dashboard/top-header.png" alt="header" class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX"></div>
            </div>
        </div>

        <div class="container-fluid content-inner mt-n5 py-0">
            <div class="row">
                <div class="col-md-12 col-lg-12">
                    <div class="row">
                        <div class="overflow-hidden d-slider1 ">
                            <ul class="p-0 m-0 mb-2 swiper-wrapper list-inline" style="gap:6px;">
                                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="700">
                                    <div class="card-body">
                                        <div class="progress-widget">
                                            <div id="circle-progress-01" class="text-center circle-progress-01 circle-progress circle-progress-primary" data-min-value="0" data-max-value="100" data-value="<?= (int)$vendasPct ?>" data-type="percent"></div>
                                            <div class="progress-detail">
                                                <p class="mb-2">Vendas</p>
                                                <h4 class="counter"><?= number_format((float)$vendasQtde, 0, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="800">
                                    <div class="card-body">
                                        <div class="progress-widget">
                                            <div id="circle-progress-02" class="text-center circle-progress-01 circle-progress circle-progress-info" data-min-value="0" data-max-value="100" data-value="<?= (int)$estoquePct ?>" data-type="percent"></div>
                                            <div class="progress-detail">
                                                <p class="mb-2">Itens em Estoque</p>
                                                <h4 class="counter"><?= number_format((float)$itensEstoque, 0, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="swiper-slide card card-slide col-lg-3" data-aos="fade-up" data-aos-delay="900">
                                    <div class="card-body">
                                        <div class="progress-widget">
                                            <div id="circle-progress-03"
                                                class="text-center circle-progress-01 circle-progress circle-progress-primary"
                                                data-min-value="0" data-max-value="100"
                                                data-value="<?= (int)$faturamentoPct ?>" data-type="percent"></div>

                                            <div class="progress-detail">
                                                <p class="mb-2">Faturamento</p>
                                                <h4 class="counter">
                                                    <span class="fat-valor <?= $usa_compacto ? '' : 'fat-valor--normal' ?>">
                                                        <?= $usa_compacto ? htmlspecialchars($fat_compact, ENT_QUOTES, 'UTF-8')
                                                            : htmlspecialchars($fat_full, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <li class="swiper-slide card card-slide col-lg-3 px-3" data-aos="fade-up" data-aos-delay="1100">
                                    <div class="card-body">
                                        <div class="progress-widget">
                                            <div id="circle-progress-04" class="text-center circle-progress-01 circle-progress circle-progress-primary" data-min-value="0" data-max-value="100" data-value="<?= (int)$despesasPct ?>" data-type="percent"></div>
                                            <div class="progress-detail">
                                                <p class="mb-2">Despesas</p>
                                                <h4 class="counter">R$ <?= number_format((float)$despesas30d, 2, ',', '.') ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICO -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card" data-aos="fade-up" data-aos-delay="800">
                        <div class="flex-wrap card-header d-flex justify-content-between align-items-center">
                            <div class="header-title">
                                <h4 class="card-title">Gráfico de Vendas</h4>
                                <p class="mb-0">Tendência diária / mensal (acumulado)</p>
                            </div>
                            <div class="d-none d-md-block text-muted small">Use os filtros</div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button class="btn btn-sm btn-outline-primary btn-range" data-range="7">Semana</button>
                                <button class="btn btn-sm btn-outline-primary btn-range" data-range="30">Mês</button>
                                <button class="btn btn-sm btn-outline-primary btn-range" data-range="180">6 Meses</button>
                                <button class="btn btn-sm btn-primary btn-range" data-range="365">12 Meses</button>
                            </div>
                            <canvas id="graficoVendas" style="max-height:360px"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                // Se seu controller já limita para a semana, use $lavagens (ou renomeie para o que você usa)
                window.LAVAGENS_SEMANA = <?= json_encode($lavagens ?? [], JSON_UNESCAPED_UNICODE) ?>;
            </script>

            <!-- LAVAGENS -->
            <div class="row">
                <div class="col-md-12 col-lg-12">
                    <div class="overflow-hidden card" data-aos="fade-up" data-aos-delay="600">
                        <!-- Cabeçalho do card atualizado -->
                        <div class="flex-wrap card-header d-flex justify-content-between align-items-center">
                            <div class="header-title">
                                <h4 class="mb-1 card-title">Lavagens Recentes</h4>
                                <p class="mb-0 text-muted small">Semana atual • <span id="lavSemanaRange"><?= htmlspecialchars($lavSemanaLabel ?? '—', ENT_QUOTES, 'UTF-8') ?></span></p>
                            </div>
                            <div class="text-muted small">
                                <span id="lavInfo" class="me-2">—</span>
                                <span class="d-none d-md-inline">Paginação no cliente</span>
                            </div>
                        </div>

                        <div class="p-0 card-body">
                            <div class="mt-3 table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width:160px">Lavador</th>
                                            <th style="min-width:180px">Serviço</th>
                                            <th style="min-width:220px">Veículo</th>
                                            <th class="text-end" style="width:120px">Valor</th>
                                            <th style="width:160px">Data/Hora</th>
                                            <th style="width:110px">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbLavagens">
                                        <?php if (empty($lavagens)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Sem registros.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($lavagens as $L): /* fallback se JS off */ ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($L['lavador'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars($L['servico'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars($L['veiculo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-end">R$ <?= number_format((float)($L['valor'] ?? 0), 2, ',', '.') ?></td>
                                                    <td><?= htmlspecialchars($L['quando'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td>
                                                        <?php
                                                        $st = (string)($L['status'] ?? 'aberta');
                                                        $badge = ($st === 'concluida' ? 'success' : ($st === 'cancelada' ? 'danger' : 'secondary'));
                                                        $rot   = ($st === 'concluida' ? 'Concluída' : ($st === 'cancelada' ? 'Cancelada' : 'Aberta'));
                                                        ?>
                                                        <span class="badge bg-<?= $badge ?>"><?= $rot ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginação -->
                            <div class="mt-3 d-flex justify-content-end mb-3">
                                <nav aria-label="Paginação lavagens">
                                    <ul id="lavPager" class="pagination pagination-sm mb-0"><!-- via JS --></ul>
                                </nav>
                            </div>
                        </div>

                        <script>
                            // Dados da semana (controller sem LIMIT)
                            window.LAVAGENS_SEMANA = <?= json_encode($lavagens ?? [], JSON_UNESCAPED_UNICODE) ?>;
                        </script>

                        <script>
                            (function() {
                                const DATA = Array.isArray(window.LAVAGENS_SEMANA) ? window.LAVAGENS_SEMANA : [];
                                const PER = 6; // 6 por página
                                let page = 1; // página atual
                                const tbody = document.getElementById('tbLavagens');
                                const pager = document.getElementById('lavPager');
                                const infoEl = document.getElementById('lavInfo');

                                const fmtBRL = (v) => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const statusBadge = (st) => {
                                    const s = String(st || 'aberta');
                                    if (s === 'concluida') return ['success', 'Concluída'];
                                    if (s === 'cancelada') return ['danger', 'Cancelada'];
                                    return ['secondary', 'Aberta'];
                                };

                                function updateInfo(from, to, total) {
                                    if (infoEl) infoEl.textContent = total ? `Mostrando ${from}–${to} de ${total}` : 'Sem registros';
                                }

                                function renderPage(p) {
                                    if (!tbody) return;
                                    tbody.innerHTML = '';

                                    const total = DATA.length;
                                    const pages = Math.max(1, Math.ceil(total / PER));
                                    page = Math.min(Math.max(1, p), pages);

                                    if (!total) {
                                        const tr = document.createElement('tr');
                                        tr.innerHTML = `<td colspan="6" class="text-center text-muted py-4">Sem registros.</td>`;
                                        tbody.appendChild(tr);
                                        renderPager(1, 1);
                                        updateInfo(0, 0, 0);
                                        return;
                                    }

                                    const ini = (page - 1) * PER;
                                    const fim = Math.min(ini + PER, total);
                                    const slice = DATA.slice(ini, fim);

                                    slice.forEach(L => {
                                        const [cls, rot] = statusBadge(L.status);
                                        const tr = document.createElement('tr');
                                        tr.innerHTML = `
        <td>${(L.lavador ?? '-').toString().replace(/</g,'&lt;')}</td>
        <td>${(L.servico ?? '-').toString().replace(/</g,'&lt;')}</td>
        <td>${(L.veiculo ?? '-').toString().replace(/</g,'&lt;')}</td>
        <td class="text-end">${fmtBRL(L.valor)}</td>
        <td>${(L.quando ?? '-').toString().replace(/</g,'&lt;')}</td>
        <td><span class="badge bg-${cls}">${rot}</span></td>
      `;
                                        tbody.appendChild(tr);
                                    });

                                    renderPager(page, pages);
                                    updateInfo(ini + 1, fim, total);
                                }

                                function renderPager(current, pages) {
                                    if (!pager) return;
                                    pager.innerHTML = '';

                                    const mk = (label, disabled, active, go) => {
                                        const li = document.createElement('li');
                                        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                                        const a = document.createElement('a');
                                        a.className = 'page-link';
                                        a.href = '#';
                                        a.textContent = label;
                                        if (!disabled && go) a.addEventListener('click', (ev) => {
                                            ev.preventDefault();
                                            go();
                                        });
                                        li.appendChild(a);
                                        return li;
                                    };

                                    pager.appendChild(mk('Anterior', current <= 1, false, () => renderPage(current - 1)));

                                    // janela de 5 páginas
                                    const span = 2;
                                    let start = Math.max(1, current - span);
                                    let end = Math.min(pages, current + span);
                                    if (end - start < span * 2) {
                                        start = Math.max(1, end - span * 2);
                                        end = Math.min(pages, start + span * 2);
                                    }
                                    for (let p = start; p <= end; p++) {
                                        pager.appendChild(mk(String(p), false, p === current, () => renderPage(p)));
                                    }

                                    pager.appendChild(mk('Próxima', current >= pages, false, () => renderPage(current + 1)));
                                }

                                // Inicializa
                                renderPage(1);
                            })();
                        </script>


                    </div>

                    <footer class="footer">
                        <div class="footer-body d-flex justify-content-between align-items-center">
                            <div class="left-panel">© <script>
                                    document.write(new Date().getFullYear())
                                </script> <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="right-panel">Desenvolvido por Lucas de S. Correa.</div>
                        </div>
                    </footer>
    </main>

    <!-- LIBS -->
    <script src="./assets/js/core/libs.min.js"></script>
    <script src="./assets/js/core/external.min.js"></script>
    <script src="./assets/js/charts/widgetcharts.js"></script>
    <script src="./assets/js/charts/vectore-chart.js"></script>
    <script src="./assets/js/plugins/fslightbox.js"></script>
    <script src="./assets/js/plugins/setting.js"></script>
    <script src="./assets/js/plugins/slider-tabs.js"></script>
    <script src="./assets/js/plugins/form-wizard.js"></script>
    <script src="./assets/vendor/aos/dist/aos.js"></script>
    <script src="./assets/js/hope-ui.js" defer></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Dados do PHP para JS -->
    <script>
        window.CHART_DAILY_LABELS = <?= json_encode($dailyLabels,   JSON_UNESCAPED_UNICODE) ?>;
        window.CHART_DAILY_SERIES = <?= json_encode($dailySeries,   JSON_UNESCAPED_UNICODE) ?>;
        window.CHART_MONTHLY_LABELS = <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>;
        window.CHART_MONTHLY_SERIES = <?= json_encode($monthlySeries, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- Gráfico + filtros -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById("graficoVendas");
            if (!canvas) return;

            function toNum(x) {
                if (typeof x === "number") return x;
                if (x == null) return 0;
                const s = String(x).trim()
                    .replace(/\s+/g, "")
                    .replace(/\.(?=\d{3}(\D|$))/g, "")
                    .replace(/,/, ".");
                const n = Number(s);
                return Number.isFinite(n) ? n : 0;
            }

            function suggestedMaxOf(arr) {
                const m = Math.max(0, ...arr);
                return (isFinite(m) && m > 0) ? m * 1.1 : undefined;
            }

            const DL = Array.isArray(window.CHART_DAILY_LABELS) ? window.CHART_DAILY_LABELS : [];
            const DS = Array.isArray(window.CHART_DAILY_SERIES) ? window.CHART_DAILY_SERIES : [];
            const ML = Array.isArray(window.CHART_MONTHLY_LABELS) ? window.CHART_MONTHLY_LABELS : [];
            const MS = Array.isArray(window.CHART_MONTHLY_SERIES) ? window.CHART_MONTHLY_SERIES : [];

            const dailyLabels = DL.length ? DL : ["01/01", "02/01", "03/01", "04/01", "05/01", "06/01", "07/01"];
            const dailySeries = (DS.length ? DS : [0, 0, 0, 0, 0, 0, 0]).map(toNum);
            const monthlyLabels = ML.length ? ML : ["01/24", "02/24", "03/24", "04/24", "05/24", "06/24", "07/24", "08/24", "09/24", "10/24", "11/24", "12/24"];
            const monthlySeries = (MS.length ? MS : Array(12).fill(0)).map(toNum);

            const chart = new Chart(canvas.getContext("2d"), {
                type: "line",
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: "Faturamento acumulado",
                        data: monthlySeries,
                        fill: true,
                        tension: 0.3,
                        backgroundColor: "rgba(54,162,235,0.2)",
                        borderColor: "rgba(54,162,235,1)",
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(54,162,235,1)"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const v = Number(ctx.parsed.y || 0);
                                    return "R$ " + v.toLocaleString("pt-BR", {
                                        minimumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: suggestedMaxOf(monthlySeries),
                            ticks: {
                                callback: (val) => "R$ " + Number(val).toLocaleString("pt-BR")
                            }
                        }
                    }
                }
            });

            function setActive(btn) {
                document.querySelectorAll('.btn-range').forEach(b => {
                    b.classList.remove('btn-primary', 'active');
                    b.classList.add('btn-outline-primary');
                });
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary', 'active');
            }

            function renderDaily(lastDays) {
                const qtd = Math.min(lastDays, dailyLabels.length);
                const data = dailySeries.slice(-qtd);
                chart.data.labels = dailyLabels.slice(-qtd);
                chart.data.datasets[0].data = data;
                chart.options.scales.y.suggestedMax = suggestedMaxOf(data);
                chart.update();
            }

            function renderMonthly(lastMonths) {
                const qtd = Math.min(lastMonths, monthlyLabels.length);
                const data = monthlySeries.slice(-qtd);
                chart.data.labels = monthlyLabels.slice(-qtd);
                chart.data.datasets[0].data = data;
                chart.options.scales.y.suggestedMax = suggestedMaxOf(data);
                chart.update();
            }

            document.querySelectorAll(".btn-range").forEach(btn => {
                btn.addEventListener("click", () => {
                    const val = parseInt(btn.getAttribute("data-range"), 10);
                    if (val === 7) {
                        renderDaily(7);
                        setActive(btn);
                    } else if (val === 30) {
                        renderDaily(30);
                        setActive(btn);
                    } else if (val === 180) {
                        renderMonthly(6);
                        setActive(btn);
                    } else if (val === 365) {
                        renderMonthly(12);
                        setActive(btn);
                    }
                });
            });

            const startBtn = document.querySelector('.btn-range[data-range="365"]');
            if (startBtn) setActive(startBtn);
            renderMonthly(12);
        });
    </script>
</body>

</html>