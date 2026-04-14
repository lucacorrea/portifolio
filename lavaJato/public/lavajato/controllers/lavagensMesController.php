<?php
// autoErp/public/lavajato/controllers/lavagensMesController.php
declare(strict_types=1);

if (!function_exists('empresa_cnpj_logada')) {
    function empresa_cnpj_logada(PDO $pdo): ?string
    {
        if (!empty($_SESSION['empresa_cnpj'])) {
            return (string)$_SESSION['empresa_cnpj'];
        }

        try {
            if (!empty($_SESSION['user_id'])) {
                $st = $pdo->prepare("SELECT empresa_cnpj FROM usuarios_peca WHERE id = :id LIMIT 1");
                $st->execute([':id' => (int)$_SESSION['user_id']]);
                $cnpj = $st->fetchColumn();

                if ($cnpj) {
                    $_SESSION['empresa_cnpj'] = (string)$cnpj;
                    return (string)$cnpj;
                }
            }
        } catch (Throwable $e) {
            // silencioso
        }

        return null;
    }
}

if (!function_exists('mes_label_pt')) {
    function mes_label_pt(string $ym): string
    {
        [$y, $m] = explode('-', $ym);
        $meses = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return ($meses[(int)$m] ?? $ym) . '/' . $y;
    }
}

/**
 * Semanas fixas:
 * 1 => 01-07
 * 2 => 08-14
 * 3 => 15-21
 * 4 => 22-último dia
 */
if (!function_exists('semanas_do_mes')) {
    function semanas_do_mes(string $mes): array
    {
        if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
            return [];
        }

        [$y, $m] = array_map('intval', explode('-', $mes));

        $inicioMes = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m));
        $fimMes    = $inicioMes->modify('last day of this month')->setTime(23, 59, 59);
        $last      = (int)$fimMes->format('j');

        return [
            0 => [
                'label'         => 'Semana 1',
                'periodo_ini'   => sprintf('%04d-%02d-01 00:00:00', $y, $m),
                'periodo_fim'   => sprintf('%04d-%02d-07 23:59:59', $y, $m),
                'periodo_label' => '01/' . sprintf('%02d', $m) . ' – 07/' . sprintf('%02d', $m),
                'qtd'           => 0,
                'total'         => 0.0,
                'lavadores'     => 0,
                'items'         => [],
                '_lav_set'      => [],
            ],
            1 => [
                'label'         => 'Semana 2',
                'periodo_ini'   => sprintf('%04d-%02d-08 00:00:00', $y, $m),
                'periodo_fim'   => sprintf('%04d-%02d-14 23:59:59', $y, $m),
                'periodo_label' => '08/' . sprintf('%02d', $m) . ' – 14/' . sprintf('%02d', $m),
                'qtd'           => 0,
                'total'         => 0.0,
                'lavadores'     => 0,
                'items'         => [],
                '_lav_set'      => [],
            ],
            2 => [
                'label'         => 'Semana 3',
                'periodo_ini'   => sprintf('%04d-%02d-15 00:00:00', $y, $m),
                'periodo_fim'   => sprintf('%04d-%02d-21 23:59:59', $y, $m),
                'periodo_label' => '15/' . sprintf('%02d', $m) . ' – 21/' . sprintf('%02d', $m),
                'qtd'           => 0,
                'total'         => 0.0,
                'lavadores'     => 0,
                'items'         => [],
                '_lav_set'      => [],
            ],
            3 => [
                'label'         => 'Semana 4',
                'periodo_ini'   => sprintf('%04d-%02d-22 00:00:00', $y, $m),
                'periodo_fim'   => sprintf('%04d-%02d-%02d 23:59:59', $y, $m, $last),
                'periodo_label' => '22/' . sprintf('%02d', $m) . ' – ' . sprintf('%02d', $last) . '/' . sprintf('%02d', $m),
                'qtd'           => 0,
                'total'         => 0.0,
                'lavadores'     => 0,
                'items'         => [],
                '_lav_set'      => [],
            ],
        ];
    }
}

function lavagens_mes_semanas_viewmodel(PDO $pdo, array $params = []): array
{
    $mes = (string)($params['mes'] ?? '');
    $q   = trim((string)($params['q'] ?? ''));

    $cnpj = empresa_cnpj_logada($pdo);
    if (!$cnpj) {
        return ['ok' => false, 'err' => true, 'msg' => 'Empresa não identificada.', 'semanas' => [], 'resumo' => []];
    }

    if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
        return ['ok' => false, 'err' => true, 'msg' => 'Parâmetro inválido para mês.', 'semanas' => [], 'resumo' => []];
    }

    [$y, $m] = array_map('intval', explode('-', $mes));
    $iniMes = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m));
    $fimMes = $iniMes->modify('last day of this month')->setTime(23, 59, 59);

    $sql = "
      SELECT
        COALESCE(l.checkin_at, l.criado_em) AS data_evento,
        l.categoria_nome, l.modelo, l.cor, l.placa,
        l.valor, l.forma_pagamento, l.status,
        l.lavador_cpf,
        u.nome AS lavador_nome
      FROM lavagens_peca l
      LEFT JOIN lavadores_peca u
        ON u.cpf = l.lavador_cpf
       AND u.empresa_cnpj = l.empresa_cnpj
      WHERE l.empresa_cnpj = :cnpj
        AND COALESCE(l.checkin_at, l.criado_em) BETWEEN :ini AND :fim
    ";

    $bind = [
        ':cnpj' => $cnpj,
        ':ini'  => $iniMes->format('Y-m-d H:i:s'),
        ':fim'  => $fimMes->format('Y-m-d H:i:s'),
    ];

    if ($q !== '') {
        $sql .= " AND (u.nome LIKE :q OR l.lavador_cpf LIKE :q OR l.placa LIKE :q)";
        $bind[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY data_evento ASC";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($bind);
    } catch (Throwable $e) {
        return ['ok' => false, 'err' => true, 'msg' => 'Erro ao consultar: ' . $e->getMessage(), 'semanas' => [], 'resumo' => []];
    }

    $semanas = semanas_do_mes($mes);

    $qtdGeral   = 0;
    $totalGeral = 0.0;
    $lavSetGeral = [];

    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$row['data_evento'])
            ?: new DateTimeImmutable((string)$row['data_evento']);

        $lavNome = (string)($row['lavador_nome'] ?? '');
        $lavCpf  = (string)($row['lavador_cpf'] ?? '');
        $lavador = $lavNome !== '' ? $lavNome : ($lavCpf !== '' ? 'CPF ' . $lavCpf : '—');
        $lavKey  = $lavCpf !== '' ? 'CPF:' . $lavCpf : ($lavNome !== '' ? 'N:' . $lavNome : 'desconhecido');

        $modelo = (string)($row['modelo'] ?? '');
        $cor    = (string)($row['cor'] ?? '');
        $placa  = (string)($row['placa'] ?? '');

        $veiculo = ($modelo || $cor || $placa)
            ? trim($modelo . ($cor ? ' (' . $cor . ')' : '') . ($placa ? (($modelo || $cor) ? ' ' : '') . '[' . $placa . ']' : ''))
            : '-';

        $valor = (float)($row['valor'] ?? 0);

        foreach ($semanas as &$sem) {
            $ini = new DateTimeImmutable($sem['periodo_ini']);
            $fim = new DateTimeImmutable($sem['periodo_fim']);

            if ($dt >= $ini && $dt <= $fim) {
                $sem['items'][] = [
                    'quando'          => $dt->format('d/m/Y H:i'),
                    'lavador'         => $lavador,
                    'servico'         => (string)($row['categoria_nome'] ?? '-'),
                    'veiculo'         => $veiculo,
                    'forma_pagamento' => (string)($row['forma_pagamento'] ?? ''),
                    'valor'           => $valor,
                    'status'          => (string)($row['status'] ?? ''),
                ];

                $sem['qtd']++;
                $sem['total'] += $valor;
                $sem['_lav_set'][$lavKey] = true;
                break;
            }
        }
        unset($sem);

        $qtdGeral++;
        $totalGeral += $valor;
        $lavSetGeral[$lavKey] = true;
    }

    foreach ($semanas as &$S) {
        $S['lavadores'] = count($S['_lav_set']);
        unset($S['_lav_set']);
    }
    unset($S);

    return [
        'ok'      => true,
        'err'     => false,
        'msg'     => '',
        'semanas' => $semanas,
        'resumo'  => [
            'qtd'       => $qtdGeral,
            'total'     => $totalGeral,
            'lavadores' => count($lavSetGeral),
        ],
        'mes'     => $mes,
        'q'       => $q,
    ];
}

if (!function_exists('lavagens_mes_4semanas_viewmodel')) {
    function lavagens_mes_4semanas_viewmodel(PDO $pdo, array $params = []): array
    {
        return lavagens_mes_semanas_viewmodel($pdo, $params);
    }
}