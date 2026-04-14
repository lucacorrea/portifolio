<?php
// autoErp/public/lavajato/controllers/listaLavagens.php
declare(strict_types=1);

/* ===================== Empresa / Sessão ===================== */
function empresa_cnpj_logada(PDO $pdo): ?string
{
    if (!empty($_SESSION['empresa_cnpj'])) return (string)$_SESSION['empresa_cnpj'];

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

/* ===================== Datas ===================== */
function hoje_intervalo(): array
{
    date_default_timezone_set('America/Manaus');

    $inicio = new DateTimeImmutable('today 00:00:00', new DateTimeZone('America/Manaus'));
    $fim    = new DateTimeImmutable('today 23:59:59', new DateTimeZone('America/Manaus'));

    return [$inicio, $fim];
}

/**
 * Retorna intervalo conforme período:
 * - hoje   => today 00:00:00 até today 23:59:59
 * - semana => monday this week 00:00:00 até sunday this week 23:59:59
 * - mes    => first day this month 00:00:00 até last day this month 23:59:59
 * - ano    => first day this year 00:00:00 até last day this year 23:59:59
 * - todos  => sem filtro de data
 */
function periodo_intervalo(string $period): array
{
    date_default_timezone_set('America/Manaus');
    $tz = new DateTimeZone('America/Manaus');
    $period = strtolower(trim($period));

    if ($period === 'semana') {
        $inicio = new DateTimeImmutable('monday this week 00:00:00', $tz);
        $fim    = new DateTimeImmutable('sunday this week 23:59:59', $tz);
        return [$inicio, $fim, 'Semana'];
    }

    if ($period === 'mes') {
        $inicio = new DateTimeImmutable('first day of this month 00:00:00', $tz);
        $fim    = new DateTimeImmutable('last day of this month 23:59:59', $tz);
        return [$inicio, $fim, 'Mês'];
    }

    if ($period === 'ano') {
        $inicio = new DateTimeImmutable('first day of january this year 00:00:00', $tz);
        $fim    = new DateTimeImmutable('last day of december this year 23:59:59', $tz);
        return [$inicio, $fim, 'Ano'];
    }

    if ($period === 'todos') {
        return [null, null, 'Todos os períodos'];
    }

    // default: hoje
    [$inicio, $fim] = hoje_intervalo();
    return [$inicio, $fim, 'Hoje'];
}

/* ===================== ViewModel — ABERTAS + PAGINAÇÃO + PESQUISA + PERÍODO ===================== */
function lavagens_abertas(PDO $pdo, array $params = []): array
{
    date_default_timezone_set('America/Manaus');

    $cnpj = empresa_cnpj_logada($pdo);
    if (!$cnpj) {
        return [
            'ok' => false,
            'err' => true,
            'msg' => 'Empresa não identificada.',
            'dados' => [],
            'resumo' => ['qtd' => 0, 'total' => 0, 'total_rows' => 0],
            'paginacao' => [
                'page' => 1,
                'per_page' => 10,
                'total_rows' => 0,
                'total_pages' => 0,
                'has_prev' => false,
                'has_next' => false,
            ]
        ];
    }

    $q = trim((string)($params['q'] ?? ''));

    $page = (int)($params['page'] ?? 1);
    if ($page < 1) $page = 1;

    $perPage = (int)($params['per_page'] ?? 10);
    if ($perPage < 1) $perPage = 10;
    if ($perPage > 100) $perPage = 100;

    $offset = ($page - 1) * $perPage;

    // ✅ período
    $period = strtolower(trim((string)($params['period'] ?? 'hoje')));
    if (!in_array($period, ['hoje', 'semana', 'mes', 'ano', 'todos'], true)) {
        $period = 'hoje';
    }

    [$inicio, $fim, $periodLabel] = periodo_intervalo($period);

    try {
        // JOIN com lavadores_peca + normalização CPF
        $join = "
            LEFT JOIN lavadores_peca lv
              ON REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(lv.cpf,''), '.', ''), '-', ''), '/', ''), ' ', '')
               = REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.lavador_cpf,''), '.', ''), '-', ''), '/', ''), ' ', '')
        ";

        // Base WHERE
        $where = "
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.empresa_cnpj,''),'.',''),'-',''),'/',''),' ','') = :cnpj
              AND l.status IN ('aberta','lavando')
        ";

        $cnpjNorm = preg_replace('/\D+/', '', (string)$cnpj) ?: (string)$cnpj;

        $bind = [
            ':cnpj' => $cnpjNorm,
        ];

        // ✅ aplica filtro de período só quando NÃO for "todos"
        if ($period !== 'todos' && $inicio instanceof DateTimeImmutable && $fim instanceof DateTimeImmutable) {
            $where .= " AND COALESCE(l.checkin_at, l.criado_em) BETWEEN :inicio AND :fim ";
            $bind[':inicio'] = $inicio->format('Y-m-d H:i:s');
            $bind[':fim']    = $fim->format('Y-m-d H:i:s');
        }

        // 🔎 Busca “por tudo” (sem repetir :q)
        if ($q !== '') {
            $isNum = preg_match('/^\d+$/', $q) === 1;

            if ($isNum) {
                $bind[':id_exact'] = (int)$q;
                $like = "%{$q}%";
                $bind[':q1'] = $like;
                $bind[':q2'] = $like;
                $bind[':q3'] = $like;
                $bind[':q4'] = $like;
                $bind[':q5'] = $like;
                $bind[':q6'] = $like;

                $where .= "
                    AND (
                        l.id = :id_exact
                        OR CAST(l.id AS CHAR) LIKE :q1
                        OR COALESCE(l.placa,'') LIKE :q2
                        OR COALESCE(l.modelo,'') LIKE :q3
                        OR COALESCE(l.cor,'') LIKE :q4
                        OR COALESCE(l.categoria_nome,'') LIKE :q5
                        OR COALESCE(l.lavador_cpf,'') LIKE :q6
                        OR COALESCE(lv.nome,'') LIKE :q6
                        OR CAST(COALESCE(l.valor,0) AS CHAR) LIKE :q6
                    )
                ";
            } else {
                $like = "%{$q}%";
                $bind[':q1'] = $like; // placa
                $bind[':q2'] = $like; // modelo
                $bind[':q3'] = $like; // cor
                $bind[':q4'] = $like; // categoria
                $bind[':q5'] = $like; // lavador cpf
                $bind[':q6'] = $like; // lavador nome
                $bind[':q7'] = $like; // valor texto
                $bind[':q8'] = $like; // id texto

                $where .= "
                    AND (
                        COALESCE(l.placa,'') LIKE :q1
                        OR COALESCE(l.modelo,'') LIKE :q2
                        OR COALESCE(l.cor,'') LIKE :q3
                        OR COALESCE(l.categoria_nome,'') LIKE :q4
                        OR COALESCE(l.lavador_cpf,'') LIKE :q5
                        OR COALESCE(lv.nome,'') LIKE :q6
                        OR CAST(COALESCE(l.valor,0) AS CHAR) LIKE :q7
                        OR CAST(l.id AS CHAR) LIKE :q8
                    )
                ";
            }
        }

        // total
        $sqlCount = "SELECT COUNT(*) FROM lavagens_peca l {$join} {$where}";
        $stC = $pdo->prepare($sqlCount);
        foreach ($bind as $k => $v) {
            $stC->bindValue($k, $v);
        }
        $stC->execute();
        $totalRows = (int)$stC->fetchColumn();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        // dados
        $sqlData = "
            SELECT
                l.*,
                COALESCE(NULLIF(lv.nome,''), l.lavador_cpf) AS lavador_nome
            FROM lavagens_peca l
            {$join}
            {$where}
            ORDER BY COALESCE(l.checkin_at, l.criado_em) DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $st = $pdo->prepare($sqlData);
        foreach ($bind as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $dados = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // resumo página
        $qtd = 0;
        $totalValor = 0.0;
        foreach ($dados as $d) {
            $qtd++;
            $totalValor += (float)($d['valor'] ?? 0);
        }

        $hasPrev = ($page > 1);
        $hasNext = ($page < $totalPages) && $totalRows > 0;

        return [
            'ok' => true,
            'err' => false,
            'msg' => '',
            'period' => $period,
            'period_label' => $periodLabel,
            'range' => [
                'inicio' => $inicio instanceof DateTimeImmutable ? $inicio->format('Y-m-d H:i:s') : null,
                'fim'    => $fim instanceof DateTimeImmutable ? $fim->format('Y-m-d H:i:s') : null,
            ],
            'dados' => $dados,
            'resumo' => [
                'qtd' => $qtd,
                'total' => $totalValor,
                'total_rows' => $totalRows,
            ],
            'paginacao' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'has_prev' => $hasPrev,
                'has_next' => $hasNext,
            ],
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'err' => true,
            'msg' => 'Erro ao consultar lavagens: ' . $e->getMessage(),
            'period' => $period,
            'period_label' => $periodLabel ?? 'Hoje',
            'dados' => [],
            'resumo' => ['qtd' => 0, 'total' => 0, 'total_rows' => 0],
            'paginacao' => [
                'page' => 1,
                'per_page' => $perPage,
                'total_rows' => 0,
                'total_pages' => 0,
                'has_prev' => false,
                'has_next' => false,
            ]
        ];
    }
}