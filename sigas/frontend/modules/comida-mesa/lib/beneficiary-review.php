<?php

declare(strict_types=1);

use App\Core\Validator;
use App\DTO\ComidaMesaFilter;
use App\DTO\PaginatedResult;

/**
 * Camada de qualidade cadastral da lista principal do Comida na Mesa.
 *
 * A revisão é independente da situação do benefício: um beneficiário ativo pode
 * possuir pendências cadastrais sem ter o benefício suspenso.
 */
function cm_beneficiary_review_key(mixed $value): string
{
    $value = trim((string) $value);
    return in_array($value, ['pendente', 'cadastro', 'cpf', 'cpf_duplicado', 'telefone', 'polo', 'regular'], true)
        ? $value
        : '';
}

function cm_beneficiary_origin_key(mixed $value): string
{
    $value = trim((string) $value);
    return in_array($value, ['importado', 'manual'], true) ? $value : '';
}

function cm_beneficiary_priority_key(mixed $value): string
{
    $value = trim((string) $value);
    return in_array($value, ['alta', 'normal', 'baixa'], true) ? $value : '';
}

/** @return array<string,string> */
function cm_beneficiary_review_expressions(): array
{
    $cpfDuplicate = '(COALESCE(review_info.cpf_duplicado, 0) = 1)';
    $cpfIssue = "((p.cpf IS NULL OR TRIM(p.cpf) = '') OR {$cpfDuplicate})";
    $phoneDigits = "REGEXP_REPLACE(COALESCE(p.telefone, ''), '[^0-9]', '')";
    $phoneIssue = "({$phoneDigits} = '' OR CHAR_LENGTH({$phoneDigits}) NOT IN (10, 11))";
    $poleIssue = "(i.status = 'ativa' AND (i.polo_id IS NULL OR polo.id IS NULL OR COALESCE(polo.ativo, 0) <> 1))";
    $reviewCount = "((CASE WHEN {$cpfIssue} THEN 1 ELSE 0 END)"
        . " + (CASE WHEN {$phoneIssue} THEN 1 ELSE 0 END)"
        . " + (CASE WHEN {$poleIssue} THEN 1 ELSE 0 END))";

    return [
        'cpf_duplicado' => $cpfDuplicate,
        'cpf' => $cpfIssue,
        'telefone' => $phoneIssue,
        'polo' => $poleIssue,
        'total' => $reviewCount,
        'importado' => '(COALESCE(review_info.importado, 0) = 1)',
    ];
}

function cm_beneficiary_review_join(): string
{
    return "LEFT JOIN (
        SELECT
            ri.inscricao_id,
            1 AS importado,
            MAX(CASE WHEN ri.motivos LIKE '%CPF duplicado na planilha%' THEN 1 ELSE 0 END) AS cpf_duplicado,
            GROUP_CONCAT(DISTINCT NULLIF(ri.motivos, '') ORDER BY ri.id SEPARATOR ' | ') AS motivos_importacao
        FROM comida_mesa_importacao_itens ri
        WHERE ri.inscricao_id IS NOT NULL
        GROUP BY ri.inscricao_id
    ) review_info ON review_info.inscricao_id = i.id";
}

function cm_beneficiary_review_condition(string $review): string
{
    $e = cm_beneficiary_review_expressions();
    return match ($review) {
        'pendente' => $e['total'] . ' >= 1',
        'cadastro' => $e['total'] . ' >= 2',
        'cpf' => $e['cpf'],
        'cpf_duplicado' => $e['cpf_duplicado'],
        'telefone' => $e['telefone'],
        'polo' => $e['polo'],
        'regular' => $e['total'] . ' = 0',
        default => '1 = 1',
    };
}

/** @return array<string,int> */
function cm_beneficiary_review_stats(PDO $pdo): array
{
    $e = cm_beneficiary_review_expressions();
    $join = cm_beneficiary_review_join();

    $sql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN {$e['total']} >= 1 THEN 1 ELSE 0 END) AS revisao_pendente,
        SUM(CASE WHEN {$e['total']} >= 2 THEN 1 ELSE 0 END) AS revisar_cadastro,
        SUM(CASE WHEN {$e['cpf']} THEN 1 ELSE 0 END) AS revisar_cpf,
        SUM(CASE WHEN {$e['cpf_duplicado']} THEN 1 ELSE 0 END) AS cpf_duplicado,
        SUM(CASE WHEN {$e['telefone']} THEN 1 ELSE 0 END) AS revisar_telefone,
        SUM(CASE WHEN {$e['polo']} THEN 1 ELSE 0 END) AS revisar_polo,
        SUM(CASE WHEN {$e['total']} = 0 THEN 1 ELSE 0 END) AS regular,
        SUM(CASE WHEN {$e['importado']} THEN 1 ELSE 0 END) AS importados
        FROM comida_mesa_inscricoes i
        INNER JOIN familias f ON f.id = i.familia_id
        INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
        LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
        {$join}";

    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats = [
        'total' => (int) ($row['total'] ?? 0),
        'revisao_pendente' => (int) ($row['revisao_pendente'] ?? 0),
        'revisar_cadastro' => (int) ($row['revisar_cadastro'] ?? 0),
        'revisar_cpf' => (int) ($row['revisar_cpf'] ?? 0),
        'cpf_duplicado' => (int) ($row['cpf_duplicado'] ?? 0),
        'revisar_telefone' => (int) ($row['revisar_telefone'] ?? 0),
        'revisar_polo' => (int) ($row['revisar_polo'] ?? 0),
        'regular' => (int) ($row['regular'] ?? 0),
        'importados' => (int) ($row['importados'] ?? 0),
    ];

    // Enquanto existirem confirmados ainda sem inscrição oficial, eles também
    // participam dos indicadores de qualidade. Após a regularização, deixam esta
    // consulta e passam automaticamente para os indicadores oficiais acima.
    $uPhone = "REGEXP_REPLACE(COALESCE(item.telefone_informado, ''), '[^0-9]', '')";
    $uDuplicate = "(item.motivos LIKE '%CPF duplicado na planilha%')";
    $uCpf = "(item.cpf_validado IS NULL OR TRIM(item.cpf_validado) = '' OR {$uDuplicate})";
    $uPhoneIssue = "({$uPhone} = '' OR CHAR_LENGTH({$uPhone}) NOT IN (10, 11))";
    $uPole = "(item.motivos LIKE '%Polo/local não localizado:%' OR COALESCE(TRIM(item.polo_informado), '') = '')";
    $uCount = "((CASE WHEN {$uCpf} THEN 1 ELSE 0 END) + (CASE WHEN {$uPhoneIssue} THEN 1 ELSE 0 END) + (CASE WHEN {$uPole} THEN 1 ELSE 0 END))";

    $unlinkedSql = "SELECT
        COUNT(*) total,
        SUM(CASE WHEN {$uCount} >= 1 THEN 1 ELSE 0 END) revisao_pendente,
        SUM(CASE WHEN {$uCount} >= 2 THEN 1 ELSE 0 END) revisar_cadastro,
        SUM(CASE WHEN {$uCpf} THEN 1 ELSE 0 END) revisar_cpf,
        SUM(CASE WHEN {$uDuplicate} THEN 1 ELSE 0 END) cpf_duplicado,
        SUM(CASE WHEN {$uPhoneIssue} THEN 1 ELSE 0 END) revisar_telefone,
        SUM(CASE WHEN {$uPole} THEN 1 ELSE 0 END) revisar_polo,
        SUM(CASE WHEN {$uCount} = 0 THEN 1 ELSE 0 END) regular
        FROM comida_mesa_importacao_itens item
        WHERE item.inscricao_id IS NULL
          AND item.situacao_programa = 'Beneficiario'";
    $unlinked = $pdo->query($unlinkedSql)->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach (['total','revisao_pendente','revisar_cadastro','revisar_cpf','cpf_duplicado','revisar_telefone','revisar_polo','regular'] as $key) {
        $stats[$key] += (int) ($unlinked[$key] ?? 0);
    }
    $stats['importados'] += (int) ($unlinked['total'] ?? 0);

    return $stats;
}

/**
 * Paginação oficial com os mesmos filtros da lista original mais filtros de revisão,
 * origem e prioridade. Mantém a situação do benefício independente da revisão.
 */
function cm_beneficiary_review_paginate(
    PDO $pdo,
    ComidaMesaFilter $filter,
    string $review = '',
    string $origin = '',
    string $priority = ''
): PaginatedResult {
    $review = cm_beneficiary_review_key($review);
    $origin = cm_beneficiary_origin_key($origin);
    $priority = cm_beneficiary_priority_key($priority);
    $reviewJoin = cm_beneficiary_review_join();
    $e = cm_beneficiary_review_expressions();

    $deliveryJoin = $filter->competenceId === null
        ? 'LEFT JOIN comida_mesa_entregas entrega ON 1 = 0'
        : 'LEFT JOIN comida_mesa_entregas entrega ON entrega.inscricao_id = i.id AND entrega.competencia_id = :entrega_competencia_id';

    $where = ['1 = 1'];
    $params = [];
    if ($filter->competenceId !== null) {
        $params['entrega_competencia_id'] = $filter->competenceId;
    }

    if ($filter->search !== null) {
        $where[] = '(p.nome LIKE :search_name OR p.nis LIKE :search_nis OR f.codigo LIKE :search_code'
            . (Validator::onlyDigits($filter->search) === '' ? ')' : ' OR p.cpf LIKE :search_cpf)');
        $params['search_name'] = '%' . $filter->search . '%';
        $params['search_nis'] = '%' . $filter->search . '%';
        $params['search_code'] = '%' . $filter->search . '%';
        if (Validator::onlyDigits($filter->search) !== '') {
            $params['search_cpf'] = '%' . Validator::onlyDigits($filter->search) . '%';
        }
    }

    if ($filter->programStatus !== null) {
        $where[] = 'i.status = :program_status';
        $params['program_status'] = $filter->programStatus;
    }

    foreach (['zone' => 'f.zona', 'district' => 'f.bairro', 'community' => 'f.comunidade'] as $property => $column) {
        if ($filter->{$property} !== null) {
            $where[] = "{$column} = :{$property}";
            $params[$property] = $filter->{$property};
        }
    }

    if ($filter->poleId !== null) {
        $where[] = 'i.polo_id = :pole_id';
        $params['pole_id'] = $filter->poleId;
    }

    if ($filter->deliveryStatus === 'recebida') {
        $where[] = $filter->competenceId === null ? '1 = 0' : "entrega.id IS NOT NULL AND entrega.status = 'entregue'";
    } elseif ($filter->deliveryStatus === 'aguardando') {
        if ($filter->competenceId === null) {
            $where[] = '1 = 0';
        } else {
            $where[] = "i.status = 'ativa' AND NOT EXISTS (
                SELECT 1 FROM comida_mesa_entregas e2
                WHERE e2.inscricao_id = i.id
                  AND e2.competencia_id = :aguardando_competencia_id
                  AND e2.status = 'entregue'
            )";
            $params['aguardando_competencia_id'] = $filter->competenceId;
        }
    } elseif ($filter->deliveryStatus === 'bloqueada') {
        $where[] = "i.status IN ('suspensa', 'bloqueada')";
    } elseif ($filter->deliveryStatus === 'indisponivel') {
        $where[] = "i.status IN ('em_analise', 'lista_espera', 'encerrada')";
    }

    if ($review !== '') {
        $where[] = '(' . cm_beneficiary_review_condition($review) . ')';
    }
    if ($origin === 'importado') {
        $where[] = $e['importado'];
    } elseif ($origin === 'manual') {
        $where[] = 'NOT ' . $e['importado'];
    }
    if ($priority !== '') {
        $where[] = 'i.prioridade = :review_priority';
        $params['review_priority'] = $priority;
    }

    $whereSql = implode(' AND ', $where);
    $fromSql = "FROM comida_mesa_inscricoes i
        INNER JOIN familias f ON f.id = i.familia_id
        INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
        LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
        {$reviewJoin}
        {$deliveryJoin}
        LEFT JOIN usuarios entrega_operador ON entrega_operador.id = entrega.entregue_por";

    $count = $pdo->prepare("SELECT COUNT(*) {$fromSql} WHERE {$whereSql}");
    cm_beneficiary_review_bind($count, $params);
    $count->execute();
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $filter->perPage));
    $page = min(max(1, $filter->page), $totalPages);
    $offset = ($page - 1) * $filter->perPage;

    $stmt = $pdo->prepare("SELECT
        i.id AS inscricao_id,
        f.id AS familia_id,
        f.codigo AS familia_codigo,
        p.id AS pessoa_id,
        p.nome AS responsavel_nome,
        p.cpf,
        p.nis,
        p.telefone,
        p.data_nascimento,
        f.zona,
        f.bairro,
        f.comunidade,
        i.polo_id,
        polo.nome AS polo_nome,
        polo.ativo AS polo_ativo,
        i.status AS inscricao_status,
        i.prioridade,
        i.data_inscricao,
        i.observacao AS inscricao_observacao,
        i.atualizado_em,
        entrega.id AS entrega_id,
        entrega.status AS entrega_status,
        entrega.entregue_em AS entrega_data,
        entrega_operador.nome AS entrega_operador_nome,
        CASE WHEN {$e['cpf']} THEN 1 ELSE 0 END AS revisao_cpf,
        CASE WHEN {$e['cpf_duplicado']} THEN 1 ELSE 0 END AS revisao_cpf_duplicado,
        CASE WHEN {$e['telefone']} THEN 1 ELSE 0 END AS revisao_telefone,
        CASE WHEN {$e['polo']} THEN 1 ELSE 0 END AS revisao_polo,
        {$e['total']} AS revisao_total,
        CASE WHEN {$e['importado']} THEN 1 ELSE 0 END AS origem_importacao,
        review_info.motivos_importacao
        {$fromSql}
        WHERE {$whereSql}
        ORDER BY
            CASE WHEN {$e['total']} >= 2 THEN 1 WHEN {$e['total']} = 1 THEN 2 ELSE 3 END,
            CASE i.prioridade WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 WHEN 'baixa' THEN 3 ELSE 4 END,
            p.nome,
            i.id
        LIMIT :limit OFFSET :offset");
    cm_beneficiary_review_bind($stmt, $params);
    $stmt->bindValue(':limit', $filter->perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return new PaginatedResult($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $total, $page, $filter->perPage);
}

/** @return array{label:string,tone:string,reasons:list<string>,count:int,cpf:bool,cpf_duplicate:bool,phone:bool,pole:bool,imported:bool} */
function cm_beneficiary_review_meta(array $row): array
{
    $cpf = (int) ($row['revisao_cpf'] ?? 0) === 1;
    $cpfDuplicate = (int) ($row['revisao_cpf_duplicado'] ?? 0) === 1;
    $phone = (int) ($row['revisao_telefone'] ?? 0) === 1;
    $pole = (int) ($row['revisao_polo'] ?? 0) === 1;
    $count = (int) ($row['revisao_total'] ?? (($cpf ? 1 : 0) + ($phone ? 1 : 0) + ($pole ? 1 : 0)));
    $reasons = [];

    if ($cpfDuplicate) {
        $reasons[] = 'CPF duplicado na importação';
    } elseif ($cpf) {
        $reasons[] = 'CPF pendente';
    }
    if ($phone) $reasons[] = 'Telefone não informado ou fora do padrão';
    if ($pole) $reasons[] = 'Polo não definido ou inativo';

    if ($count === 0) {
        $label = 'Sem pendência';
        $tone = 'success';
    } elseif ($count >= 2) {
        $label = 'Revisar Cadastro';
        $tone = 'warning';
    } elseif ($cpfDuplicate) {
        $label = 'CPF duplicado';
        $tone = 'danger';
    } elseif ($cpf) {
        $label = 'Revisar CPF';
        $tone = 'warning';
    } elseif ($phone) {
        $label = 'Revisar Telefone';
        $tone = 'warning';
    } else {
        $label = 'Revisar Polo';
        $tone = 'warning';
    }

    return [
        'label' => $label,
        'tone' => $tone,
        'reasons' => $reasons,
        'count' => $count,
        'cpf' => $cpf,
        'cpf_duplicate' => $cpfDuplicate,
        'phone' => $phone,
        'pole' => $pole,
        'imported' => (int) ($row['origem_importacao'] ?? 0) === 1,
    ];
}

/** @return array{label:string,tone:string,reasons:list<string>,count:int,cpf:bool,cpf_duplicate:bool,phone:bool,pole:bool,imported:bool} */
function cm_beneficiary_review_import_meta(array $item): array
{
    $motives = mb_strtolower((string) ($item['motivos'] ?? ''), 'UTF-8');
    $duplicate = str_contains($motives, 'cpf duplicado na planilha');
    $cpfDigits = preg_replace('/\D+/', '', (string) ($item['cpf_validado'] ?? '')) ?: '';
    $cpf = $duplicate || strlen($cpfDigits) !== 11;
    $phoneDigits = preg_replace('/\D+/', '', (string) ($item['telefone_informado'] ?? '')) ?: '';
    $phone = !in_array(strlen($phoneDigits), [10, 11], true);
    $pole = str_contains($motives, 'polo/local não localizado') || trim((string) ($item['polo_informado'] ?? '')) === '';
    $count = ($cpf ? 1 : 0) + ($phone ? 1 : 0) + ($pole ? 1 : 0);
    $reasons = [];
    if ($duplicate) $reasons[] = 'CPF duplicado na importação';
    elseif ($cpf) $reasons[] = 'CPF pendente';
    if ($phone) $reasons[] = 'Telefone não informado ou fora do padrão';
    if ($pole) $reasons[] = 'Polo não localizado';

    if ($count >= 2) $label = 'Revisar Cadastro';
    elseif ($duplicate) $label = 'CPF duplicado';
    elseif ($cpf) $label = 'Revisar CPF';
    elseif ($phone) $label = 'Revisar Telefone';
    elseif ($pole) $label = 'Revisar Polo';
    else $label = 'Sem pendência';

    return [
        'label' => $label,
        'tone' => $count === 0 ? 'success' : ($duplicate ? 'danger' : 'warning'),
        'reasons' => $reasons,
        'count' => $count,
        'cpf' => $cpf,
        'cpf_duplicate' => $duplicate,
        'phone' => $phone,
        'pole' => $pole,
        'imported' => true,
    ];
}

function cm_beneficiary_review_matches(array $meta, string $review): bool
{
    return match (cm_beneficiary_review_key($review)) {
        'pendente' => $meta['count'] >= 1,
        'cadastro' => $meta['count'] >= 2,
        'cpf' => $meta['cpf'],
        'cpf_duplicado' => $meta['cpf_duplicate'],
        'telefone' => $meta['phone'],
        'polo' => $meta['pole'],
        'regular' => $meta['count'] === 0,
        default => true,
    };
}

/** @param array<string,mixed> $params */
function cm_beneficiary_review_bind(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}
