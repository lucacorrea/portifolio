<?php

declare(strict_types=1);

use App\Core\Validator;
use App\DTO\ComidaMesaFilter;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/import.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/beneficiary-review.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/beneficiary-review-import.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/pdf.php';

cm_require('comida_mesa.visualizar');

$app = cm_app();
$service = $app['service'];
$repository = $app['repository'];

$requested = $service->buildFilter($_GET);
$competence = $service->resolveCompetence($requested->competenceId);
$filter = new ComidaMesaFilter(
    $requested->search,
    $competence === null ? null : (int) $competence['id'],
    $requested->programStatus,
    $requested->deliveryStatus,
    $requested->zone,
    $requested->district,
    $requested->community,
    $requested->poleId,
    1,
);

$review = cm_beneficiary_review_key($_GET['review'] ?? '');
$origin = cm_beneficiary_origin_key($_GET['origin'] ?? '');
$priority = cm_beneficiary_priority_key($_GET['priority'] ?? '');

function cm_pdf_beneficiary_rows(
    PDO $pdo,
    ComidaMesaFilter $filter,
    string $review,
    string $origin,
    string $priority,
    int $limit = 20000
): array {
    $limit = max(1, min(50000, $limit));
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
            $where[] = $column . ' = :' . $property;
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

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql} WHERE {$whereSql}");
    cm_beneficiary_review_bind($countStmt, $params);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT
        i.id AS inscricao_id,
        f.id AS familia_id,
        f.codigo AS familia_codigo,
        p.nome AS responsavel_nome,
        p.cpf,
        p.nis,
        p.telefone,
        f.zona,
        f.bairro,
        f.comunidade,
        i.polo_id,
        polo.nome AS polo_nome,
        polo.ativo AS polo_ativo,
        i.status AS inscricao_status,
        i.prioridade,
        i.data_inscricao,
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
        LIMIT {$limit}");

    cm_beneficiary_review_bind($stmt, $params);
    $stmt->execute();

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
        'truncated' => $total > $limit,
    ];
}

function cm_pdf_unlinked_rows(PDO $pdo, string $programStatus, string $search, string $review, int $limit = 20000): array
{
    if (!cm_import_schema_ready($pdo)) {
        return ['items' => [], 'total' => 0, 'truncated' => false];
    }

    $situations = match ($programStatus) {
        'ativa' => ['Beneficiario'],
        'lista_espera' => ['ListaEspera'],
        '' => ['Beneficiario', 'ListaEspera'],
        default => [],
    };

    if ($situations === []) {
        return ['items' => [], 'total' => 0, 'truncated' => false];
    }

    $review = cm_beneficiary_review_key($review);
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
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comida_mesa_importacao_itens item WHERE {$whereSql}");
    cm_beneficiary_review_bind($countStmt, $params);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $limit = max(1, min(50000, $limit));
    $stmt = $pdo->prepare("SELECT item.*, imp.arquivo_nome, imp.criado_em AS importado_em, u.nome AS decisor_nome
        FROM comida_mesa_importacao_itens item
        INNER JOIN comida_mesa_importacoes imp ON imp.id = item.importacao_id
        LEFT JOIN usuarios u ON u.id = item.decidido_por
        WHERE {$whereSql}
        ORDER BY item.nome, item.id
        LIMIT {$limit}");
    cm_beneficiary_review_bind($stmt, $params);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) {
        $item = cm_import_decode_item($item);
    }
    unset($item);

    return [
        'items' => $items,
        'total' => $total,
        'truncated' => $total > $limit,
    ];
}

try {
    $official = cm_pdf_beneficiary_rows(cm_db(), $filter, $review, $origin, $priority);

    $canIncludeUnlinked = $filter->zone === null
        && $filter->district === null
        && $filter->community === null
        && $filter->poleId === null
        && $filter->deliveryStatus === null
        && $priority === ''
        && $origin !== 'manual'
        && in_array($filter->programStatus, [null, 'ativa', 'lista_espera'], true);

    $unlinked = $canIncludeUnlinked
        ? cm_pdf_unlinked_rows(
            cm_db(),
            (string) ($filter->programStatus ?? ''),
            (string) ($filter->search ?? ''),
            $review
        )
        : ['items' => [], 'total' => 0, 'truncated' => false];

    $reviewLabels = [
        'pendente' => 'Todas pendentes',
        'cadastro' => 'Revisar cadastro',
        'cpf' => 'Revisar CPF',
        'cpf_duplicado' => 'CPF duplicado',
        'telefone' => 'Revisar telefone',
        'polo' => 'Revisar polo',
        'regular' => 'Sem pendencia',
    ];
    $deliveryLabels = [
        'recebida' => 'Recebida',
        'aguardando' => 'Aguardando retirada',
        'bloqueada' => 'Bloqueada',
        'indisponivel' => 'Nao disponivel',
    ];
    $priorityLabels = ['alta' => 'Alta', 'normal' => 'Normal', 'baixa' => 'Baixa'];

    $poles = $repository->listActivePoles();
    $poleName = null;
    if ($filter->poleId !== null) {
        foreach ($poles as $pole) {
            if ((int) $pole['id'] === $filter->poleId) {
                $poleName = (string) $pole['nome'];
                break;
            }
        }
        $poleName ??= 'Polo #' . $filter->poleId;
    }

    $activeFilters = [];
    if ($filter->search !== null) $activeFilters[] = 'Pesquisa: ' . $filter->search;
    if ($review !== '') $activeFilters[] = 'Revisao: ' . ($reviewLabels[$review] ?? $review);
    if ($filter->programStatus !== null) $activeFilters[] = 'Situacao: ' . $service->programStatusLabel($filter->programStatus);
    if ($origin !== '') $activeFilters[] = 'Origem: ' . ($origin === 'importado' ? 'Importacao' : 'Manual');
    if ($filter->deliveryStatus !== null) $activeFilters[] = 'Entrega: ' . ($deliveryLabels[$filter->deliveryStatus] ?? $filter->deliveryStatus);
    if ($priority !== '') $activeFilters[] = 'Prioridade: ' . ($priorityLabels[$priority] ?? ucfirst($priority));
    if ($filter->zone !== null) $activeFilters[] = 'Zona: ' . ucfirst($filter->zone);
    if ($filter->district !== null) $activeFilters[] = 'Bairro: ' . $filter->district;
    if ($filter->community !== null) $activeFilters[] = 'Comunidade: ' . $filter->community;
    if ($poleName !== null) $activeFilters[] = 'Polo: ' . $poleName;

    $competenceLabel = $competence
        ? cm_month_label((int) $competence['mes'], (int) $competence['ano'])
        : 'Sem competencia';

    $totalRows = count($official['items']) + count($unlinked['items']);
    $databaseTotal = (int) $official['total'] + (int) $unlinked['total'];

    $metaLines = [
        'Competencia: ' . $competenceLabel . ' | Registros encontrados: ' . number_format($databaseTotal, 0, ',', '.')
            . ' | Gerado em: ' . date('d/m/Y H:i'),
        'Filtros: ' . ($activeFilters !== [] ? implode(' | ', $activeFilters) : 'Lista geral de beneficiarios'),
    ];

    if ($official['truncated'] || $unlinked['truncated']) {
        $metaLines[] = 'ATENCAO: a exportacao atingiu o limite tecnico de registros. Refine os filtros para obter a lista completa.';
    }

    $columns = [
        ['label' => '#', 'width' => 25.0, 'align' => 'R'],
        ['label' => 'Codigo', 'width' => 58.0],
        ['label' => 'Responsavel familiar', 'width' => 145.0],
        ['label' => 'CPF', 'width' => 76.0],
        ['label' => 'Localidade', 'width' => 112.0],
        ['label' => 'Polo', 'width' => 88.0],
        ['label' => 'Revisao', 'width' => 96.0],
        ['label' => 'Situacao', 'width' => 88.0],
        ['label' => 'Entrega', 'width' => 85.0],
    ];

    $pdf = new ComidaMesaSimplePdf(
        'COARI COMIDA NA MESA - LISTA DE BENEFICIARIOS',
        $metaLines,
        $columns
    );

    $sequence = 1;
    foreach ($official['items'] as $row) {
        $meta = cm_beneficiary_review_meta($row);
        $delivery = $service->deliveryStatusForRow($row, $competence);

        $pdf->addRow([
            (string) $sequence++,
            (string) ($row['familia_codigo'] ?? ''),
            (string) ($row['responsavel_nome'] ?? ''),
            cm_format_cpf($row['cpf'] ?? ''),
            cm_location($row),
            (string) (($row['polo_nome'] ?? '') ?: 'Sem polo'),
            (string) $meta['label'],
            $service->programStatusLabel((string) ($row['inscricao_status'] ?? '')),
            (string) ($delivery['label'] ?? 'Nao disponivel'),
        ]);
    }

    foreach ($unlinked['items'] as $row) {
        $meta = cm_beneficiary_review_import_meta($row);
        $document = trim((string) (($row['cpf_validado'] ?? '') ?: ($row['cpf_informado'] ?? '')));
        $digits = preg_replace('/\D+/', '', $document) ?: '';
        $documentDisplay = strlen($digits) === 11 ? cm_format_cpf($digits) : ($document !== '' ? $document : 'CPF pendente');
        $locationParts = array_values(array_filter([
            $row['bairro_origem'] ?? '',
            $row['endereco_origem'] ?? '',
            $row['local_origem'] ?? '',
        ], static fn ($value): bool => trim((string) $value) !== ''));
        $isBeneficiary = (string) ($row['situacao_programa'] ?? '') === 'Beneficiario';

        $pdf->addRow([
            (string) $sequence++,
            'IMP-' . (int) ($row['id'] ?? 0),
            (string) ($row['nome'] ?? ''),
            $documentDisplay,
            $locationParts ? implode(' - ', $locationParts) : 'Nao informado',
            (string) (($row['polo_informado'] ?? '') ?: 'Sem polo'),
            (string) $meta['label'],
            $isBeneficiary ? 'Beneficiaria ativa' : 'Lista de espera',
            $isBeneficiary ? 'Beneficio mantido' : 'Nao disponivel',
        ]);
    }

    if ($totalRows === 0) {
        $pdf->addEmptyMessage('Nenhum beneficiario foi encontrado com os filtros selecionados.');
    }

    $filename = 'comida-na-mesa-beneficiarios-' . date('Ymd-His') . '.pdf';
    $pdf->output($filename, true);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Nao foi possivel gerar a lista em PDF. Tente novamente ou contate o suporte.');
}
