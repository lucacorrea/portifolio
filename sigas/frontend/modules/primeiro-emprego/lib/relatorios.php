<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/lotacoes.php';

function pe_rel_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return $cache[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}


function pe_rel_month_label(string $ym): string
{
    [$year, $month] = array_pad(explode('-', $ym), 2, '00');
    $months = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    return ($months[$month] ?? $month) . '/' . substr($year, -2);
}

function pe_rel_lotacao_case(): string
{
    return 'CASE
        WHEN (
            UPPER(TRIM(COALESCE(i.setor_informado, ""))) IN (
                "NÃO VEIO DA SARA", "NAO VEIO DA SARA",
                "NÃO ESTA NA SARA", "NAO ESTA NA SARA",
                "NÃO ESTÁ NA SARA", "NAO ESTÁ NA SARA",
                "PRIMEIRA VEZ", "PRIMEIRE VEZ",
                "NÃO INFORMADO", "NAO INFORMADO",
                "SEM INFORMAÇÃO", "SEM INFORMACAO",
                "INÊS DE NAZAERÉ", "INES DE NAZAERE",
                "DARQUILANA AMORIM", "BERIANO"
            )
            OR UPPER(TRIM(COALESCE(i.setor_informado, ""))) LIKE "%PROCURAR%CRACH%"
            OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) IN (
                "NÃO VEIO DA SARA", "NAO VEIO DA SARA",
                "NÃO ESTA NA SARA", "NAO ESTA NA SARA",
                "NÃO ESTÁ NA SARA", "NAO ESTÁ NA SARA",
                "PRIMEIRA VEZ", "PRIMEIRE VEZ",
                "NÃO INFORMADO", "NAO INFORMADO",
                "SEM INFORMAÇÃO", "SEM INFORMACAO",
                "INÊS DE NAZAERÉ", "INES DE NAZAERE",
                "DARQUILANA AMORIM", "BERIANO"
            )
            OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%PROCURAR%CRACH%"
        ) THEN "Revisar lotação"
        WHEN l.id IS NOT NULL THEN "Lotado"
        WHEN i.setor_informado IS NULL OR TRIM(i.setor_informado) = "" THEN "Não lotado"
        ELSE "Pronto para importar"
    END';
}

function pe_rel_joins(PDO $pdo): string
{
    $partnerSigla = pe_partner_has_sigla($pdo) ? 'p.sigla' : 'NULL';

    return '
        LEFT JOIN pe_lotacoes l
            ON l.candidato_id = c.id
           AND l.status = "Ativa"
        LEFT JOIN pe_parceiros p
            ON p.id = l.parceiro_id
        LEFT JOIN (
            SELECT i1.*
            FROM pe_importacao_itens i1
            INNER JOIN (
                SELECT candidato_id, MAX(id) AS ultimo_id
                FROM pe_importacao_itens
                WHERE candidato_id IS NOT NULL
                GROUP BY candidato_id
            ) ult ON ult.ultimo_id = i1.id
        ) i ON i.candidato_id = c.id
    ';
}

function pe_rel_filter_parts(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];
    $lotacaoCase = pe_rel_lotacao_case();

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(c.nome LIKE :q_nome
            OR c.cpf LIKE :q_cpf
            OR c.cpf_informado LIKE :q_cpf_inf
            OR c.telefone LIKE :q_tel
            OR c.bairro LIKE :q_bairro
            OR c.responsavel_familiar LIKE :q_resp
            OR l.local_atuacao LIKE :q_local
            OR l.setor LIKE :q_setor
            OR p.nome LIKE :q_parceiro'
            . (pe_partner_has_sigla($pdo) ? ' OR p.sigla LIKE :q_sigla' : '') . ')';
        foreach (['q_nome','q_cpf','q_cpf_inf','q_tel','q_bairro','q_resp','q_local','q_setor','q_parceiro'] as $key) {
            $params[$key] = $like;
        }
        if (pe_partner_has_sigla($pdo)) {
            $params['q_sigla'] = $like;
        }
    }

    if (!empty($filters['status'])) {
        $where[] = 'c.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if (!empty($filters['bairro'])) {
        $where[] = 'c.bairro = :bairro';
        $params['bairro'] = (string) $filters['bairro'];
    }

    if (!empty($filters['parceiro_id'])) {
        $where[] = 'l.parceiro_id = :parceiro_id';
        $params['parceiro_id'] = (int) $filters['parceiro_id'];
    }

    if (!empty($filters['setor'])) {
        $where[] = '(l.local_atuacao = :setor_local OR l.setor = :setor_setor)';
        $params['setor_local'] = (string) $filters['setor'];
        $params['setor_setor'] = (string) $filters['setor'];
    }

    if (!empty($filters['origem'])) {
        $where[] = 'c.origem = :origem';
        $params['origem'] = (string) $filters['origem'];
    }

    if (!empty($filters['sexo'])) {
        $where[] = 'c.sexo = :sexo';
        $params['sexo'] = (string) $filters['sexo'];
    }

    $review = trim((string) ($filters['revisao'] ?? ''));
    if ($review === 'pendentes') {
        $where[] = 'c.revisao_status IS NOT NULL AND c.revisao_status <> ""';
    } elseif ($review === 'sem_pendencia') {
        $where[] = '(c.revisao_status IS NULL OR c.revisao_status = "")';
    } elseif ($review === 'cpf') {
        $where[] = 'c.revisao_cpf = 1';
    } elseif ($review === 'telefone') {
        $where[] = 'c.revisao_telefone = 1';
    } elseif ($review === 'nascimento') {
        $where[] = 'c.revisao_nascimento = 1';
    } elseif ($review === 'cadastro') {
        $where[] = 'c.revisao_status = "Revisar Cadastro"';
    } elseif ($review === 'cpf_duplicado') {
        $where[] = 'c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0';
    }

    $lotacao = trim((string) ($filters['lotacao'] ?? ''));
    if ($lotacao === 'lotado') {
        $where[] = '(' . $lotacaoCase . ') = "Lotado"';
    } elseif ($lotacao === 'nao_lotado') {
        $where[] = '(' . $lotacaoCase . ') = "Não lotado"';
    } elseif ($lotacao === 'revisar') {
        $where[] = '(' . $lotacaoCase . ') = "Revisar lotação"';
    } elseif ($lotacao === 'pronto_importar') {
        $where[] = '(' . $lotacaoCase . ') = "Pronto para importar"';
    }

    $idadeMin = trim((string) ($filters['idade_min'] ?? ''));
    if ($idadeMin !== '' && ctype_digit($idadeMin)) {
        $where[] = 'c.data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) >= :idade_min';
        $params['idade_min'] = (int) $idadeMin;
    }

    $idadeMax = trim((string) ($filters['idade_max'] ?? ''));
    if ($idadeMax !== '' && ctype_digit($idadeMax)) {
        $where[] = 'c.data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) <= :idade_max';
        $params['idade_max'] = (int) $idadeMax;
    }

    $dataInicio = trim((string) ($filters['data_inicio'] ?? ''));
    if ($dataInicio !== '') {
        $where[] = 'DATE(c.created_at) >= :data_inicio';
        $params['data_inicio'] = $dataInicio;
    }

    $dataFim = trim((string) ($filters['data_fim'] ?? ''));
    if ($dataFim !== '') {
        $where[] = 'DATE(c.created_at) <= :data_fim';
        $params['data_fim'] = $dataFim;
    }

    return [$where, $params];
}

function pe_rel_candidate_id_subquery(PDO $pdo, array $filters): array
{
    [$where, $params] = pe_rel_filter_parts($pdo, $filters);
    $sql = 'SELECT c.id FROM pe_candidatos c ' . pe_rel_joins($pdo);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    return [$sql, $params];
}

function pe_rel_candidate_count(PDO $pdo, array $filters): int
{
    [$where, $params] = pe_rel_filter_parts($pdo, $filters);
    $sql = 'SELECT COUNT(*) FROM pe_candidatos c ' . pe_rel_joins($pdo);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function pe_rel_candidate_rows(PDO $pdo, array $filters, ?int $limit = null, int $offset = 0): array
{
    [$where, $params] = pe_rel_filter_parts($pdo, $filters);
    $lotacaoCase = pe_rel_lotacao_case();
    $siglaSelect = pe_partner_has_sigla($pdo) ? 'p.sigla AS parceiro_sigla,' : 'NULL AS parceiro_sigla,';

    $sql = 'SELECT
        c.id,
        c.nome,
        c.sexo,
        c.data_nascimento,
        c.responsavel_familiar,
        c.bairro,
        COALESCE(NULLIF(c.endereco, ""), TRIM(CONCAT(COALESCE(c.rua, ""), " ", COALESCE(c.ponto_referencia, "")))) AS endereco,
        c.telefone,
        COALESCE(NULLIF(c.cpf, ""), c.cpf_informado) AS cpf,
        c.status,
        c.origem,
        c.revisao_status,
        c.revisao_motivos,
        c.cpf_duplicado,
        c.created_at,
        TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) AS idade,
        l.id AS lotacao_id,
        l.parceiro_id,
        l.local_atuacao,
        l.setor,
        l.turno_atuacao,
        l.data_inicio AS lotacao_inicio,
        p.nome AS parceiro_nome,
        ' . $siglaSelect . '
        i.setor_informado,
        ' . $lotacaoCase . ' AS situacao_lotacao,
        (SELECT v.decisao FROM pe_visitas_sociais v WHERE v.candidato_id = c.id ORDER BY v.data_visita DESC, v.id DESC LIMIT 1) AS parecer,
        (SELECT v.data_visita FROM pe_visitas_sociais v WHERE v.candidato_id = c.id ORDER BY v.data_visita DESC, v.id DESC LIMIT 1) AS data_ultima_visita,
        (SELECT e.status FROM pe_encaminhamentos e WHERE e.candidato_id = c.id ORDER BY e.data_encaminhamento DESC, e.id DESC LIMIT 1) AS encaminhamento_status,
        (SELECT COUNT(*) FROM pe_documentos d WHERE d.candidato_id = c.id AND d.status IN ("Pendente", "Revisar")) AS documentos_pendentes
    FROM pe_candidatos c ' . pe_rel_joins($pdo);

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY
        CASE
            WHEN c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0 THEN 0
            WHEN c.revisao_status = "Revisar Cadastro" THEN 1
            WHEN (' . $lotacaoCase . ') = "Revisar lotação" THEN 2
            WHEN (' . $lotacaoCase . ') = "Não lotado" THEN 3
            ELSE 4
        END,
        c.nome ASC';

    if ($limit !== null) {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_rel_summary(PDO $pdo, array $filters): array
{
    [$where, $params] = pe_rel_filter_parts($pdo, $filters);
    $lotacaoCase = pe_rel_lotacao_case();

    $sql = 'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN c.revisao_status IS NULL OR c.revisao_status = "" THEN 1 ELSE 0 END) AS cadastro_regular,
        SUM(CASE WHEN c.revisao_status IS NOT NULL AND c.revisao_status <> "" THEN 1 ELSE 0 END) AS revisao_pendente,
        SUM(CASE WHEN c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0 THEN 1 ELSE 0 END) AS cpf_duplicado,
        SUM(CASE WHEN (' . $lotacaoCase . ') = "Lotado" THEN 1 ELSE 0 END) AS lotados,
        SUM(CASE WHEN (' . $lotacaoCase . ') = "Não lotado" THEN 1 ELSE 0 END) AS nao_lotados,
        SUM(CASE WHEN (' . $lotacaoCase . ') = "Revisar lotação" THEN 1 ELSE 0 END) AS revisar_lotacao,
        SUM(CASE WHEN (' . $lotacaoCase . ') = "Pronto para importar" THEN 1 ELSE 0 END) AS pronto_importar,
        AVG(CASE WHEN c.data_nascimento IS NOT NULL THEN TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) END) AS idade_media
    FROM pe_candidatos c ' . pe_rel_joins($pdo);

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'cadastro_regular' => (int) ($row['cadastro_regular'] ?? 0),
        'revisao_pendente' => (int) ($row['revisao_pendente'] ?? 0),
        'cpf_duplicado' => (int) ($row['cpf_duplicado'] ?? 0),
        'lotados' => (int) ($row['lotados'] ?? 0),
        'nao_lotados' => (int) ($row['nao_lotados'] ?? 0),
        'revisar_lotacao' => (int) ($row['revisar_lotacao'] ?? 0),
        'pronto_importar' => (int) ($row['pronto_importar'] ?? 0),
        'idade_media' => isset($row['idade_media']) ? (float) $row['idade_media'] : 0.0,
    ];
}

function pe_rel_group(PDO $pdo, array $filters, string $expression, int $limit = 20): array
{
    [$where, $params] = pe_rel_filter_parts($pdo, $filters);
    $limit = max(3, min($limit, 50));

    $sql = 'SELECT ' . $expression . ' AS label, COUNT(*) AS total
        FROM pe_candidatos c ' . pe_rel_joins($pdo);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY label ORDER BY total DESC, label ASC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn(array $row): array => [
        'label' => (string) ($row['label'] ?? 'Não informado'),
        'value' => (int) ($row['total'] ?? 0),
    ], $rows);
}

function pe_rel_distributions(PDO $pdo, array $filters): array
{
    $lotacaoCase = pe_rel_lotacao_case();
    $partnerExpr = pe_partner_has_sigla($pdo)
        ? 'COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.sigla, ""), CASE WHEN p.sigla IS NOT NULL AND p.sigla <> "" THEN " — " ELSE "" END, p.nome)), ""), "Sem parceiro")'
        : 'COALESCE(NULLIF(p.nome, ""), "Sem parceiro")';

    return [
        'status' => pe_rel_group($pdo, $filters, 'COALESCE(NULLIF(c.status, ""), "Sem status")', 12),
        'lotacao' => pe_rel_group($pdo, $filters, $lotacaoCase, 8),
        'revisao' => pe_rel_group($pdo, $filters, 'COALESCE(NULLIF(c.revisao_status, ""), "Regular")', 10),
        'sexo' => pe_rel_group($pdo, $filters, 'COALESCE(NULLIF(c.sexo, ""), "Não informado")', 10),
        'origem' => pe_rel_group($pdo, $filters, 'COALESCE(NULLIF(c.origem, ""), "Não informada")', 10),
        'bairros' => pe_rel_group($pdo, $filters, 'COALESCE(NULLIF(c.bairro, ""), "Não informado")', 10),
        'parceiros' => pe_rel_group($pdo, $filters, $partnerExpr, 10),
        'idades' => pe_rel_group($pdo, $filters, 'CASE
            WHEN c.data_nascimento IS NULL THEN "Não informada"
            WHEN TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) < 18 THEN "Menos de 18"
            WHEN TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) BETWEEN 18 AND 20 THEN "18 a 20"
            WHEN TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) BETWEEN 21 AND 24 THEN "21 a 24"
            WHEN TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) BETWEEN 25 AND 29 THEN "25 a 29"
            ELSE "30 ou mais"
        END', 10),
    ];
}

function pe_rel_latest_competencia(PDO $pdo, string $table, string $candidateSubquery, array $params): ?string
{
    if (!pe_rel_table_exists($pdo, $table)) {
        return null;
    }

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $sql = 'SELECT MAX(competencia) FROM ' . $safe . ' t WHERE t.candidato_id IN (' . $candidateSubquery . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value ? (string) $value : null;
}

function pe_rel_operational(PDO $pdo, array $filters): array
{
    [$candidateSubquery, $params] = pe_rel_candidate_id_subquery($pdo, $filters);

    $visitSql = 'SELECT
        COUNT(*) AS total,
        SUM(decisao = "Deferido") AS deferidos,
        SUM(decisao = "Indeferido") AS indeferidos,
        SUM(decisao = "Pendente") AS pendentes
        FROM pe_visitas_sociais v
        WHERE v.candidato_id IN (' . $candidateSubquery . ')';
    $stmt = $pdo->prepare($visitSql);
    $stmt->execute($params);
    $visitas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $refSql = 'SELECT COALESCE(NULLIF(e.status, ""), "Sem status") AS label, COUNT(*) AS total
        FROM pe_encaminhamentos e
        WHERE e.candidato_id IN (' . $candidateSubquery . ')
        GROUP BY label ORDER BY total DESC, label ASC';
    $stmt = $pdo->prepare($refSql);
    $stmt->execute($params);
    $refRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $docSql = 'SELECT COALESCE(NULLIF(d.status, ""), "Sem status") AS label, COUNT(*) AS total
        FROM pe_documentos d
        WHERE d.candidato_id IN (' . $candidateSubquery . ')
        GROUP BY label ORDER BY total DESC, label ASC';
    $stmt = $pdo->prepare($docSql);
    $stmt->execute($params);
    $docRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $docSummarySql = 'SELECT
        COUNT(*) AS total,
        SUM(d.status IN ("Pendente", "Revisar")) AS pendentes,
        SUM(d.validade IS NOT NULL AND d.validade < CURDATE()) AS vencidos,
        SUM(d.arquivo_path IS NULL OR TRIM(d.arquivo_path) = "") AS sem_arquivo
        FROM pe_documentos d
        WHERE d.candidato_id IN (' . $candidateSubquery . ')';
    $stmt = $pdo->prepare($docSummarySql);
    $stmt->execute($params);
    $docSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $freqComp = pe_rel_latest_competencia($pdo, 'pe_frequencias', $candidateSubquery, $params);
    $frequency = ['competencia' => $freqComp, 'media' => 0.0, 'total' => 0, 'series' => []];
    if ($freqComp !== null) {
        $freqSql = 'SELECT
            COUNT(*) AS total,
            AVG(f.percentual) AS media,
            SUM(f.percentual >= 90) AS f90,
            SUM(f.percentual >= 75 AND f.percentual < 90) AS f75,
            SUM(f.percentual > 0 AND f.percentual < 75) AS fbaixo,
            SUM(f.percentual <= 0) AS fzero
            FROM pe_frequencias f
            WHERE f.competencia = :freq_comp
              AND f.candidato_id IN (' . $candidateSubquery . ')';
        $freqParams = $params;
        $freqParams['freq_comp'] = $freqComp;
        $stmt = $pdo->prepare($freqSql);
        $stmt->execute($freqParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $frequency = [
            'competencia' => $freqComp,
            'media' => (float) ($row['media'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
            'series' => [
                ['label' => '90% ou mais', 'value' => (int) ($row['f90'] ?? 0)],
                ['label' => '75% a 89,99%', 'value' => (int) ($row['f75'] ?? 0)],
                ['label' => 'Abaixo de 75%', 'value' => (int) ($row['fbaixo'] ?? 0)],
                ['label' => 'Sem presença', 'value' => (int) ($row['fzero'] ?? 0)],
            ],
        ];
    }

    $grantComp = pe_rel_latest_competencia($pdo, 'pe_bolsas', $candidateSubquery, $params);
    $grants = ['competencia' => $grantComp, 'total' => 0, 'valor_pago' => 0.0, 'series' => []];
    if ($grantComp !== null) {
        $grantSql = 'SELECT COALESCE(NULLIF(b.status, ""), "Sem status") AS label,
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN b.status = "Paga" THEN b.valor ELSE 0 END), 0) AS valor_pago
            FROM pe_bolsas b
            WHERE b.competencia = :grant_comp
              AND b.candidato_id IN (' . $candidateSubquery . ')
            GROUP BY label ORDER BY total DESC, label ASC';
        $grantParams = $params;
        $grantParams['grant_comp'] = $grantComp;
        $stmt = $pdo->prepare($grantSql);
        $stmt->execute($grantParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total = 0;
        $valorPago = 0.0;
        $series = [];
        foreach ($rows as $row) {
            $total += (int) $row['total'];
            $valorPago += (float) $row['valor_pago'];
            $series[] = ['label' => (string) $row['label'], 'value' => (int) $row['total']];
        }
        $grants = ['competencia' => $grantComp, 'total' => $total, 'valor_pago' => $valorPago, 'series' => $series];
    }

    $network = [
        'parceiros_ativos' => 0,
        'vagas_abertas' => 0,
        'vagas_total' => 0,
        'capacitacoes_ativas' => 0,
    ];
    try {
        $row = $pdo->query('SELECT
            (SELECT COUNT(*) FROM pe_parceiros WHERE status = "Ativa") AS parceiros_ativos,
            (SELECT COUNT(*) FROM pe_vagas WHERE status = "Aberta") AS vagas_abertas,
            (SELECT COUNT(*) FROM pe_vagas) AS vagas_total,
            (SELECT COUNT(*) FROM pe_capacitacoes WHERE status IN ("Inscrições abertas", "Em andamento")) AS capacitacoes_ativas')->fetch(PDO::FETCH_ASSOC) ?: [];
        $network = [
            'parceiros_ativos' => (int) ($row['parceiros_ativos'] ?? 0),
            'vagas_abertas' => (int) ($row['vagas_abertas'] ?? 0),
            'vagas_total' => (int) ($row['vagas_total'] ?? 0),
            'capacitacoes_ativas' => (int) ($row['capacitacoes_ativas'] ?? 0),
        ];
    } catch (Throwable) {
    }

    return [
        'visitas' => [
            'total' => (int) ($visitas['total'] ?? 0),
            'deferidos' => (int) ($visitas['deferidos'] ?? 0),
            'indeferidos' => (int) ($visitas['indeferidos'] ?? 0),
            'pendentes' => (int) ($visitas['pendentes'] ?? 0),
            'series' => [
                ['label' => 'Deferido', 'value' => (int) ($visitas['deferidos'] ?? 0)],
                ['label' => 'Indeferido', 'value' => (int) ($visitas['indeferidos'] ?? 0)],
                ['label' => 'Pendente', 'value' => (int) ($visitas['pendentes'] ?? 0)],
            ],
        ],
        'encaminhamentos' => array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $refRows),
        'documentos' => array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $docRows),
        'documentos_resumo' => [
            'total' => (int) ($docSummary['total'] ?? 0),
            'pendentes' => (int) ($docSummary['pendentes'] ?? 0),
            'vencidos' => (int) ($docSummary['vencidos'] ?? 0),
            'sem_arquivo' => (int) ($docSummary['sem_arquivo'] ?? 0),
        ],
        'frequencia' => $frequency,
        'bolsas' => $grants,
        'rede' => $network,
    ];
}

function pe_rel_filter_options(PDO $pdo): array
{
    $bairros = $pdo->query('SELECT DISTINCT bairro FROM pe_candidatos WHERE bairro IS NOT NULL AND TRIM(bairro) <> "" ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $status = $pdo->query('SELECT DISTINCT status FROM pe_candidatos WHERE status IS NOT NULL AND TRIM(status) <> "" ORDER BY status')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $origens = $pdo->query('SELECT DISTINCT origem FROM pe_candidatos WHERE origem IS NOT NULL AND TRIM(origem) <> "" ORDER BY origem')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $sexos = $pdo->query('SELECT DISTINCT sexo FROM pe_candidatos WHERE sexo IS NOT NULL AND TRIM(sexo) <> "" ORDER BY sexo')->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $setores = $pdo->query('SELECT local FROM (
        SELECT DISTINCT local_atuacao AS local FROM pe_lotacoes WHERE status="Ativa" AND local_atuacao IS NOT NULL AND TRIM(local_atuacao)<>""
        UNION
        SELECT DISTINCT setor AS local FROM pe_lotacoes WHERE status="Ativa" AND setor IS NOT NULL AND TRIM(setor)<>""
    ) x ORDER BY local')->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $siglaSelect = pe_partner_has_sigla($pdo) ? 'sigla,' : 'NULL AS sigla,';
    $partners = $pdo->query('SELECT id, nome, ' . $siglaSelect . ' status FROM pe_parceiros ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'bairros' => $bairros,
        'status' => $status,
        'origens' => $origens,
        'sexos' => $sexos,
        'setores' => $setores,
        'partners' => $partners,
    ];
}
