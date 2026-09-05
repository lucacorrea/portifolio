<?php

declare(strict_types=1);

/**
 * Pagina os confirmados da importação que ainda não possuem inscrição oficial,
 * aplicando os mesmos filtros de qualidade cadastral usados na lista principal.
 *
 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
 */
function cm_beneficiary_review_unlinked_paginate(
    PDO $pdo,
    string $programStatus,
    string $search,
    string $review,
    int $page = 1,
    int $perPage = 50
): array {
    if (!cm_import_schema_ready($pdo)) {
        return ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>$perPage, 'total_pages'=>1];
    }

    $situations = match ($programStatus) {
        'ativa' => ['Beneficiario'],
        'lista_espera' => ['ListaEspera'],
        '' => ['Beneficiario', 'ListaEspera'],
        default => [],
    };
    if ($situations === []) {
        return ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>$perPage, 'total_pages'=>1];
    }

    $review = cm_beneficiary_review_key($review);
    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $params = [];
    $statusPlaceholders = [];
    foreach ($situations as $index => $status) {
        $key = 'status_' . $index;
        $statusPlaceholders[] = ':' . $key;
        $params[$key] = $status;
    }

    $phoneDigits = "REGEXP_REPLACE(COALESCE(item.telefone_informado, ''), '[^0-9]', '')";
    $duplicate = "(item.motivos LIKE '%CPF duplicado na planilha%')";
    $cpf = "(item.cpf_validado IS NULL OR TRIM(item.cpf_validado) = '' OR {$duplicate})";
    $phone = "({$phoneDigits} = '' OR CHAR_LENGTH({$phoneDigits}) NOT IN (10, 11))";
    $pole = "(item.motivos LIKE '%Polo/local não localizado:%' OR COALESCE(TRIM(item.polo_informado), '') = '')";
    $issueCount = "((CASE WHEN {$cpf} THEN 1 ELSE 0 END) + (CASE WHEN {$phone} THEN 1 ELSE 0 END) + (CASE WHEN {$pole} THEN 1 ELSE 0 END))";

    $where = [
        'item.inscricao_id IS NULL',
        'item.situacao_programa IN (' . implode(',', $statusPlaceholders) . ')',
    ];

    $search = trim($search);
    if ($search !== '') {
        $where[] = '(item.nome LIKE :search_name OR item.cpf_informado LIKE :search_cpf_informado OR item.cpf_validado LIKE :search_cpf_validado OR item.telefone_informado LIKE :search_phone OR item.polo_informado LIKE :search_pole OR item.classificacao LIKE :search_classification OR item.motivos LIKE :search_motives)';
        foreach (['search_name','search_cpf_informado','search_cpf_validado','search_phone','search_pole','search_classification','search_motives'] as $key) {
            $params[$key] = '%' . $search . '%';
        }
    }

    if ($review !== '') {
        $where[] = match ($review) {
            'pendente' => $issueCount . ' >= 1',
            'cadastro' => $issueCount . ' >= 2',
            'cpf' => $cpf,
            'cpf_duplicado' => $duplicate,
            'telefone' => $phone,
            'polo' => $pole,
            'regular' => $issueCount . ' = 0',
            default => '1 = 1',
        };
    }

    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM comida_mesa_importacao_itens item WHERE {$whereSql}");
    cm_beneficiary_review_bind($count, $params);
    $count->execute();
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT item.*, imp.arquivo_nome, imp.criado_em AS importado_em, u.nome AS decisor_nome
        FROM comida_mesa_importacao_itens item
        INNER JOIN comida_mesa_importacoes imp ON imp.id = item.importacao_id
        LEFT JOIN usuarios u ON u.id = item.decidido_por
        WHERE {$whereSql}
        ORDER BY item.nome, item.id
        LIMIT :limit OFFSET :offset");
    cm_beneficiary_review_bind($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) {
        $item = cm_import_decode_item($item);
    }
    unset($item);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}
