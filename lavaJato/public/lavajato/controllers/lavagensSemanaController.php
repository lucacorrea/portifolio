<?php
// autoErp/public/lavajato/controllers/lavagensSemanaController.php
declare(strict_types=1);

/* ============================================================
   EMPRESA / HELPERS BASE
   ============================================================ */
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

if (!function_exists('lav_key_and_label')) {
    function lav_key_and_label(?string $cpf, ?string $nome): array
    {
        $cpf  = preg_replace('/\D+/', '', (string)$cpf);
        $nome = trim((string)$nome);

        if ($cpf !== '') {
            return [
                'key'   => 'CPF:' . $cpf,
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

if (!function_exists('semana_label_pt')) {
    function semana_label_pt(DateTimeImmutable $ini, DateTimeImmutable $fim): string
    {
        return $ini->format('d/m/Y') . ' – ' . $fim->format('d/m/Y');
    }
}

if (!function_exists('semana_ref_from_ini')) {
    function semana_ref_from_ini(DateTimeImmutable $ini): string
    {
        return $ini->format('Y-m-d');
    }
}

if (!function_exists('parse_week_ref_or_dates')) {
    function parse_week_ref_or_dates(array $params): array
    {
        $weekRef = trim((string)($params['week_ref'] ?? ''));
        $iniStr  = trim((string)($params['ini'] ?? ''));
        $fimStr  = trim((string)($params['fim'] ?? ''));

        if ($weekRef !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $weekRef);
            if (!$dt) {
                throw new RuntimeException('week_ref inválido.');
            }

            // força segunda-feira
            $dow = (int)$dt->format('N'); // 1..7
            $ini = $dt->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
            $fim = $ini->modify('+6 days')->setTime(23, 59, 59);

            return [$ini, $fim];
        }

        if ($iniStr !== '' && $fimStr !== '') {
            $ini = DateTimeImmutable::createFromFormat('Y-m-d', $iniStr);
            $fim = DateTimeImmutable::createFromFormat('Y-m-d', $fimStr);

            if (!$ini || !$fim) {
                throw new RuntimeException('Período inválido.');
            }

            $ini = $ini->setTime(0, 0, 0);
            $fim = $fim->setTime(23, 59, 59);

            if ($ini > $fim) {
                throw new RuntimeException('Período inválido: início maior que fim.');
            }

            if ((int)$ini->diff($fim)->format('%a') !== 6) {
                throw new RuntimeException('A semana precisa ter exatamente 7 dias.');
            }

            return [$ini, $fim];
        }

        throw new RuntimeException('Informe week_ref ou ini/fim.');
    }
}

if (!function_exists('construir_intervalo_por_range')) {
    function construir_intervalo_por_range(?string $range): array
    {
        $today = new DateTimeImmutable('today');

        if (preg_match('/^\d+$/', (string)$range)) {
            $days = max(1, (int)$range);
            $ini = $today->modify("-{$days} days");
            $fim = $today->modify('+1 day')->modify('-1 second');
            return [$ini, $fim];
        }

        $ini = $today->modify('-365 days');
        $fim = $today->modify('+1 day')->modify('-1 second');
        return [$ini, $fim];
    }
}

if (!function_exists('iniciar_semana_segunda')) {
    function iniciar_semana_segunda(DateTimeImmutable $dt): DateTimeImmutable
    {
        $dow = (int)$dt->format('N'); // 1..7
        return $dt->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
    }
}

/* ============================================================
   QUERY BASE
   ============================================================ */
if (!function_exists('lavagens_rows_por_periodo')) {
    function lavagens_rows_por_periodo(PDO $pdo, string $cnpj, string $ini, string $fim, string $q = ''): array
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

/* ============================================================
   VIEWMODEL: LISTA DE SEMANAS REAIS
   ============================================================ */
if (!function_exists('lavagens_por_semana_viewmodel')) {
    function lavagens_por_semana_viewmodel(PDO $pdo, array $params = []): array
    {
        $range = (string)($params['range'] ?? '365');
        $q     = trim((string)($params['q'] ?? ''));

        $cnpj = empresa_cnpj_logada($pdo);
        if (!$cnpj) {
            return [
                'ok'     => false,
                'err'    => true,
                'msg'    => 'Empresa não identificada.',
                'semanas'=> [],
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0, 'semanas' => 0],
                'range'  => $range,
                'q'      => $q,
            ];
        }

        [$iniBusca, $fimBusca] = construir_intervalo_por_range($range);

        try {
            $rows = lavagens_rows_por_periodo(
                $pdo,
                $cnpj,
                $iniBusca->format('Y-m-d H:i:s'),
                $fimBusca->format('Y-m-d H:i:s'),
                $q
            );
        } catch (Throwable $e) {
            return [
                'ok'     => false,
                'err'    => true,
                'msg'    => 'Erro ao consultar lavagens: ' . $e->getMessage(),
                'semanas'=> [],
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0, 'semanas' => 0],
                'range'  => $range,
                'q'      => $q,
            ];
        }

        $semanas = [];
        $lavadoresGerais = [];
        $qtdGeral = 0;
        $totalGeral = 0.0;

        foreach ($rows as $row) {
            $dataEvento = (string)($row['data_evento'] ?? '');

            try {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dataEvento)
                    ?: new DateTimeImmutable($dataEvento);
            } catch (Throwable $e) {
                continue;
            }

            $iniSemana = iniciar_semana_segunda($dt);
            $fimSemana = $iniSemana->modify('+6 days')->setTime(23, 59, 59);
            $weekRef   = semana_ref_from_ini($iniSemana);
            $lavKey    = lav_key_and_label($row['lavador_cpf'] ?? '', $row['lavador_nome'] ?? '')['key'];
            $valor     = (float)($row['valor'] ?? 0);

            if (!isset($semanas[$weekRef])) {
                $semanas[$weekRef] = [
                    'week_ref'   => $weekRef,
                    'ini'        => $iniSemana->format('Y-m-d'),
                    'fim'        => $fimSemana->format('Y-m-d'),
                    'label'      => semana_label_pt($iniSemana, $fimSemana),
                    'mes_ref'    => $iniSemana->format('Y-m'),
                    'mes_label'  => mes_label_pt($iniSemana->format('Y-m')),
                    'qtd'        => 0,
                    'total'      => 0.0,
                    'lavadores'  => 0,
                    '_lav_set'   => [],
                ];
            }

            $semanas[$weekRef]['qtd']++;
            $semanas[$weekRef]['total'] += $valor;
            $semanas[$weekRef]['_lav_set'][$lavKey] = true;

            $qtdGeral++;
            $totalGeral += $valor;
            $lavadoresGerais[$lavKey] = true;
        }

        foreach ($semanas as &$semana) {
            $semana['lavadores'] = count($semana['_lav_set']);
            $semana['total'] = round((float)$semana['total'], 2);
            unset($semana['_lav_set']);
        }
        unset($semana);

        if (!empty($semanas)) {
            krsort($semanas);
        }

        return [
            'ok'      => true,
            'err'     => false,
            'msg'     => '',
            'semanas' => array_values($semanas),
            'resumo'  => [
                'qtd'       => $qtdGeral,
                'total'     => round($totalGeral, 2),
                'lavadores' => count($lavadoresGerais),
                'semanas'   => count($semanas),
            ],
            'range'   => $range,
            'q'       => $q,
        ];
    }
}

/* ============================================================
   VIEWMODEL: LAVADORES DE UMA SEMANA REAL
   ============================================================ */
if (!function_exists('lavagens_semana_por_lavador_viewmodel')) {
    function lavagens_semana_por_lavador_viewmodel(PDO $pdo, array $params = []): array
    {
        $q   = trim((string)($params['q'] ?? ''));
        $lav = trim((string)($params['lav'] ?? ''));

        try {
            [$iniSemana, $fimSemana] = parse_week_ref_or_dates($params);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => $e->getMessage(),
                'lavadores' => [],
                'detalhe' => null,
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
            ];
        }

        $cnpj = empresa_cnpj_logada($pdo);
        if (!$cnpj) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => 'Empresa não identificada.',
                'lavadores' => [],
                'detalhe' => null,
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
            ];
        }

        try {
            $rows = lavagens_rows_por_periodo(
                $pdo,
                $cnpj,
                $iniSemana->format('Y-m-d H:i:s'),
                $fimSemana->format('Y-m-d H:i:s'),
                $q
            );
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'err' => true,
                'msg' => 'Erro ao consultar lavagens da semana: ' . $e->getMessage(),
                'lavadores' => [],
                'detalhe' => null,
                'resumo' => ['qtd' => 0, 'total' => 0.0, 'lavadores' => 0],
            ];
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
            'ok'           => true,
            'err'          => false,
            'msg'          => '',
            'week_ref'     => semana_ref_from_ini($iniSemana),
            'periodo_ini'  => $iniSemana->format('Y-m-d 00:00:00'),
            'periodo_fim'  => $fimSemana->format('Y-m-d 23:59:59'),
            'ini'          => $iniSemana->format('Y-m-d'),
            'fim'          => $fimSemana->format('Y-m-d'),
            'periodo_label'=> semana_label_pt($iniSemana, $fimSemana),
            'lavadores'    => $lavadores,
            'detalhe'      => $detalhe,
            'resumo'       => [
                'qtd'       => $qtdGeral,
                'total'     => round($totalGeral, 2),
                'lavadores' => count($lavSet),
            ],
            'q'            => $q,
            'lav'          => $lav,
        ];
    }
}