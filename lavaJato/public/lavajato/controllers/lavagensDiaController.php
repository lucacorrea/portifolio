<?php
// autoErp/public/lavajato/controllers/lavagensDiaController.php
declare(strict_types=1);

if (!function_exists('empresa_cnpj_logada')) {
    function empresa_cnpj_logada(PDO $pdo): ?string
    {
        if (!empty($_SESSION['empresa_cnpj'])) {
            return (string)$_SESSION['empresa_cnpj'];
        }

        try {
            if (!empty($_SESSION['empresa_identificador'])) {
                $st = $pdo->prepare("SELECT cnpj FROM empresas_peca WHERE identificador = :ident LIMIT 1");
                $st->execute([':ident' => (string)$_SESSION['empresa_identificador']]);
                $x = $st->fetchColumn();
                if ($x) {
                    $_SESSION['empresa_cnpj'] = (string)$x;
                    return (string)$x;
                }
            }
        } catch (Throwable $e) {
        }

        try {
            if (!empty($_SESSION['empresa_id'])) {
                $st = $pdo->prepare("SELECT cnpj FROM empresas_peca WHERE id = :id LIMIT 1");
                $st->execute([':id' => (int)$_SESSION['empresa_id']]);
                $x = $st->fetchColumn();
                if ($x) {
                    $_SESSION['empresa_cnpj'] = (string)$x;
                    return (string)$x;
                }
            }
        } catch (Throwable $e) {
        }

        try {
            if (!empty($_SESSION['user_id'])) {
                $st = $pdo->prepare("SELECT empresa_cnpj FROM usuarios_peca WHERE id = :id LIMIT 1");
                $st->execute([':id' => (int)$_SESSION['user_id']]);
                $x = $st->fetchColumn();
                if ($x) {
                    $_SESSION['empresa_cnpj'] = (string)$x;
                    return (string)$x;
                }
            }
        } catch (Throwable $e) {
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

if (!function_exists('dia_label_pt')) {
    function dia_label_pt(string $dia): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dia);
        if (!$dt) {
            return $dia;
        }

        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        return $dt->format('d/m/Y') . ' (' . $dias[(int)$dt->format('w')] . ')';
    }
}

if (!function_exists('lav_key_and_label')) {
    function lav_key_and_label(?string $cpf, ?string $nome): array
    {
        $cpf  = trim((string)$cpf);
        $nome = trim((string)$nome);

        if ($cpf !== '') {
            return [
                'key'   => 'CPF:' . preg_replace('/\D+/', '', $cpf),
                'label' => ($nome !== '' ? $nome : 'CPF ' . $cpf),
            ];
        }

        if ($nome !== '') {
            return [
                'key'   => 'N:' . $nome,
                'label' => $nome,
            ];
        }

        return [
            'key'   => 'desconhecido',
            'label' => '—',
        ];
    }
}

if (!function_exists('veiculo_composto')) {
    function veiculo_composto(?string $modelo, ?string $cor, ?string $placa): string
    {
        $modelo = trim((string)$modelo);
        $cor    = trim((string)$cor);
        $placa  = trim((string)$placa);

        $parts = [];
        if ($modelo !== '') {
            $parts[] = $modelo;
        }
        if ($cor !== '') {
            $parts[] = "($cor)";
        }

        $v = trim(implode(' ', $parts));

        if ($placa !== '') {
            $v .= ($v !== '' ? ' ' : '') . '[' . $placa . ']';
        }

        return $v !== '' ? $v : '-';
    }
}

if (!function_exists('lavagens_base_rows')) {
    function lavagens_base_rows(PDO $pdo, string $cnpj, string $ini, string $fim, string $q = ''): array
    {
        $sql = "
            SELECT
                l.id,
                COALESCE(l.checkin_at, l.criado_em) AS data_evento,
                l.categoria_nome,
                l.modelo,
                l.cor,
                l.placa,
                l.valor,
                l.forma_pagamento,
                l.status,
                l.lavador_cpf,
                u.nome AS lavador_nome
            FROM lavagens_peca l
            LEFT JOIN lavadores_peca u
              ON u.empresa_cnpj = l.empresa_cnpj
             AND REPLACE(REPLACE(REPLACE(u.cpf,'.',''),'-',''),'/','') =
                 REPLACE(REPLACE(REPLACE(l.lavador_cpf,'.',''),'-',''),'/','')
            WHERE l.empresa_cnpj = :cnpj
              AND COALESCE(l.checkin_at, l.criado_em) BETWEEN :ini AND :fim
        ";

        $bind = [
            ':cnpj' => $cnpj,
            ':ini'  => $ini,
            ':fim'  => $fim,
        ];

        if ($q !== '') {
            $sql .= "
              AND (
                    u.nome LIKE :q
                 OR l.lavador_cpf LIKE :q
                 OR l.placa LIKE :q
                 OR l.modelo LIKE :q
                 OR l.categoria_nome LIKE :q
              )
            ";
            $bind[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY data_evento ASC, l.id ASC";

        $st = $pdo->prepare($sql);
        $st->execute($bind);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('lavagens_mes_por_dia_viewmodel')) {
    function lavagens_mes_por_dia_viewmodel(PDO $pdo, array $params = []): array
    {
        $mes = (string)($params['mes'] ?? '');
        $q   = trim((string)($params['q'] ?? ''));

        if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => 'Parâmetro de mês inválido.',
                'dias' => [],
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
                'mes' => $mes,
                'q' => $q,
            ];
        }

        $cnpj = empresa_cnpj_logada($pdo);
        if (!$cnpj) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => 'Empresa não identificada.',
                'dias' => [],
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
                'mes' => $mes,
                'q' => $q,
            ];
        }

        [$y, $m] = array_map('intval', explode('-', $mes));
        $iniMes = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m));
        $fimMes = $iniMes->modify('last day of this month')->setTime(23, 59, 59);

        try {
            $rows = lavagens_base_rows(
                $pdo,
                $cnpj,
                $iniMes->format('Y-m-d H:i:s'),
                $fimMes->format('Y-m-d H:i:s'),
                $q
            );
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => 'Erro ao consultar lavagens do mês: ' . $e->getMessage(),
                'dias' => [],
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
                'mes' => $mes,
                'q' => $q,
            ];
        }

        $dias = [];
        $lavSetGeral = [];
        $qtdGeral = 0;
        $totalGeral = 0.0;

        foreach ($rows as $row) {
            $rawData = (string)($row['data_evento'] ?? '');
            $ts = strtotime($rawData);
            if ($ts === false) {
                continue;
            }

            $diaKey = date('Y-m-d', $ts);

            if (!isset($dias[$diaKey])) {
                $dias[$diaKey] = [
                    'dia'       => $diaKey,
                    'label'     => dia_label_pt($diaKey),
                    'qtd'       => 0,
                    'total'     => 0.0,
                    'lavadores' => 0,
                    '_lav_set'  => [],
                ];
            }

            $valor = (float)($row['valor'] ?? 0);
            $lk = lav_key_and_label($row['lavador_cpf'] ?? '', $row['lavador_nome'] ?? '');

            $dias[$diaKey]['qtd']++;
            $dias[$diaKey]['total'] += $valor;
            $dias[$diaKey]['_lav_set'][$lk['key']] = true;

            $qtdGeral++;
            $totalGeral += $valor;
            $lavSetGeral[$lk['key']] = true;
        }

        foreach ($dias as &$dia) {
            $dia['lavadores'] = count($dia['_lav_set']);
            unset($dia['_lav_set']);
        }
        unset($dia);

        ksort($dias);

        return [
            'ok' => true,
            'err' => false,
            'msg' => '',
            'dias' => array_values($dias),
            'resumo' => [
                'qtd'       => $qtdGeral,
                'total'     => round($totalGeral, 2),
                'lavadores' => count($lavSetGeral),
            ],
            'mes' => $mes,
            'mes_label' => mes_label_pt($mes),
            'q' => $q,
        ];
    }
}

if (!function_exists('lavagens_dia_por_lavador_viewmodel')) {
    function lavagens_dia_por_lavador_viewmodel(PDO $pdo, array $params = []): array
    {
        $mes = (string)($params['mes'] ?? '');
        $dia = (string)($params['dia'] ?? '');
        $q   = trim((string)($params['q'] ?? ''));
        $lav = (string)($params['lav'] ?? '');

        if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
            return ['ok' => false, 'err' => true, 'msg' => 'Mês inválido.'];
        }

        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dia)) {
            return ['ok' => false, 'err' => true, 'msg' => 'Dia inválido.'];
        }

        if (strpos($dia, $mes . '-') !== 0) {
            return ['ok' => false, 'err' => true, 'msg' => 'O dia não pertence ao mês informado.'];
        }

        $cnpj = empresa_cnpj_logada($pdo);
        if (!$cnpj) {
            return ['ok' => false, 'err' => true, 'msg' => 'Empresa não identificada.'];
        }

        $ini = $dia . ' 00:00:00';
        $fim = $dia . ' 23:59:59';

        try {
            $rows = lavagens_base_rows($pdo, $cnpj, $ini, $fim, $q);
        } catch (Throwable $e) {
            return ['ok' => false, 'err' => true, 'msg' => 'Erro ao consultar lavagens do dia: ' . $e->getMessage()];
        }

        $map = [];
        $lavSet = [];
        $qtdGeral = 0;
        $totalGeral = 0.0;

        foreach ($rows as $row) {
            $lk = lav_key_and_label($row['lavador_cpf'] ?? '', $row['lavador_nome'] ?? '');
            $valor = (float)($row['valor'] ?? 0);
            $quando = (string)($row['data_evento'] ?? '');

            if (!isset($map[$lk['key']])) {
                $map[$lk['key']] = [
                    'lavador' => $lk['label'],
                    'lav_key' => $lk['key'],
                    'qtd'     => 0,
                    'total'   => 0.0,
                    'items'   => [],
                ];
            }

            $map[$lk['key']]['qtd']++;
            $map[$lk['key']]['total'] += $valor;
            $map[$lk['key']]['items'][] = [
                'id'              => (int)($row['id'] ?? 0),
                'quando'          => $quando !== '' ? date('d/m/Y H:i', strtotime($quando)) : '',
                'servico'         => (string)($row['categoria_nome'] ?? '-'),
                'veiculo'         => veiculo_composto($row['modelo'] ?? '', $row['cor'] ?? '', $row['placa'] ?? ''),
                'forma_pagamento' => (string)($row['forma_pagamento'] ?? ''),
                'valor'           => $valor,
                'status'          => (string)($row['status'] ?? ''),
            ];

            $lavSet[$lk['key']] = true;
            $qtdGeral++;
            $totalGeral += $valor;
        }

        uasort($map, static function (array $a, array $b): int {
            return strcasecmp((string)$a['lavador'], (string)$b['lavador']);
        });

        $lavadores = array_values($map);
        $detalhe = null;

        if ($lav !== '') {
            foreach ($lavadores as $item) {
                if ((string)$item['lav_key'] === $lav) {
                    $detalhe = $item;
                    break;
                }
            }
        }

        return [
            'ok' => true,
            'err' => false,
            'msg' => '',
            'mes' => $mes,
            'mes_label' => mes_label_pt($mes),
            'dia' => $dia,
            'dia_label' => dia_label_pt($dia),
            'periodo_ini' => $ini,
            'periodo_fim' => $fim,
            'periodo_label' => dia_label_pt($dia),
            'lavadores' => $lavadores,
            'detalhe' => $detalhe,
            'resumo' => [
                'qtd'       => $qtdGeral,
                'total'     => round($totalGeral, 2),
                'lavadores' => count($lavSet),
            ],
            'q' => $q,
            'lav' => $lav,
        ];
    }
}