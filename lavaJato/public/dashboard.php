<?php
// autoErp/public/dashboard.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/auth_guard.php';
ensure_logged_in(['dono', 'funcionario']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===========================================================
   Sessão
   =========================================================== */
$cnpjSess = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$nomeUser = $_SESSION['user_nome'] ?? 'Usuário';
$perfil   = strtolower((string)($_SESSION['user_perfil'] ?? 'funcionario'));
$tipo     = strtolower((string)($_SESSION['user_tipo'] ?? ''));

/* ===========================================================
   Conexão
   =========================================================== */
$pdo = null;
$pathConexao = realpath(__DIR__ . '/../conexao/conexao.php');
if ($pathConexao && file_exists($pathConexao)) {
    require_once $pathConexao;
}
if (!($pdo instanceof PDO)) {
    die('Conexão indisponível.');
}

/* ===========================================================
   Helpers
   =========================================================== */
function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function fmtDateTime(?string $dt): string
{
    if (!$dt) return '-';
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i', $ts) : $dt;
}

function fmtDate(?string $dt): string
{
    if (!$dt) return '-';
    $ts = strtotime($dt);
    return $ts ? date('d/m', $ts) : $dt;
}

function statusBadge(string $status): array
{
    $status = strtolower(trim($status));
    return match ($status) {
        'concluida' => ['success', 'Concluída'],
        'cancelada' => ['danger', 'Cancelada'],
        default     => ['warning', 'Aberta'],
    };
}

$empresaNome = $_SESSION['empresa_nome'] ?? 'Lava Jato';
$empresaRow = null;
$empresaPendente = false;
$msgCompletar = '';
$canEditEmpresa = ($perfil === 'dono');

try {
    if ($cnpjSess !== '') {
        $stEmpresa = $pdo->prepare("
            SELECT *
            FROM empresas_peca
            WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','') = :c
            LIMIT 1
        ");
        $stEmpresa->execute([':c' => $cnpjSess]);
        $empresaRow = $stEmpresa->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($empresaRow) {
            $empresaNome = (string)($empresaRow['nome_fantasia'] ?? $empresaNome);
        }
    }
} catch (Throwable $e) {
    $empresaRow = null;
}

$camposObrigatorios = [
    'nome_fantasia' => 'Nome Fantasia',
    'email'         => 'E-mail',
    'telefone'      => 'Telefone',
    'endereco'      => 'Endereço',
    'cidade'        => 'Cidade',
    'estado'        => 'UF',
    'cep'           => 'CEP'
];

if (!$empresaRow) {
    $empresaPendente = true;
    $msgCompletar = 'Sua empresa ainda não está cadastrada. Complete as informações para usar todos os recursos do sistema.';
} else {
    $faltando = [];
    foreach ($camposObrigatorios as $campo => $rotulo) {
        if (trim((string)($empresaRow[$campo] ?? '')) === '') {
            $faltando[] = $rotulo;
        }
    }
    if ($faltando) {
        $empresaPendente = true;
        $msgCompletar = 'Algumas informações da empresa estão faltando: ' . implode(', ', $faltando) . '.';
    }
}

$fraseHeader = $perfil === 'dono'
    ? 'Acompanhe as lavagens, o faturamento, os vales e o desempenho da equipe em tempo real.'
    : 'Acompanhe a operação do lava jato, registre serviços e mantenha o fluxo organizado.';

/* ===========================================================
   Indicadores do Dashboard
   =========================================================== */
$lavagensHoje = 0;
$faturamentoHoje = 0.0;
$lavagensAbertas = 0;
$lavagensSemana = 0;
$faturamentoSemana = 0.0;
$lavadoresAtivos = 0;
$valesSemana = 0.0;

$chartLabels = [];
$chartLavagens = [];
$chartValores = [];
$lavagensRecentes = [];
$topLavadores = [];

try {
    if ($cnpjSess !== '') {
        $cnpjExpr = "REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','')";

        // Lavagens hoje
        $st = $pdo->prepare("
            SELECT COUNT(*) AS total, COALESCE(SUM(valor),0) AS valor_total
            FROM lavagens_peca
            WHERE $cnpjExpr = :c
              AND DATE(criado_em) = CURDATE()
              AND status <> 'cancelada'
        ");
        $st->execute([':c' => $cnpjSess]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $lavagensHoje = (int)($row['total'] ?? 0);
        $faturamentoHoje = (float)($row['valor_total'] ?? 0);

        // Lavagens abertas
        $st = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM lavagens_peca
            WHERE $cnpjExpr = :c
              AND status = 'aberta'
        ");
        $st->execute([':c' => $cnpjSess]);
        $lavagensAbertas = (int)($st->fetchColumn() ?: 0);

        // Semana atual
        $st = $pdo->prepare("
            SELECT COUNT(*) AS total, COALESCE(SUM(valor),0) AS valor_total
            FROM lavagens_peca
            WHERE $cnpjExpr = :c
              AND YEARWEEK(criado_em, 1) = YEARWEEK(CURDATE(), 1)
              AND status <> 'cancelada'
        ");
        $st->execute([':c' => $cnpjSess]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $lavagensSemana = (int)($row['total'] ?? 0);
        $faturamentoSemana = (float)($row['valor_total'] ?? 0);

        // Lavadores ativos
        $st = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM lavadores_peca
            WHERE $cnpjExpr = :c
              AND ativo = 1
        ");
        $st->execute([':c' => $cnpjSess]);
        $lavadoresAtivos = (int)($st->fetchColumn() ?: 0);

        // Vales da semana
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(valor),0) AS total
            FROM vales_lavadores_peca
            WHERE $cnpjExpr = :c
              AND YEARWEEK(criado_em, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $st->execute([':c' => $cnpjSess]);
        $valesSemana = (float)($st->fetchColumn() ?: 0);

        // Gráfico últimos 7 dias
        $diasBase = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $diasBase[$d] = ['qtd' => 0, 'valor' => 0.0];
        }

        $st = $pdo->prepare("
            SELECT DATE(criado_em) AS dia,
                   COUNT(*) AS qtd,
                   COALESCE(SUM(valor),0) AS total
            FROM lavagens_peca
            WHERE $cnpjExpr = :c
              AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              AND status <> 'cancelada'
            GROUP BY DATE(criado_em)
            ORDER BY DATE(criado_em) ASC
        ");
        $st->execute([':c' => $cnpjSess]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dia = (string)$r['dia'];
            if (isset($diasBase[$dia])) {
                $diasBase[$dia]['qtd'] = (int)($r['qtd'] ?? 0);
                $diasBase[$dia]['valor'] = (float)($r['total'] ?? 0);
            }
        }

        foreach ($diasBase as $dia => $dados) {
            $chartLabels[] = fmtDate($dia);
            $chartLavagens[] = (int)$dados['qtd'];
            $chartValores[] = (float)$dados['valor'];
        }

        // Lavagens recentes
        $st = $pdo->prepare("
            SELECT
                l.id,
                l.lavador_cpf,
                l.placa,
                l.modelo,
                l.cor,
                l.categoria_nome,
                l.valor,
                l.forma_pagamento,
                l.status,
                l.checkin_at,
                l.checkout_at,
                l.criado_em,
                lv.nome AS lavador_nome
            FROM lavagens_peca l
            LEFT JOIN lavadores_peca lv
              ON REPLACE(REPLACE(REPLACE(lv.empresa_cnpj,'.',''),'-',''),'/','') = REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','')
             AND REPLACE(REPLACE(lv.cpf,'.',''),'-','') = REPLACE(REPLACE(l.lavador_cpf,'.',''),'-','')
            WHERE REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','') = :c
            ORDER BY l.criado_em DESC
            LIMIT 12
        ");
        $st->execute([':c' => $cnpjSess]);
        $lavagensRecentes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Top lavadores da semana
        $st = $pdo->prepare("
            SELECT
                COALESCE(lv.nome, CONCAT('CPF: ', l.lavador_cpf)) AS lavador,
                COUNT(*) AS qtd,
                COALESCE(SUM(l.valor),0) AS total
            FROM lavagens_peca l
            LEFT JOIN lavadores_peca lv
              ON REPLACE(REPLACE(REPLACE(lv.empresa_cnpj,'.',''),'-',''),'/','') = REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','')
             AND REPLACE(REPLACE(lv.cpf,'.',''),'-','') = REPLACE(REPLACE(l.lavador_cpf,'.',''),'-','')
            WHERE REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','') = :c
              AND YEARWEEK(l.criado_em, 1) = YEARWEEK(CURDATE(), 1)
              AND l.status <> 'cancelada'
            GROUP BY lavador
            ORDER BY qtd DESC, total DESC
            LIMIT 5
        ");
        $st->execute([':c' => $cnpjSess]);
        $topLavadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log('ERRO DASHBOARD LAVAJATO: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lava Jato - Dashboard</title>

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
        .dashboard-lavajato .metric-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
            height: 100%;
        }

        .dashboard-lavajato .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .dashboard-lavajato .metric-value {
            font-size: 1.55rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .dashboard-lavajato .metric-label {
            color: #64748b;
            font-size: .92rem;
        }

        .dashboard-lavajato .hero-box {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
            color: #fff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(2, 132, 199, .28);
        }

        .dashboard-lavajato .hero-box .mini-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .15);
            font-size: .9rem;
        }

        .dashboard-lavajato .card-soft {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .05);
        }

        .dashboard-lavajato .table td,
        .dashboard-lavajato .table th {
            vertical-align: middle;
        }

        .dashboard-lavajato .top-list li {
            border-bottom: 1px solid #eef2f7;
            padding: 12px 0;
        }

        .dashboard-lavajato .top-list li:last-child {
            border-bottom: 0;
        }
    </style>
</head>

<body>
    <?php
    $menuAtivo = 'dashboard';
    include './layouts/dashboard.php';
    ?>

    <main class="main-content dashboard-lavajato">

        <?php if ($empresaPendente): ?>
            <div class="modal fade" id="modalEmpresaIncompleta" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning-subtle">
                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Completar cadastro da empresa</h5>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0"><?= h($msgCompletar) ?></p>
                        </div>
                        <div class="modal-footer">
                            <?php if ($canEditEmpresa): ?>
                                <a href="./configuracao/pages/empresa.php" class="btn btn-primary w-100">
                                    <i class="bi bi-building me-1"></i> Ir para dados da empresa
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                    Somente o dono pode editar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const el = document.getElementById('modalEmpresaIncompleta');
                        if (el) new bootstrap.Modal(el, {
                            backdrop: 'static',
                            keyboard: false
                        }).show();
                    }
                });
            </script>
        <?php endif; ?>

        <div class="container-fluid content-inner py-4">
            <div class="hero-box mb-4" data-aos="fade-up">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="mb-2 text-white">Bem-vindo, <?= h($nomeUser) ?>!</h2>
                        <p class="mb-0 opacity-75">
                            <?= h($fraseHeader) ?>
                            Empresa: <strong><?= h($empresaNome) ?></strong>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="mini-pill"><i class="bi bi-car-front"></i> Lava Jato</span>
                        <span class="mini-pill"><i class="bi bi-calendar-week"></i> Semana atual</span>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="card metric-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="metric-label">Lavagens de hoje</div>
                                <div class="metric-value"><?= number_format($lavagensHoje, 0, ',', '.') ?></div>
                            </div>
                            <div class="metric-icon bg-primary-subtle text-primary">
                                <i class="bi bi-droplet-half"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="150">
                    <div class="card metric-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="metric-label">Faturamento de hoje</div>
                                <div class="metric-value"><?= h(money($faturamentoHoje)) ?></div>
                            </div>
                            <div class="metric-icon bg-success-subtle text-success">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="card metric-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="metric-label">Lavagens em aberto</div>
                                <div class="metric-value"><?= number_format($lavagensAbertas, 0, ',', '.') ?></div>
                            </div>
                            <div class="metric-icon bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="250">
                    <div class="card metric-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="metric-label">Lavadores ativos</div>
                                <div class="metric-value"><?= number_format($lavadoresAtivos, 0, ',', '.') ?></div>
                            </div>
                            <div class="metric-icon bg-info-subtle text-info">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-xl-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="card card-soft">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">Resumo da semana</h5>
                                <i class="bi bi-bar-chart-line text-primary"></i>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-muted small">Lavagens</div>
                                    <div class="fs-4 fw-bold"><?= number_format($lavagensSemana, 0, ',', '.') ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Faturamento</div>
                                    <div class="fs-4 fw-bold"><?= h(money($faturamentoSemana)) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6" data-aos="fade-up" data-aos-delay="350">
                    <div class="card card-soft">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">Vales pagos na semana</h5>
                                <i class="bi bi-wallet2 text-danger"></i>
                            </div>
                            <div class="text-muted small">Adiantamentos lançados para os lavadores</div>
                            <div class="fs-3 fw-bold mt-2"><?= h(money($valesSemana)) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8" data-aos="fade-up" data-aos-delay="400">
                    <div class="card card-soft">
                        <div class="card-header border-0 pb-0">
                            <h5 class="card-title mb-1">Desempenho dos últimos 7 dias</h5>
                            <p class="text-muted mb-0">Quantidade de lavagens e faturamento diário</p>
                        </div>
                        <div class="card-body">
                            <canvas id="graficoLavajato" style="max-height:360px;"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="450">
                    <div class="card card-soft h-100">
                        <div class="card-header border-0 pb-0">
                            <h5 class="card-title mb-1">Top lavadores da semana</h5>
                            <p class="text-muted mb-0">Mais produtivos no período</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topLavadores)): ?>
                                <div class="text-muted">Sem dados na semana.</div>
                            <?php else: ?>
                                <ul class="list-unstyled top-list mb-0">
                                    <?php foreach ($topLavadores as $i => $item): ?>
                                        <li class="d-flex justify-content-between align-items-center gap-3">
                                            <div>
                                                <div class="fw-semibold"><?= h((string)($item['lavador'] ?? '-')) ?></div>
                                                <div class="small text-muted">
                                                    <?= number_format((int)($item['qtd'] ?? 0), 0, ',', '.') ?> lavagens
                                                </div>
                                            </div>
                                            <div class="text-end fw-bold"><?= h(money((float)($item['total'] ?? 0))) ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-soft" data-aos="fade-up" data-aos-delay="500">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-1">Lavagens recentes</h5>
                        <p class="text-muted mb-0">Últimos serviços registrados no sistema</p>
                    </div>
                    <a href="./lavajato/pages/lavagens.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-list-ul me-1"></i> Ver todas
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Lavador</th>
                                    <th>Serviço</th>
                                    <th>Veículo</th>
                                    <th>Valor</th>
                                    <th>Pagamento</th>
                                    <th>Entrada</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lavagensRecentes)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Nenhuma lavagem encontrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lavagensRecentes as $item): ?>
                                        <?php
                                        [$badgeClass, $badgeText] = statusBadge((string)($item['status'] ?? 'aberta'));
                                        $veiculo = trim(
                                            (string)($item['modelo'] ?? '') .
                                                ((string)($item['cor'] ?? '') !== '' ? ' / ' . (string)$item['cor'] : '') .
                                                ((string)($item['placa'] ?? '') !== '' ? ' - ' . (string)$item['placa'] : '')
                                        );
                                        $servico = (string)($item['categoria_nome'] ?? 'Lavagem');
                                        ?>
                                        <tr>
                                            <td>#<?= (int)($item['id'] ?? 0) ?></td>
                                            <td><?= h((string)($item['lavador_nome'] ?? ('CPF: ' . ($item['lavador_cpf'] ?? '-')))) ?></td>
                                            <td><?= h($servico) ?></td>
                                            <td><?= h($veiculo !== '' ? $veiculo : '-') ?></td>
                                            <td class="fw-semibold"><?= h(money((float)($item['valor'] ?? 0))) ?></td>
                                            <td><?= h((string)($item['forma_pagamento'] ?? '-')) ?></td>
                                            <td><?= h(fmtDateTime((string)($item['checkin_at'] ?? $item['criado_em'] ?? ''))) ?></td>
                                            <td><span class="badge bg-<?= h($badgeClass) ?>"><?= h($badgeText) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <footer class="footer mt-4">
                <div class="footer-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>© <script>
                            document.write(new Date().getFullYear())
                        </script> <?= h($empresaNome) ?></div>
                    <div>Desenvolvido por L&amp;J Soluções Tecnológicas.</div>
                </div>
            </footer>
        </div>
    </main>

    <script src="./assets/js/core/libs.min.js"></script>
    <script src="./assets/js/core/external.min.js"></script>
    <script src="./assets/js/plugins/fslightbox.js"></script>
    <script src="./assets/js/plugins/setting.js"></script>
    <script src="./assets/js/plugins/slider-tabs.js"></script>
    <script src="./assets/js/plugins/form-wizard.js"></script>
    <script src="./assets/vendor/aos/dist/aos.js"></script>
    <script src="./assets/js/hope-ui.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        window.DASH_LAVAJATO_LABELS = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
        window.DASH_LAVAJATO_QTD = <?= json_encode($chartLavagens, JSON_UNESCAPED_UNICODE) ?>;
        window.DASH_LAVAJATO_VALOR = <?= json_encode($chartValores, JSON_UNESCAPED_UNICODE) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AOS !== 'undefined') {
                AOS.init();
            }

            const el = document.getElementById('graficoLavajato');
            if (!el || typeof Chart === 'undefined') return;

            const labels = Array.isArray(window.DASH_LAVAJATO_LABELS) ? window.DASH_LAVAJATO_LABELS : [];
            const qtd = Array.isArray(window.DASH_LAVAJATO_QTD) ? window.DASH_LAVAJATO_QTD : [];
            const valor = Array.isArray(window.DASH_LAVAJATO_VALOR) ? window.DASH_LAVAJATO_VALOR : [];

            new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            type: 'bar',
                            label: 'Lavagens',
                            data: qtd,
                            yAxisID: 'y',
                            borderRadius: 8
                        },
                        {
                            type: 'line',
                            label: 'Faturamento',
                            data: valor,
                            yAxisID: 'y1',
                            tension: 0.35,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.label === 'Faturamento') {
                                        return 'Faturamento: R$ ' + Number(ctx.parsed.y || 0).toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                    return 'Lavagens: ' + Number(ctx.parsed.y || 0).toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Lavagens'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + Number(value).toLocaleString('pt-BR');
                                }
                            },
                            title: {
                                display: true,
                                text: 'Faturamento'
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>