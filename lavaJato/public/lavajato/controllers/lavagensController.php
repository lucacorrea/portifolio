<?php
// autoErp/public/lavajato/controllers/lavagensController.php
declare(strict_types=1);

/* ===================== Empresa / Sessão ===================== */
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

/* ===================== Datas / Labels ===================== */
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

if (!function_exists('mes_label_pt')) {
    function mes_label_pt(string $ym): string
    {
        [$y, $m] = explode('-', $ym);
        $meses = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
        ];
        $idx = (int)$m;
        return ($meses[$idx] ?? $ym) . '/' . $y;
    }
}

if (!function_exists('lavador_chave_mes')) {
    function lavador_chave_mes(?string $cpf, ?string $nome): string
    {
        $cpf = preg_replace('/\D+/', '', (string)$cpf);
        $nome = trim((string)$nome);

        if ($cpf !== '') {
            return 'CPF:' . $cpf;
        }

        if ($nome !== '') {
            return 'N:' . $nome;
        }

        return 'desconhecido';
    }
}

/* ===================== ViewModel — Lavagens por MÊS (resumo) ===================== */
if (!function_exists('lavagens_por_mes_viewmodel')) {
    function lavagens_por_mes_viewmodel(PDO $pdo, array $params = []): array
    {
        $range = (string)($params['range'] ?? '365');
        $q     = trim((string)($params['q'] ?? ''));

        $cnpj = empresa_cnpj_logada($pdo);
        if (!$cnpj) {
            return [
                'ok'     => false,
                'err'    => true,
                'msg'    => 'Empresa não identificada.',
                'meses'  => [],
                'resumo' => [
                    'qtd'       => 0,
                    'total'     => 0.0,
                    'lavadores' => 0,
                    'meses'     => 0,
                ],
                'range'  => $range,
                'q'      => $q,
            ];
        }

        [$ini, $fim] = construir_intervalo_por_range($range);

        $sql = "
            SELECT
                COALESCE(l.checkin_at, l.criado_em) AS data_evento,
                l.valor,
                l.placa,
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
            ':ini'  => $ini->format('Y-m-d H:i:s'),
            ':fim'  => $fim->format('Y-m-d H:i:s'),
        ];

        if ($q !== '') {
            $sql .= "
                AND (
                    u.nome LIKE :q
                    OR l.lavador_cpf LIKE :q
                    OR l.placa LIKE :q
                )
            ";
            $bind[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY data_evento DESC";

        try {
            $st = $pdo->prepare($sql);
            $st->execute($bind);
        } catch (Throwable $e) {
            return [
                'ok'     => false,
                'err'    => true,
                'msg'    => 'Erro ao consultar lavagens: ' . $e->getMessage(),
                'meses'  => [],
                'resumo' => [
                    'qtd'       => 0,
                    'total'     => 0.0,
                    'lavadores' => 0,
                    'meses'     => 0,
                ],
                'range'  => $range,
                'q'      => $q,
            ];
        }

        $meses = [];
        $lavadoresGerais = [];
        $qtdGeral = 0;
        $totalGeral = 0.0;

        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $dataEvento = (string)($row['data_evento'] ?? '');

            try {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dataEvento)
                    ?: new DateTimeImmutable($dataEvento);
            } catch (Throwable $e) {
                continue;
            }

            $mesKey = $dt->format('Y-m');
            $lavKey = lavador_chave_mes($row['lavador_cpf'] ?? '', $row['lavador_nome'] ?? '');
            $valor  = (float)($row['valor'] ?? 0);

            if (!isset($meses[$mesKey])) {
                $meses[$mesKey] = [
                    'mes'       => $mesKey,
                    'label'     => mes_label_pt($mesKey),
                    'qtd'       => 0,
                    'lavadores' => 0,
                    'total'     => 0.0,
                    '_lav_set'  => [],
                ];
            }

            $meses[$mesKey]['qtd']++;
            $meses[$mesKey]['total'] += $valor;
            $meses[$mesKey]['_lav_set'][$lavKey] = true;

            $qtdGeral++;
            $totalGeral += $valor;
            $lavadoresGerais[$lavKey] = true;
        }

        foreach ($meses as &$mesItem) {
            $mesItem['lavadores'] = count($mesItem['_lav_set']);
            $mesItem['total'] = round((float)$mesItem['total'], 2);
            unset($mesItem['_lav_set']);
        }
        unset($mesItem);

        if (!empty($meses)) {
            krsort($meses);
        }

        return [
            'ok'     => true,
            'err'    => false,
            'msg'    => '',
            'meses'  => array_values($meses),
            'resumo' => [
                'qtd'       => $qtdGeral,
                'total'     => round($totalGeral, 2),
                'lavadores' => count($lavadoresGerais),
                'meses'     => count($meses),
            ],
            'range'  => $range,
            'q'      => $q,
        ];
    }
}