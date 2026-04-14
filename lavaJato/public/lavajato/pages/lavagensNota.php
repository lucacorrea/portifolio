<?php
// autoErp/public/lavajato/pages/lavagensNota.php
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
$iniGet  = trim((string)($_GET['ini'] ?? ''));
$fimGet  = trim((string)($_GET['fim'] ?? ''));
$q       = trim((string)($_GET['q'] ?? ''));
$lav     = trim((string)($_GET['lav'] ?? ''));

if ($lav === '' || ($weekRef === '' && ($iniGet === '' || $fimGet === ''))) {
    http_response_code(400);
    die('Parâmetros inválidos.');
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

function pct($p): string
{
    $p = (float)$p;
    $s = number_format($p, 2, ',', '.');
    $s = rtrim(rtrim($s, '0'), ',');
    return $s . '%';
}

function only_time(string $dt): string
{
    $ts = strtotime($dt);
    return $ts ? date('H:i', $ts) : $dt;
}

/* ==== ViewModel ==== */
try {
    if (!function_exists('lavagens_semana_por_lavador_viewmodel')) {
        throw new RuntimeException('Função lavagens_semana_por_lavador_viewmodel() não encontrada.');
    }

    $args = [
        'lav' => $lav,
        'q'   => $q,
    ];

    if ($weekRef !== '') {
        $args['week_ref'] = $weekRef;
    } else {
        $args['ini'] = $iniGet;
        $args['fim'] = $fimGet;
    }

    $vmBase = lavagens_semana_por_lavador_viewmodel($pdo, $args);

    if (empty($vmBase['detalhe'])) {
        throw new RuntimeException('Lavador não encontrado para esta semana.');
    }

    $det = $vmBase['detalhe'];
    $period = (string)($vmBase['periodo_label'] ?? '');
    $weekRefOut = (string)($vmBase['week_ref'] ?? $weekRef);
    $ini = (string)($vmBase['periodo_ini'] ?? '');
    $fim = (string)($vmBase['periodo_fim'] ?? '');
    $iniDate = (string)($vmBase['ini'] ?? $iniGet);
    $fimDate = (string)($vmBase['fim'] ?? $fimGet);
} catch (Throwable $e) {
    http_response_code(500);
    die('Erro ao gerar nota: ' . $e->getMessage());
}

/* ==== Empresa ==== */
$cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $cnpj)) {
    http_response_code(403);
    die('Empresa inválida.');
}

/* ==== Config ==== */
$cfg = [
    'utilidades_pct'       => 0.00,
    'comissao_lavador_pct' => 0.00,
];

try {
    $stCfg = $pdo->prepare("
        SELECT utilidades_pct, comissao_lavador_pct
          FROM lavjato_config_peca
         WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         LIMIT 1
    ");
    $stCfg->execute([':c' => $cnpj]);

    if ($rowCfg = $stCfg->fetch(PDO::FETCH_ASSOC)) {
        $cfg['utilidades_pct']       = (float)($rowCfg['utilidades_pct'] ?? 0);
        $cfg['comissao_lavador_pct'] = (float)($rowCfg['comissao_lavador_pct'] ?? 0);
    }
} catch (Throwable $e) {
}

$uPct = max(0.0, min(100.0, (float)$cfg['utilidades_pct']));
$cPct = max(0.0, min(100.0, (float)$cfg['comissao_lavador_pct']));

/* ==== Resolver lavador ==== */
$lavadorId = 0;
$lavadorNome = (string)($det['lavador'] ?? '—');
$lavCpf = '';
$lavKey = (string)($det['lav_key'] ?? $lav);

try {
    if (stripos($lavKey, 'CPF:') === 0) {
        $lavCpf = preg_replace('/\D+/', '', substr($lavKey, 4));
    } elseif (stripos($lavKey, 'N:') === 0) {
        $lavadorNome = trim(substr($lavKey, 2));
    }

    if ($lavCpf !== '') {
        $st = $pdo->prepare("
            SELECT id, nome, cpf
              FROM lavadores_peca
             WHERE empresa_cnpj = :c
               AND REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),'/','') = :cpf
             LIMIT 1
        ");
        $st->execute([':c' => $cnpj, ':cpf' => $lavCpf]);

        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $lavadorId = (int)$r['id'];
            if (!empty($r['nome'])) {
                $lavadorNome = (string)$r['nome'];
            }
        }
    }

    if ($lavadorId <= 0 && $lavadorNome !== '' && $lavadorNome !== '—') {
        $st = $pdo->prepare("
            SELECT id, nome, cpf
              FROM lavadores_peca
             WHERE empresa_cnpj = :c
               AND nome = :n
             LIMIT 1
        ");
        $st->execute([':c' => $cnpj, ':n' => $lavadorNome]);

        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $lavadorId = (int)$r['id'];
        }
    }
} catch (Throwable $e) {
}

/* ==== Cálculos ==== */
$bruto     = (float)($det['total'] ?? 0.0);
$descU     = round($bruto * ($uPct / 100.0), 2);
$liq       = round($bruto - $descU, 2);
$comissao  = round($liq * ($cPct / 100.0), 2);
$aPagarLav = $comissao;

/* ==== Vales da semana ==== */
$vales = [];
$valesTotal = 0.0;

try {
    if ($lavadorId > 0 && $ini !== '' && $fim !== '') {
        $sqlV = "
            SELECT id, valor, motivo, forma_pagamento, criado_em
              FROM vales_lavadores_peca
             WHERE empresa_cnpj = :c
               AND lavador_id   = :l
               AND criado_em BETWEEN :ini AND :fim
             ORDER BY criado_em DESC, id DESC
             LIMIT 200
        ";
        $st = $pdo->prepare($sqlV);
        $st->execute([
            ':c'   => $cnpj,
            ':l'   => $lavadorId,
            ':ini' => $ini,
            ':fim' => $fim,
        ]);

        $vales = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($vales as $vv) {
            $valesTotal += (float)($vv['valor'] ?? 0);
        }
        $valesTotal = round($valesTotal, 2);
    }
} catch (Throwable $e) {
    $vales = [];
    $valesTotal = 0.0;
}

$saldoARepasse = round($aPagarLav - $valesTotal, 2);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Resumo do Lavador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            margin: 0;
            padding: 24px;
            color: #111827;
            background: #fff;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .subtitle {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
        }

        .print-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #111827;
            color: #fff;
            border-color: #111827;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
        }

        .card .label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .card .value {
            font-size: 20px;
            font-weight: 800;
        }

        .section {
            margin-top: 18px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 10px;
        }

        .pill-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .pill {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            font-size: 13px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }

        .text-end {
            text-align: right;
        }

        .muted {
            color: #6b7280;
        }

        .summary-box {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-top: 16px;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-actions {
                display: none !important;
            }

            .card, .summary-box {
                break-inside: avoid;
            }
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <h1 class="title">Resumo do Lavador</h1>
            <p class="subtitle">
                <strong><?= h((string)$empresaNome) ?></strong><br>
                Lavador: <strong><?= h((string)$det['lavador']) ?></strong><br>
                Semana: <strong><?= h($period !== '' ? $period : ($iniDate . ' – ' . $fimDate)) ?></strong>
            </p>
        </div>

        <div class="print-actions">
            <button class="btn" onclick="window.history.back()">Voltar</button>
            <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="label">Lavagens</div>
            <div class="value"><?= (int)($det['qtd'] ?? 0) ?></div>
        </div>

        <div class="card">
            <div class="label">Bruto</div>
            <div class="value"><?= money($bruto) ?></div>
        </div>

        <div class="card">
            <div class="label">Utilidades (<?= pct($uPct) ?>)</div>
            <div class="value">- <?= money($descU) ?></div>
        </div>

        <div class="card">
            <div class="label">Saldo a repassar</div>
            <div class="value"><?= money($saldoARepasse) ?></div>
        </div>
    </div>

    <div class="pill-row">
        <div class="pill">Comissão (<?= pct($cPct) ?>): <strong><?= money($comissao) ?></strong></div>
        <div class="pill">A pagar (pré-vale): <strong><?= money($aPagarLav) ?></strong></div>
        <div class="pill">Vales da semana: <strong>- <?= money($valesTotal) ?></strong></div>
    </div>

    <div class="section">
        <h2 class="section-title">Serviços da semana</h2>

        <?php if (empty($det['items'])): ?>
            <div class="muted">Nenhuma lavagem registrada para este lavador nesta semana.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 120px;">Data/Hora</th>
                        <th>Serviço</th>
                        <th>Veículo</th>
                        <th style="width: 120px;">Pagamento</th>
                        <th style="width: 120px;">Status</th>
                        <th class="text-end" style="width: 130px;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($det['items'] as $item): ?>
                        <tr>
                            <td><?= h((string)$item['quando']) ?></td>
                            <td><?= h((string)($item['servico'] ?? '—')) ?></td>
                            <td><?= h((string)($item['veiculo'] ?? '—')) ?></td>
                            <td><?= h((string)($item['forma_pagamento'] ?? '—')) ?></td>
                            <td><?= h((string)($item['status'] ?? '—')) ?></td>
                            <td class="text-end"><?= money((float)($item['valor'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2 class="section-title">Vales da semana</h2>

        <?php if ($lavadorId <= 0): ?>
            <div class="muted">Lavador não identificado para consulta de vales.</div>
        <?php elseif (empty($vales)): ?>
            <div class="muted">Nenhum vale encontrado nesta semana.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 150px;">Criado em</th>
                        <th>Motivo</th>
                        <th style="width: 140px;">Forma</th>
                        <th class="text-end" style="width: 130px;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vales as $v): ?>
                        <tr>
                            <td>#<?= (int)$v['id'] ?></td>
                            <td><?= h(date('d/m/Y H:i', strtotime((string)$v['criado_em']))) ?></td>
                            <td><?= h((string)($v['motivo'] ?? '—')) ?></td>
                            <td><?= h((string)($v['forma_pagamento'] ?? '—')) ?></td>
                            <td class="text-end"><?= money((float)($v['valor'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="summary-box">
        <strong>Resumo final</strong><br><br>
        Bruto: <?= money($bruto) ?><br>
        Utilidades: - <?= money($descU) ?><br>
        Comissão: <?= money($comissao) ?><br>
        Vales da semana: - <?= money($valesTotal) ?><br>
        <strong>Saldo a repassar: <?= money($saldoARepasse) ?></strong>
    </div>

</body>
</html>