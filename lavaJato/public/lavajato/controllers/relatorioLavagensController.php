<?php
// autoErp/public/lavajato/controllers/relatorioLavagensController.php
declare(strict_types=1);

function relatorio_lavagens_viewmodel(PDO $pdo, array $filtros): array
{
    date_default_timezone_set('America/Manaus');

    $empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? $_SESSION['empresa_cnpj'] ?? ''));
    $nf = normalize_filtros_relatorio($filtros);

    if (!preg_match('/^\d{14}$/', $empresaCnpj)) {
        return [
            'err' => 1,
            'msg' => 'Empresa não vinculada ao usuário.',
            'filtros' => $nf,
            'selects' => build_selects_relatorio($pdo, $empresaCnpj),
            'resumo' => ['qtd' => 0, 'total' => 0, 'lavadores' => 0],
            'ticket_medio' => 0,
            'porLavador' => [],
            'porServico' => [],
            'porDia' => [],
            'porForma' => [],
            'linhas' => [],
        ];
    }

    /**
     * REGRA DE DATA:
     * - concluida  => checkout_at
     * - aberta     => checkin_at
     * - cancelada  => fallback
     */
    $dataBase = "
        CASE
            WHEN l.status = 'concluida' AND l.checkout_at IS NOT NULL THEN l.checkout_at
            WHEN l.status = 'aberta' AND l.checkin_at IS NOT NULL THEN l.checkin_at
            ELSE COALESCE(l.checkout_at, l.checkin_at, l.criado_em)
        END
    ";

    $params = [':empresa' => $empresaCnpj];
    $where  = ["l.empresa_cnpj = :empresa"];

    // Status
    if (($nf['status'] ?? 'concluida') !== 'todos') {
        $where[] = "l.status = :status";
        $params[':status'] = $nf['status'];
    }

    // Forma
    if (($nf['forma'] ?? '') !== '') {
        $where[] = "l.forma_pagamento = :forma";
        $params[':forma'] = $nf['forma'];
    }

    // Lavador (nome ou CPF)
    if (($nf['lavador'] ?? '') !== '') {
        $lav = trim((string)$nf['lavador']);
        $lavCpf = preg_replace('/\D+/', '', $lav);

        if ($lavCpf !== '' && strlen($lavCpf) >= 6) {
            $where[] = "(
                REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.lavador_cpf,''),'.',''),'-',''),'/',''),' ','') LIKE :lavCpf
                OR lv.nome LIKE :lavNome
            )";
            $params[':lavCpf']  = '%' . $lavCpf . '%';
            $params[':lavNome'] = '%' . $lav . '%';
        } else {
            $where[] = "(lv.nome LIKE :lavNome OR l.lavador_cpf LIKE :lavNome)";
            $params[':lavNome'] = '%' . $lav . '%';
        }
    }

    // Serviço
    if (($nf['servico'] ?? '') !== '') {
        $where[] = "(COALESCE(NULLIF(l.categoria_nome,''), c.nome, '') LIKE :servico)";
        $params[':servico'] = '%' . trim((string)$nf['servico']) . '%';
    }

    // Período
    if ($nf['ini'] !== '' && $nf['fim'] !== '') {
        $where[] = "$dataBase BETWEEN :iniDt AND :fimDt";
        $params[':iniDt'] = $nf['ini'] . ' 00:00:00';
        $params[':fimDt'] = $nf['fim'] . ' 23:59:59';
    } elseif ($nf['ini'] !== '') {
        $where[] = "$dataBase >= :iniDt";
        $params[':iniDt'] = $nf['ini'] . ' 00:00:00';
    } elseif ($nf['fim'] !== '') {
        $where[] = "$dataBase <= :fimDt";
        $params[':fimDt'] = $nf['fim'] . ' 23:59:59';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $joinLavador = "
        LEFT JOIN lavadores_peca lv
          ON lv.empresa_cnpj = l.empresa_cnpj
         AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(lv.cpf,''),'.',''),'-',''),'/',''),' ','')
          = REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.lavador_cpf,''),'.',''),'-',''),'/',''),' ','')
    ";

    $joinCategoria = "
        LEFT JOIN categorias_lavagem_peca c
          ON c.id = l.categoria_id
         AND c.empresa_cnpj = l.empresa_cnpj
    ";

    // ====== LINHAS ======
    $sqlLinhas = "
        SELECT
            l.id,
            $dataBase AS quando_raw,
            COALESCE(NULLIF(lv.nome,''), l.lavador_cpf, '—') AS lavador,
            COALESCE(NULLIF(l.categoria_nome,''), c.nome, '—') AS servico,
            CONCAT_WS(' ', NULLIF(l.modelo,''), NULLIF(l.cor,'')) AS veiculo,
            l.forma_pagamento,
            l.valor,
            l.status,
            l.checkin_at,
            l.checkout_at,
            l.criado_em
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
        ORDER BY $dataBase DESC, l.id DESC
    ";
    $st = $pdo->prepare($sqlLinhas);
    $st->execute($params);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ====== RESUMO ======
    $sqlResumo = "
        SELECT
            COUNT(*) AS qtd,
            COALESCE(SUM(l.valor),0) AS total,
            COUNT(DISTINCT REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.lavador_cpf,''),'.',''),'-',''),'/',''),' ','')) AS lavadores
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
    ";
    $st = $pdo->prepare($sqlResumo);
    $st->execute($params);
    $resumo = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0, 'lavadores' => 0];

    $qtd    = (int)($resumo['qtd'] ?? 0);
    $total  = (float)($resumo['total'] ?? 0);
    $ticket = $qtd > 0 ? ($total / $qtd) : 0;

    // ====== AGRUPADOS ======
    $porLavador = group_relatorio($pdo, "
        SELECT
            COALESCE(NULLIF(lv.nome,''), l.lavador_cpf, '—') AS lavador,
            COUNT(*) AS qtd,
            COALESCE(SUM(l.valor),0) AS total
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
        GROUP BY COALESCE(NULLIF(lv.nome,''), l.lavador_cpf, '—')
        ORDER BY total DESC, qtd DESC
        LIMIT 20
    ", $params);

    $porServico = group_relatorio($pdo, "
        SELECT
            COALESCE(NULLIF(l.categoria_nome,''), c.nome, '—') AS servico,
            COUNT(*) AS qtd,
            COALESCE(SUM(l.valor),0) AS total
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
        GROUP BY COALESCE(NULLIF(l.categoria_nome,''), c.nome, '—')
        ORDER BY total DESC, qtd DESC
        LIMIT 20
    ", $params);

    $porDia = group_relatorio($pdo, "
        SELECT
            DATE($dataBase) AS dia,
            COALESCE(SUM(l.valor),0) AS total
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
        GROUP BY DATE($dataBase)
        ORDER BY dia ASC
    ", $params);

    $porForma = group_relatorio($pdo, "
        SELECT
            COALESCE(NULLIF(l.forma_pagamento,''),'—') AS forma,
            COALESCE(SUM(l.valor),0) AS total
        FROM lavagens_peca l
        $joinLavador
        $joinCategoria
        $whereSql
        GROUP BY COALESCE(NULLIF(l.forma_pagamento,''),'—')
        ORDER BY total DESC
    ", $params);

    return [
        'err' => 0,
        'msg' => '',
        'filtros' => $nf,
        'selects' => build_selects_relatorio($pdo, $empresaCnpj),
        'resumo' => [
            'qtd' => $qtd,
            'total' => $total,
            'lavadores' => (int)($resumo['lavadores'] ?? 0),
        ],
        'ticket_medio' => $ticket,
        'porLavador' => $porLavador,
        'porServico' => $porServico,
        'porDia' => $porDia,
        'porForma' => $porForma,
        'linhas' => $linhas,
    ];
}

function normalize_filtros_relatorio(array $f): array
{
    $ini = trim((string)($f['ini'] ?? ''));
    $fim = trim((string)($f['fim'] ?? ''));

    if ($ini !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = '';
    if ($fim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) $fim = '';

    if ($ini !== '' && $fim !== '' && $ini > $fim) {
        [$ini, $fim] = [$fim, $ini];
    }

    $status = (string)($f['status'] ?? 'concluida');
    $allowed = ['concluida', 'aberta', 'cancelada', 'todos'];
    if (!in_array($status, $allowed, true)) $status = 'concluida';

    return [
        'ini' => $ini,
        'fim' => $fim,
        'status' => $status,
        'forma' => trim((string)($f['forma'] ?? '')),
        'lavador' => trim((string)($f['lavador'] ?? '')),
        'servico' => trim((string)($f['servico'] ?? '')),
    ];
}

function build_selects_relatorio(PDO $pdo, string $empresaCnpj): array
{
    $statuses = ['concluida', 'aberta', 'cancelada', 'todos'];

    $formas = [];
    if (preg_match('/^\d{14}$/', $empresaCnpj)) {
        $st = $pdo->prepare("
            SELECT DISTINCT COALESCE(NULLIF(forma_pagamento,''),'—') AS forma
            FROM lavagens_peca
            WHERE empresa_cnpj = :empresa
            ORDER BY forma ASC
        ");
        $st->execute([':empresa' => $empresaCnpj]);
        $formas = array_values(array_filter(array_map(
            fn($r) => (string)($r['forma'] ?? ''),
            $st->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    $st = $pdo->prepare("
        SELECT nome, cpf
        FROM lavadores_peca
        WHERE empresa_cnpj = :empresa AND ativo = 1
        ORDER BY nome ASC
    ");
    $st->execute([':empresa' => $empresaCnpj]);
    $lavadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $st = $pdo->prepare("
        SELECT nome
        FROM categorias_lavagem_peca
        WHERE empresa_cnpj = :empresa AND ativo = 1
        ORDER BY nome ASC
    ");
    $st->execute([':empresa' => $empresaCnpj]);
    $cats = array_map(fn($r) => (string)$r['nome'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $st = $pdo->prepare("
        SELECT DISTINCT categoria_nome AS nome
        FROM lavagens_peca
        WHERE empresa_cnpj = :empresa AND categoria_nome IS NOT NULL AND categoria_nome <> ''
        ORDER BY categoria_nome ASC
    ");
    $st->execute([':empresa' => $empresaCnpj]);
    $snap = array_map(fn($r) => (string)$r['nome'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $servicos = array_values(array_unique(array_filter(array_merge($cats, $snap))));

    return [
        'statuses' => $statuses,
        'formas' => $formas,
        'lavadores' => $lavadores,
        'servicos' => $servicos,
    ];
}

function group_relatorio(PDO $pdo, string $sql, array $params): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}