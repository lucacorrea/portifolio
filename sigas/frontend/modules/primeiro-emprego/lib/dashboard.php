<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/lotacoes.php';

function pe_dashboard_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function pe_dashboard_month_label(string $ym): string
{
    [$year, $month] = array_pad(explode('-', $ym), 2, '00');
    $monthsPt = [
        '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
        '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
        '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez',
    ];
    return ($monthsPt[$month] ?? $month) . '/' . substr($year, -2);
}

function pe_dashboard_latest_competencia(PDO $pdo, string $table): ?string
{
    if (!pe_dashboard_table_exists($pdo, $table)) {
        return null;
    }

    try {
        $sql = sprintf('SELECT MAX(competencia) FROM %s', preg_replace('/[^a-zA-Z0-9_]/', '', $table));
        $value = $pdo->query($sql)->fetchColumn();
        return $value ? (string) $value : null;
    } catch (Throwable) {
        return null;
    }
}

function pe_dashboard_status_counts(PDO $pdo): array
{
    $where = pe_final_list_schema_ready($pdo) ? ' WHERE lista_final_ativa = 1' : '';
    $rows = $pdo->query('SELECT COALESCE(NULLIF(status, ""), "Sem status") AS label, COUNT(*) AS total FROM pe_candidatos' . $where . ' GROUP BY COALESCE(NULLIF(status, ""), "Sem status") ORDER BY total DESC, label ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $rows);
}

function pe_dashboard_lotacao_distribution(PDO $pdo): array
{
    if (!pe_dashboard_table_exists($pdo, 'pe_lotacoes')) {
        return [
            ['label' => 'Não lotado', 'value' => 0],
            ['label' => 'Pronto para importar', 'value' => 0],
            ['label' => 'Revisar lotação', 'value' => 0],
            ['label' => 'Lotado', 'value' => 0],
        ];
    }

    $activeWhere = pe_final_list_schema_ready($pdo) ? ' WHERE c.lista_final_ativa = 1' : '';

    $sql = '
        SELECT situacao_lotacao, COUNT(*) AS total
        FROM (
            SELECT
                c.id,
                CASE
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
                END AS situacao_lotacao
            FROM pe_candidatos c
            LEFT JOIN pe_lotacoes l
                ON l.candidato_id = c.id
               AND l.status = "Ativa"
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
            ' . $activeWhere . '
        ) dados
        GROUP BY situacao_lotacao
    ';

    $map = [
        'Não lotado' => 0,
        'Pronto para importar' => 0,
        'Revisar lotação' => 0,
        'Lotado' => 0,
    ];

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string) $row['situacao_lotacao']] = (int) $row['total'];
    }

    $out = [];
    foreach ($map as $label => $value) {
        $out[] = ['label' => $label, 'value' => $value];
    }

    return $out;
}

function pe_dashboard_referral_counts(PDO $pdo): array
{
    if (!pe_dashboard_table_exists($pdo, 'pe_encaminhamentos')) {
        return [];
    }

    $rows = $pdo->query('SELECT COALESCE(NULLIF(status, ""), "Sem status") AS label, COUNT(*) AS total FROM pe_encaminhamentos GROUP BY COALESCE(NULLIF(status, ""), "Sem status") ORDER BY total DESC, label ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $rows);
}

function pe_dashboard_grant_counts(PDO $pdo): array
{
    if (!pe_dashboard_table_exists($pdo, 'pe_bolsas')) {
        return ['competencia' => null, 'series' => []];
    }

    $competencia = pe_dashboard_latest_competencia($pdo, 'pe_bolsas');
    $params = [];
    $where = '';
    if ($competencia) {
        $where = ' WHERE competencia = :competencia ';
        $params['competencia'] = $competencia;
    }

    $stmt = $pdo->prepare('SELECT COALESCE(NULLIF(status, ""), "Sem status") AS label, COUNT(*) AS total FROM pe_bolsas' . $where . 'GROUP BY COALESCE(NULLIF(status, ""), "Sem status") ORDER BY total DESC, label ASC');
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'competencia' => $competencia,
        'series' => array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $rows),
    ];
}

function pe_dashboard_frequency_ranges(PDO $pdo): array
{
    if (!pe_dashboard_table_exists($pdo, 'pe_frequencias')) {
        return ['competencia' => null, 'series' => []];
    }

    $competencia = pe_dashboard_latest_competencia($pdo, 'pe_frequencias');
    if ($competencia === null) {
        return ['competencia' => null, 'series' => []];
    }

    $stmt = $pdo->prepare('
        SELECT
            SUM(CASE WHEN percentual >= 90 THEN 1 ELSE 0 END) AS faixa_90,
            SUM(CASE WHEN percentual >= 75 AND percentual < 90 THEN 1 ELSE 0 END) AS faixa_75_89,
            SUM(CASE WHEN percentual > 0 AND percentual < 75 THEN 1 ELSE 0 END) AS faixa_baixa,
            SUM(CASE WHEN percentual <= 0 THEN 1 ELSE 0 END) AS faixa_zero,
            AVG(percentual) AS media_geral,
            COUNT(*) AS total
        FROM pe_frequencias
        WHERE competencia = :competencia
    ');
    $stmt->execute(['competencia' => $competencia]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'competencia' => $competencia,
        'media_geral' => isset($row['media_geral']) ? (float) $row['media_geral'] : 0.0,
        'total' => isset($row['total']) ? (int) $row['total'] : 0,
        'series' => [
            ['label' => '90% ou mais', 'value' => (int) ($row['faixa_90'] ?? 0)],
            ['label' => '75% a 89,99%', 'value' => (int) ($row['faixa_75_89'] ?? 0)],
            ['label' => 'Abaixo de 75%', 'value' => (int) ($row['faixa_baixa'] ?? 0)],
            ['label' => 'Sem presença', 'value' => (int) ($row['faixa_zero'] ?? 0)],
        ],
    ];
}

function pe_dashboard_top_bairros(PDO $pdo, int $limit = 8): array
{
    $limit = max(3, min($limit, 12));
    $where = pe_final_list_schema_ready($pdo) ? ' WHERE lista_final_ativa = 1' : '';
    $sql = 'SELECT COALESCE(NULLIF(bairro, ""), "Não informado") AS label, COUNT(*) AS total FROM pe_candidatos' . $where . ' GROUP BY COALESCE(NULLIF(bairro, ""), "Não informado") ORDER BY total DESC, label ASC LIMIT ' . $limit;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $rows);
}

function pe_dashboard_top_partners(PDO $pdo, int $limit = 8): array
{
    if (!pe_dashboard_table_exists($pdo, 'pe_parceiros')) {
        return [];
    }

    $limit = max(3, min($limit, 12));
    $partnerDisplay = pe_partner_has_sigla($pdo)
        ? 'TRIM(CONCAT(COALESCE(NULLIF(p.sigla, ""), ""), CASE WHEN p.sigla IS NOT NULL AND p.sigla <> "" THEN " — " ELSE "" END, p.nome))'
        : 'p.nome';

    $sql = '
        SELECT ' . $partnerDisplay . ' AS label, COUNT(l.id) AS total
        FROM pe_parceiros p
        LEFT JOIN pe_lotacoes l
            ON l.parceiro_id = p.id
           AND l.status = "Ativa"
        GROUP BY p.id
        ORDER BY total DESC, p.nome ASC
        LIMIT ' . $limit;

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $rows);
}

function pe_dashboard_pipeline(PDO $pdo): array
{
    $lotacao = pe_dashboard_lotacao_distribution($pdo);
    $lotado = 0;
    foreach ($lotacao as $item) {
        if ($item['label'] === 'Lotado') {
            $lotado = (int) $item['value'];
            break;
        }
    }

    $hasFinal = pe_final_list_schema_ready($pdo);
    $active = $hasFinal ? ' WHERE lista_final_ativa = 1' : '';
    $activeAnd = $hasFinal ? ' AND lista_final_ativa = 1' : '';
    $sql = 'SELECT
        (SELECT COUNT(*) FROM pe_candidatos' . $active . ') AS base_total,
        (SELECT COUNT(*) FROM pe_visitas_sociais) AS visitas,
        (SELECT COUNT(*) FROM pe_visitas_sociais WHERE decisao = "Deferido") AS deferidos,
        (SELECT COUNT(*) FROM pe_candidatos WHERE status = "Contemplado"' . $activeAnd . ') AS contemplados,
        (SELECT COUNT(*) FROM pe_encaminhamentos WHERE status IN ("Encaminhado", "Entrevista marcada", "Aprovado")) AS em_fluxo';
    $row = (array) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    return [
        ['label' => 'Base cadastrada', 'value' => (int) ($row['base_total'] ?? 0)],
        ['label' => 'Visitas registradas', 'value' => (int) ($row['visitas'] ?? 0)],
        ['label' => 'Deferidos', 'value' => (int) ($row['deferidos'] ?? 0)],
        ['label' => 'Contemplados', 'value' => (int) ($row['contemplados'] ?? 0)],
        ['label' => 'Com lotação ativa', 'value' => $lotado],
    ];
}

function pe_dashboard_health(PDO $pdo): array
{
    $bolsaCompetencia = pe_dashboard_latest_competencia($pdo, 'pe_bolsas');
    $freqCompetencia = pe_dashboard_latest_competencia($pdo, 'pe_frequencias');

    $sql = 'SELECT
        (SELECT COUNT(*) FROM pe_parceiros WHERE status = "Ativa") AS parceiros_ativos,
        (SELECT COUNT(*) FROM pe_vagas WHERE status = "Aberta") AS vagas_abertas,
        (SELECT COUNT(*) FROM pe_documentos WHERE status = "Pendente") AS documentos_pendentes,
        (SELECT COUNT(*) FROM pe_documentos WHERE validade IS NOT NULL AND validade < CURDATE()) AS documentos_vencidos,
        (SELECT COUNT(*) FROM pe_capacitacoes WHERE status IN ("Inscrições abertas", "Em andamento")) AS capacitacoes_ativas';

    $row = (array) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    $bolsasEmAberto = 0;
    if ($bolsaCompetencia !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_bolsas WHERE competencia = :competencia AND status <> "Paga"');
        $stmt->execute(['competencia' => $bolsaCompetencia]);
        $bolsasEmAberto = (int) $stmt->fetchColumn();
    }

    $frequenciaBaixa = 0;
    if ($freqCompetencia !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_frequencias WHERE competencia = :competencia AND percentual < 75');
        $stmt->execute(['competencia' => $freqCompetencia]);
        $frequenciaBaixa = (int) $stmt->fetchColumn();
    }

    return [
        ['label' => 'Parceiros ativos', 'value' => (int) ($row['parceiros_ativos'] ?? 0), 'tone' => 'success', 'detail' => 'Rede parceira habilitada'],
        ['label' => 'Vagas abertas', 'value' => (int) ($row['vagas_abertas'] ?? 0), 'tone' => 'info', 'detail' => 'Oportunidades disponíveis'],
        ['label' => 'Documentos pendentes', 'value' => (int) ($row['documentos_pendentes'] ?? 0), 'tone' => 'warning', 'detail' => 'Exigem conferência'],
        ['label' => 'Documentos vencidos', 'value' => (int) ($row['documentos_vencidos'] ?? 0), 'tone' => 'danger', 'detail' => 'Necessitam atualização'],
        ['label' => 'Bolsas em aberto', 'value' => $bolsasEmAberto, 'tone' => 'warning', 'detail' => $bolsaCompetencia ? ('Competência ' . pe_dashboard_month_label($bolsaCompetencia)) : 'Sem competência'],
        ['label' => 'Frequência abaixo do mínimo', 'value' => $frequenciaBaixa, 'tone' => 'danger', 'detail' => $freqCompetencia ? ('Competência ' . pe_dashboard_month_label($freqCompetencia)) : 'Sem competência'],
        ['label' => 'Capacitações ativas', 'value' => (int) ($row['capacitacoes_ativas'] ?? 0), 'tone' => 'info', 'detail' => 'Ações abertas ou em andamento'],
    ];
}

function pe_dashboard_alerts(PDO $pdo, int $limit = 12): array
{
    $limit = max(5, min($limit, 30));

    $sql = '
        SELECT *
        FROM (
            SELECT
                c.id,
                c.nome,
                COALESCE(NULLIF(c.cpf, ""), c.cpf_informado) AS cpf_exibicao,
                COALESCE(NULLIF(c.bairro, ""), "Não informado") AS bairro,
                c.revisao_status,
                i.setor_informado,
                l.local_atuacao,
                CASE
                    WHEN c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0 THEN "CPF duplicado"
                    WHEN c.revisao_status = "Revisar Cadastro" THEN "Cadastro com múltiplas pendências"
                    WHEN c.revisao_status IS NOT NULL AND c.revisao_status <> "" THEN c.revisao_status
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
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NÃO VEIO DA SARA%"
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NAO VEIO DA SARA%"
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%PROCURAR%CRACH%"
                    ) THEN "Revisar lotação"
                    WHEN l.id IS NULL THEN "Sem lotação ativa"
                    ELSE "Acompanhar"
                END AS alerta,
                CASE
                    WHEN c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0 THEN 1
                    WHEN c.revisao_status = "Revisar Cadastro" THEN 2
                    WHEN c.revisao_status IS NOT NULL AND c.revisao_status <> "" THEN 3
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
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NÃO VEIO DA SARA%"
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NAO VEIO DA SARA%"
                        OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%PROCURAR%CRACH%"
                    ) THEN 4
                    WHEN l.id IS NULL THEN 5
                    ELSE 99
                END AS ordem_alerta
            FROM pe_candidatos c
            LEFT JOIN pe_lotacoes l
                ON l.candidato_id = c.id
               AND l.status = "Ativa"
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
        ) a
        WHERE alerta <> "Acompanhar"
        ORDER BY ordem_alerta ASC, nome ASC
        LIMIT ' . $limit;

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_dashboard_overview(PDO $pdo): array
{
    $stats = pe_dashboard_stats($pdo);
    $lotacao = pe_dashboard_lotacao_distribution($pdo);
    $grant = pe_dashboard_grant_counts($pdo);
    $freq = pe_dashboard_frequency_ranges($pdo);

    $lotado = 0;
    $naoLotado = 0;
    $revisarLotacao = 0;
    $prontoImportar = 0;
    foreach ($lotacao as $item) {
        switch ($item['label']) {
            case 'Lotado': $lotado = (int) $item['value']; break;
            case 'Não lotado': $naoLotado = (int) $item['value']; break;
            case 'Revisar lotação': $revisarLotacao = (int) $item['value']; break;
            case 'Pronto para importar': $prontoImportar = (int) $item['value']; break;
        }
    }

    $sql = 'SELECT
        (SELECT COUNT(*) FROM pe_parceiros WHERE status = "Ativa") AS parceiros_ativos,
        (SELECT COUNT(*) FROM pe_vagas WHERE status = "Aberta") AS vagas_abertas,
        (SELECT COUNT(*) FROM pe_encaminhamentos WHERE status IN ("Pendente", "Encaminhado", "Entrevista marcada")) AS encaminhamentos_abertos';
    $extra = (array) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    $bolsasPagas = 0;
    $bolsasValor = 0.0;
    if (!empty($grant['competencia'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(valor), 0) AS valor FROM pe_bolsas WHERE competencia = :competencia AND status = "Paga"');
        $stmt->execute(['competencia' => $grant['competencia']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $bolsasPagas = (int) ($row['total'] ?? 0);
        $bolsasValor = (float) ($row['valor'] ?? 0);
    }

    return [
        'total' => (int) ($stats['total'] ?? 0),
        'contados' => (int) ($stats['total'] ?? 0),
        'contemplados' => (int) ($stats['contemplados'] ?? 0),
        'revisao_pendente' => (int) ($stats['revisao_pendente'] ?? 0),
        'cpf_duplicado' => (int) ($stats['cpf_duplicado'] ?? 0),
        'visitas' => (int) ($stats['visitas'] ?? 0),
        'deferidos' => (int) ($stats['deferidos'] ?? 0),
        'indeferidos' => (int) ($stats['indeferidos'] ?? 0),
        'importados' => (int) ($stats['importados'] ?? 0),
        'lotados' => $lotado,
        'nao_lotados' => $naoLotado,
        'revisar_lotacao' => $revisarLotacao,
        'pronto_importar' => $prontoImportar,
        'parceiros_ativos' => (int) ($extra['parceiros_ativos'] ?? 0),
        'vagas_abertas' => (int) ($extra['vagas_abertas'] ?? 0),
        'encaminhamentos_abertos' => (int) ($extra['encaminhamentos_abertos'] ?? 0),
        'bolsas_pagas' => $bolsasPagas,
        'bolsas_valor' => $bolsasValor,
        'frequencia_media' => (float) ($freq['media_geral'] ?? 0),
        'frequencia_competencia' => $freq['competencia'],
        'bolsas_competencia' => $grant['competencia'] ?? null,
    ];
}
