<?php

declare(strict_types=1);

use App\Core\Validator;
use App\DTO\ComidaMesaCadastroData;

require_once __DIR__ . '/spreadsheet.php';

function cm_import_schema_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT id, pendentes_confirmacao, beneficiarios_confirmados, lista_espera_confirmados FROM comida_mesa_importacoes LIMIT 1');
        $pdo->query('SELECT id, situacao_programa, decidido_em, decidido_por, efetivacao_status, efetivacao_motivo FROM comida_mesa_importacao_itens LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

/** @return list<array<string,mixed>> */
function cm_import_history(PDO $pdo, int $limit = 15): array
{
    if (!cm_import_schema_ready($pdo)) {
        return [];
    }
    $limit = max(5, min(50, $limit));
    $sql = 'SELECT imp.*, u.nome AS usuario_nome, polo.nome AS polo_padrao_nome
            FROM comida_mesa_importacoes imp
            LEFT JOIN usuarios u ON u.id = imp.criado_por
            LEFT JOIN comida_mesa_polos polo ON polo.id = imp.polo_padrao_id
            ORDER BY imp.id DESC LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}



/** @return array<string,mixed>|null */
function cm_import_history_item(PDO $pdo, int $importId): ?array
{
    if (!cm_import_schema_ready($pdo) || $importId < 1) return null;
    $stmt = $pdo->prepare('SELECT imp.*, u.nome AS usuario_nome, polo.nome AS polo_padrao_nome
        FROM comida_mesa_importacoes imp
        LEFT JOIN usuarios u ON u.id = imp.criado_por
        LEFT JOIN comida_mesa_polos polo ON polo.id = imp.polo_padrao_id
        WHERE imp.id = :id LIMIT 1');
    $stmt->execute(['id' => $importId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array<string,mixed> */
function cm_import_decode_item(array $item): array
{
    $source = json_decode((string) ($item['dados_json'] ?? ''), true);
    if (!is_array($source)) $source = [];

    $item['dados_origem'] = $source;
    $item['bairro_origem'] = trim((string) ($source['bairro'] ?? ''));
    $item['endereco_origem'] = trim((string) ($source['logradouro'] ?? $source['endereco'] ?? ''));
    $item['local_origem'] = trim((string) ($source['local_origem'] ?? $source['polo_informado'] ?? $item['polo_informado'] ?? ''));
    $item['data_nascimento_origem'] = trim((string) ($source['data_nascimento'] ?? ''));
    $item['conjuge_origem'] = trim((string) ($source['conjuge_origem'] ?? ''));
    return $item;
}

/**
 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int,counts:array<string,int>}
 */
function cm_import_review_items(
    PDO $pdo,
    int $importId,
    string $search = '',
    string $situation = '',
    int $page = 1,
    int $perPage = 50
): array {
    if (!cm_import_schema_ready($pdo) || $importId < 1) {
        return ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>$perPage, 'total_pages'=>1,
            'counts'=>['Pendente'=>0,'Beneficiario'=>0,'ListaEspera'=>0]];
    }

    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $allowed = ['Pendente', 'Beneficiario', 'ListaEspera'];
    $where = 'item.importacao_id = :importacao_id';
    $params = ['importacao_id' => $importId];

    if (in_array($situation, $allowed, true)) {
        $where .= ' AND item.situacao_programa = :situacao_programa';
        $params['situacao_programa'] = $situation;
    }

    $search = trim($search);
    if ($search !== '') {
        $where .= " AND (
            item.nome LIKE :search
            OR item.cpf_informado LIKE :search
            OR item.cpf_validado LIKE :search
            OR item.telefone_informado LIKE :search
            OR item.polo_informado LIKE :search
            OR item.classificacao LIKE :search
            OR item.motivos LIKE :search
            OR item.dados_json LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comida_mesa_importacao_itens item WHERE {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT
            item.*,
            imp.arquivo_nome,
            imp.criado_em AS importado_em,
            decisor.nome AS decisor_nome
        FROM comida_mesa_importacao_itens item
        INNER JOIN comida_mesa_importacoes imp ON imp.id = item.importacao_id
        LEFT JOIN usuarios decisor ON decisor.id = item.decidido_por
        WHERE {$where}
        ORDER BY item.linha, item.id
        LIMIT :limit OFFSET :offset");

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, $key === 'importacao_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) $item = cm_import_decode_item($item);
    unset($item);

    $countByStatus = ['Pendente'=>0,'Beneficiario'=>0,'ListaEspera'=>0];
    $group = $pdo->prepare('SELECT situacao_programa, COUNT(*) total
        FROM comida_mesa_importacao_itens
        WHERE importacao_id = :importacao_id
        GROUP BY situacao_programa');
    $group->execute(['importacao_id' => $importId]);
    foreach ($group->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = (string) ($row['situacao_programa'] ?? '');
        if (array_key_exists($key, $countByStatus)) $countByStatus[$key] = (int) $row['total'];
    }

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'counts' => $countByStatus,
    ];
}

/**
 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
 */
function cm_import_confirmed_unlinked(
    PDO $pdo,
    string $programStatus = '',
    string $search = '',
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

    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $placeholders = [];
    $params = [];
    foreach ($situations as $index => $value) {
        $key = 's' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $value;
    }

    $where = 'item.inscricao_id IS NULL AND item.situacao_programa IN (' . implode(',', $placeholders) . ')';
    $search = trim($search);
    if ($search !== '') {
        $where .= " AND (
            item.nome LIKE :search OR item.cpf_informado LIKE :search OR item.cpf_validado LIKE :search
            OR item.telefone_informado LIKE :search OR item.polo_informado LIKE :search
            OR item.classificacao LIKE :search OR item.motivos LIKE :search OR item.dados_json LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM comida_mesa_importacao_itens item WHERE {$where}");
    foreach ($params as $key => $value) $count->bindValue(':' . $key, $value, PDO::PARAM_STR);
    $count->execute();
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT item.*, imp.arquivo_nome, imp.criado_em importado_em, u.nome decisor_nome
        FROM comida_mesa_importacao_itens item
        INNER JOIN comida_mesa_importacoes imp ON imp.id = item.importacao_id
        LEFT JOIN usuarios u ON u.id = item.decidido_por
        WHERE {$where}
        ORDER BY item.nome, item.id
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as &$item) $item = cm_import_decode_item($item);
    unset($item);

    return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$totalPages];
}

/** @return array{beneficiarios:int,lista_espera:int} */
function cm_import_confirmed_unlinked_counts(PDO $pdo): array
{
    if (!cm_import_schema_ready($pdo)) return ['beneficiarios'=>0,'lista_espera'=>0];

    $stmt = $pdo->query("SELECT situacao_programa, COUNT(*) total
        FROM comida_mesa_importacao_itens
        WHERE inscricao_id IS NULL AND situacao_programa IN ('Beneficiario','ListaEspera')
        GROUP BY situacao_programa");
    $result = ['beneficiarios'=>0,'lista_espera'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if ($row['situacao_programa'] === 'Beneficiario') $result['beneficiarios'] = (int) $row['total'];
        if ($row['situacao_programa'] === 'ListaEspera') $result['lista_espera'] = (int) $row['total'];
    }
    return $result;
}

/** @return array<string,mixed>|null */
function cm_import_find_family_link(PDO $pdo, int $personId): ?array
{
    $stmt = $pdo->prepare("SELECT f.*,
        CASE WHEN f.responsavel_pessoa_id = :person_a THEN 'responsavel' ELSE 'integrante' END vinculo
        FROM familias f
        LEFT JOIN familia_membros fm ON fm.familia_id = f.id AND fm.pessoa_id = :person_b
        WHERE f.responsavel_pessoa_id = :person_c OR fm.pessoa_id = :person_d
        ORDER BY CASE WHEN f.responsavel_pessoa_id = :person_e THEN 1 ELSE 2 END, f.id
        LIMIT 1");
    $stmt->execute([
        'person_a'=>$personId,'person_b'=>$personId,'person_c'=>$personId,'person_d'=>$personId,'person_e'=>$personId
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array{pessoa_id:?int,familia_id:?int,inscricao_id:?int,status:string,motivo:?string} */
function cm_import_sync_official(PDO $pdo, array $item, string $decision, int $userId): array
{
    $targetStatus = $decision === 'Beneficiario' ? 'ativa' : 'lista_espera';
    $cpf = preg_replace('/\D+/', '', (string) ($item['cpf_validado'] ?? '')) ?: '';

    if ($cpf === '' || strlen($cpf) !== 11 || !Validator::cpf($cpf)) {
        return [
            'pessoa_id'=>null,'familia_id'=>null,'inscricao_id'=>null,
            'status'=>'CadastroPendente',
            'motivo'=>'Decisão registrada. CPF precisa ser regularizado para vincular ao cadastro central.',
        ];
    }

    $conflict = $pdo->prepare("SELECT id, nome, situacao_programa
        FROM comida_mesa_importacao_itens
        WHERE id <> :id
          AND cpf_validado = :cpf
          AND situacao_programa IN ('Beneficiario','ListaEspera')
          AND situacao_programa <> :decision
        ORDER BY id
        LIMIT 1");
    $conflict->execute(['id'=>(int)$item['id'],'cpf'=>$cpf,'decision'=>$decision]);
    $conflictRow = $conflict->fetch(PDO::FETCH_ASSOC);
    if (is_array($conflictRow)) {
        return [
            'pessoa_id'=>isset($item['pessoa_id']) ? (int)$item['pessoa_id'] : null,
            'familia_id'=>isset($item['familia_id']) ? (int)$item['familia_id'] : null,
            'inscricao_id'=>isset($item['inscricao_id']) ? (int)$item['inscricao_id'] : null,
            'status'=>'Conflito',
            'motivo'=>'O mesmo CPF possui outra linha confirmada com decisão diferente. Revise a duplicidade antes de efetivar.',
        ];
    }

    $decoded = cm_import_decode_item($item);
    $source = $decoded['dados_origem'] ?? [];
    $name = trim((string) ($item['nome'] ?? $source['nome'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($source['telefone'] ?? $item['telefone_informado'] ?? '')) ?: null;

    $personStmt = $pdo->prepare('SELECT * FROM pessoas WHERE cpf = :cpf LIMIT 1 FOR UPDATE');
    $personStmt->execute(['cpf'=>$cpf]);
    $person = $personStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($person)) {
        $insertPerson = $pdo->prepare('INSERT INTO pessoas
            (nome, cpf, nis, rg, data_nascimento, telefone, email, criado_por, atualizado_por)
            VALUES (:nome,:cpf,:nis,:rg,:data_nascimento,:telefone,:email,:criado_por,:atualizado_por)');
        $insertPerson->execute([
            'nome'=>$name !== '' ? $name : 'Cadastro importado',
            'cpf'=>$cpf,
            'nis'=>$source['nis'] ?? null,
            'rg'=>$source['rg'] ?? null,
            'data_nascimento'=>!empty($source['data_nascimento']) ? $source['data_nascimento'] : null,
            'telefone'=>$phone,
            'email'=>$source['email'] ?? null,
            'criado_por'=>$userId,
            'atualizado_por'=>$userId,
        ]);
        $personId = (int) $pdo->lastInsertId();
    } else {
        $personId = (int) $person['id'];
        $fill = $pdo->prepare("UPDATE pessoas SET
            telefone = CASE WHEN (telefone IS NULL OR telefone = '') AND :telefone <> '' THEN :telefone2 ELSE telefone END,
            nis = CASE WHEN (nis IS NULL OR nis = '') AND :nis <> '' THEN :nis2 ELSE nis END,
            rg = CASE WHEN (rg IS NULL OR rg = '') AND :rg <> '' THEN :rg2 ELSE rg END,
            data_nascimento = COALESCE(data_nascimento, :data_nascimento),
            atualizado_por = :atualizado_por
            WHERE id = :id");
        $fill->execute([
            'telefone'=>$phone ?? '', 'telefone2'=>$phone,
            'nis'=>(string)($source['nis'] ?? ''), 'nis2'=>$source['nis'] ?? null,
            'rg'=>(string)($source['rg'] ?? ''), 'rg2'=>$source['rg'] ?? null,
            'data_nascimento'=>!empty($source['data_nascimento']) ? $source['data_nascimento'] : null,
            'atualizado_por'=>$userId, 'id'=>$personId,
        ]);
    }

    $family = cm_import_find_family_link($pdo, $personId);
    if (is_array($family) && ($family['vinculo'] ?? '') === 'integrante') {
        return [
            'pessoa_id'=>$personId,'familia_id'=>(int)$family['id'],'inscricao_id'=>null,
            'status'=>'CadastroPendente',
            'motivo'=>'CPF pertence a integrante de outra família. A decisão foi salva, mas o vínculo familiar precisa ser conferido.',
        ];
    }

    if (!is_array($family)) {
        $tmpCode = 'TMP-' . bin2hex(random_bytes(8));
        $insertFamily = $pdo->prepare('INSERT INTO familias
            (codigo,responsavel_pessoa_id,zona,logradouro,numero,complemento,bairro,comunidade,ponto_referencia,cep,
             quantidade_membros,renda_familiar,criado_por,atualizado_por)
            VALUES (:codigo,:responsavel_pessoa_id,:zona,:logradouro,:numero,:complemento,:bairro,:comunidade,:ponto_referencia,:cep,
                    :quantidade_membros,:renda_familiar,:criado_por,:atualizado_por)');
        $insertFamily->execute([
            'codigo'=>$tmpCode,'responsavel_pessoa_id'=>$personId,
            'zona'=>$source['zona'] ?? null,'logradouro'=>$source['logradouro'] ?? null,'numero'=>$source['numero'] ?? null,
            'complemento'=>$source['complemento'] ?? null,'bairro'=>$source['bairro'] ?? null,'comunidade'=>$source['comunidade'] ?? null,
            'ponto_referencia'=>$source['ponto_referencia'] ?? null,'cep'=>$source['cep'] ?? null,
            'quantidade_membros'=>max(1,(int)($source['quantidade_membros'] ?? 1)),
            'renda_familiar'=>$source['renda_familiar'] ?? null,'criado_por'=>$userId,'atualizado_por'=>$userId,
        ]);
        $familyId = (int) $pdo->lastInsertId();
        $code = sprintf('FAM-%06d', $familyId);
        $pdo->prepare('UPDATE familias SET codigo = :codigo WHERE id = :id')->execute(['codigo'=>$code,'id'=>$familyId]);
    } else {
        $familyId = (int) $family['id'];
    }

    $regStmt = $pdo->prepare('SELECT * FROM comida_mesa_inscricoes WHERE familia_id = :familia_id LIMIT 1 FOR UPDATE');
    $regStmt->execute(['familia_id'=>$familyId]);
    $registration = $regStmt->fetch(PDO::FETCH_ASSOC);
    $poleId = isset($source['polo_id']) && (int)$source['polo_id'] > 0 ? (int)$source['polo_id'] : null;
    $priority = in_array((string)($source['prioridade'] ?? ''), ['alta','normal','baixa'], true)
        ? (string)$source['prioridade'] : 'normal';
    $registrationDate = trim((string)($source['data_inscricao'] ?? ''));
    if ($registrationDate === '') $registrationDate = date('Y-m-d');
    $approvedAt = $targetStatus === 'ativa' ? date('Y-m-d H:i:s') : null;
    $approvedBy = $targetStatus === 'ativa' ? $userId : null;

    if (!is_array($registration)) {
        $insertReg = $pdo->prepare('INSERT INTO comida_mesa_inscricoes
            (familia_id,polo_id,status,prioridade,data_inscricao,data_aprovacao,aprovado_por,motivo_suspensao,observacao,criado_por,atualizado_por)
            VALUES (:familia_id,:polo_id,:status,:prioridade,:data_inscricao,:data_aprovacao,:aprovado_por,NULL,:observacao,:criado_por,:atualizado_por)');
        $insertReg->execute([
            'familia_id'=>$familyId,'polo_id'=>$poleId,'status'=>$targetStatus,'prioridade'=>$priority,
            'data_inscricao'=>$registrationDate,'data_aprovacao'=>$approvedAt,'aprovado_por'=>$approvedBy,
            'observacao'=>'Cadastro efetivado a partir da importação do Comida na Mesa.',
            'criado_por'=>$userId,'atualizado_por'=>$userId,
        ]);
        $registrationId = (int) $pdo->lastInsertId();
        $registrationPoleId = $poleId;
    } else {
        $registrationId = (int) $registration['id'];
        $registrationPoleId = !empty($registration['polo_id']) ? (int)$registration['polo_id'] : $poleId;
        $update = $pdo->prepare('UPDATE comida_mesa_inscricoes SET
            status = :status,
            polo_id = COALESCE(polo_id, :polo_id),
            data_aprovacao = :data_aprovacao,
            aprovado_por = :aprovado_por,
            motivo_suspensao = NULL,
            atualizado_por = :atualizado_por
            WHERE id = :id');
        $update->execute([
            'status'=>$targetStatus,'polo_id'=>$poleId,'data_aprovacao'=>$approvedAt,
            'aprovado_por'=>$approvedBy,'atualizado_por'=>$userId,'id'=>$registrationId,
        ]);
    }

    $reason = null;
    if ($targetStatus === 'ativa' && empty($registrationPoleId)) {
        $reason = 'Beneficiário confirmado e vinculado. Defina o polo antes de registrar a entrega.';
    }

    return [
        'pessoa_id'=>$personId,'familia_id'=>$familyId,'inscricao_id'=>$registrationId,
        'status'=>'Vinculado','motivo'=>$reason,
    ];
}

function cm_import_refresh_counts(PDO $pdo, int $importId): void
{
    $stmt = $pdo->prepare("SELECT
        SUM(situacao_programa = 'Pendente') pendentes,
        SUM(situacao_programa = 'Beneficiario') beneficiarios,
        SUM(situacao_programa = 'ListaEspera') lista_espera
        FROM comida_mesa_importacao_itens WHERE importacao_id = :id");
    $stmt->execute(['id'=>$importId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $update = $pdo->prepare('UPDATE comida_mesa_importacoes SET
        pendentes_confirmacao = :pendentes,
        beneficiarios_confirmados = :beneficiarios,
        lista_espera_confirmados = :lista_espera
        WHERE id = :id');
    $update->execute([
        'pendentes'=>(int)($c['pendentes'] ?? 0),
        'beneficiarios'=>(int)($c['beneficiarios'] ?? 0),
        'lista_espera'=>(int)($c['lista_espera'] ?? 0),
        'id'=>$importId,
    ]);
}

/**
 * @param list<int> $itemIds
 * @return array{updated:int,vinculados:int,pendentes:int,conflitos:int,errors:list<string>}
 */
function cm_import_decide_items(PDO $pdo, array $itemIds, string $decision, int $userId): array
{
    if (!in_array($decision, ['Beneficiario','ListaEspera'], true)) {
        throw new InvalidArgumentException('Decisão do programa inválida.');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn($id) => $id > 0)));
    if ($ids === []) throw new InvalidArgumentException('Selecione pelo menos uma pessoa da lista.');

    $result = ['updated'=>0,'vinculados'=>0,'pendentes'=>0,'conflitos'=>0,'errors'=>[]];
    $imports = [];

    foreach ($ids as $itemId) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM comida_mesa_importacao_itens WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id'=>$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($item)) throw new RuntimeException('Item #' . $itemId . ' não localizado.');

            $sync = cm_import_sync_official($pdo, $item, $decision, $userId);
            $update = $pdo->prepare('UPDATE comida_mesa_importacao_itens SET
                situacao_programa = :situacao_programa,
                pessoa_id = :pessoa_id,
                familia_id = :familia_id,
                inscricao_id = :inscricao_id,
                decidido_em = CURRENT_TIMESTAMP,
                decidido_por = :decidido_por,
                efetivacao_status = :efetivacao_status,
                efetivacao_motivo = :efetivacao_motivo
                WHERE id = :id');
            $update->execute([
                'situacao_programa'=>$decision,
                'pessoa_id'=>$sync['pessoa_id'],
                'familia_id'=>$sync['familia_id'],
                'inscricao_id'=>$sync['inscricao_id'],
                'decidido_por'=>$userId,
                'efetivacao_status'=>$sync['status'],
                'efetivacao_motivo'=>$sync['motivo'],
                'id'=>$itemId,
            ]);

            $imports[(int)$item['importacao_id']] = true;
            $pdo->commit();

            $result['updated']++;
            if ($sync['status'] === 'Vinculado') $result['vinculados']++;
            elseif ($sync['status'] === 'Conflito') $result['conflitos']++;
            else $result['pendentes']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $result['errors'][] = 'Item #' . $itemId . ': ' . $e->getMessage();
        }
    }

    foreach (array_keys($imports) as $importId) cm_import_refresh_counts($pdo, (int)$importId);

    try {
        $app = cm_app();
        $app['audit']->record($userId, null, 'confirmacao_importacao_comida_mesa', 'comida_mesa', null, null, [
            'decisao'=>$decision,'quantidade'=>$result['updated'],'vinculados'=>$result['vinculados'],
            'cadastro_pendente'=>$result['pendentes'],'conflitos'=>$result['conflitos'],
        ]);
    } catch (Throwable) {
    }

    return $result;
}

/**
 * Classifica todos os registros ainda pendentes de uma importação usando o mesmo
 * fluxo seguro da decisão individual. Isso garante que cada item tente criar/vincular
 * pessoa, família e inscrição oficial antes de ser marcado como cadastro pendente.
 *
 * @return array{updated:int,vinculados:int,pendentes:int,conflitos:int,errors:list<string>}
 */
function cm_import_decide_all_pending(PDO $pdo, int $importId, string $decision, int $userId): array
{
    if ($importId < 1) {
        throw new InvalidArgumentException('Importação inválida.');
    }
    if (!in_array($decision, ['Beneficiario', 'ListaEspera'], true)) {
        throw new InvalidArgumentException('Decisão do programa inválida.');
    }

    // Cargas do Comida na Mesa podem ter milhares de registros. O processamento
    // precisa usar a regra completa por item, mas sem o limite padrão curto do PHP.
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $stmt = $pdo->prepare("SELECT id
        FROM comida_mesa_importacao_itens
        WHERE importacao_id = :importacao_id
          AND situacao_programa = 'Pendente'
        ORDER BY id");
    $stmt->execute(['importacao_id'=>$importId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($ids === []) {
        return ['updated'=>0,'vinculados'=>0,'pendentes'=>0,'conflitos'=>0,'errors'=>[]];
    }

    $result = cm_import_decide_items($pdo, $ids, $decision, $userId);

    try {
        $app = cm_app();
        $app['audit']->record($userId, null, 'confirmacao_importacao_comida_mesa_lote_total', 'comida_mesa', null, null, [
            'importacao_id'=>$importId,
            'decisao'=>$decision,
            'quantidade'=>$result['updated'],
            'vinculados'=>$result['vinculados'],
            'cadastro_pendente'=>$result['pendentes'],
            'conflitos'=>$result['conflitos'],
        ]);
    } catch (Throwable) {
    }

    return $result;
}

/**
 * Reprocessa itens que já foram confirmados anteriormente, mas ficaram sem
 * inscricao_id por causa da versão antiga da ação em lote. A decisão já tomada
 * (Beneficiário ou Lista de espera) é preservada.
 *
 * @return array{updated:int,vinculados:int,pendentes:int,conflitos:int,errors:list<string>}
 */
function cm_import_reprocess_confirmed_unlinked(PDO $pdo, int $importId, int $userId): array
{
    if ($importId < 1) {
        throw new InvalidArgumentException('Importação inválida.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $result = ['updated'=>0,'vinculados'=>0,'pendentes'=>0,'conflitos'=>0,'errors'=>[]];

    foreach (['Beneficiario', 'ListaEspera'] as $decision) {
        $stmt = $pdo->prepare("SELECT id
            FROM comida_mesa_importacao_itens
            WHERE importacao_id = :importacao_id
              AND situacao_programa = :situacao_programa
              AND inscricao_id IS NULL
            ORDER BY id");
        $stmt->execute([
            'importacao_id'=>$importId,
            'situacao_programa'=>$decision,
        ]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids === []) {
            continue;
        }

        $partial = cm_import_decide_items($pdo, $ids, $decision, $userId);
        $result['updated'] += $partial['updated'];
        $result['vinculados'] += $partial['vinculados'];
        $result['pendentes'] += $partial['pendentes'];
        $result['conflitos'] += $partial['conflitos'];
        foreach ($partial['errors'] as $error) {
            $result['errors'][] = $error;
        }
    }

    return $result;
}

function cm_import_confirmed_unlinked_count(PDO $pdo, int $importId): int
{
    if ($importId < 1) return 0;

    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM comida_mesa_importacao_itens
        WHERE importacao_id = :importacao_id
          AND situacao_programa IN ('Beneficiario','ListaEspera')
          AND inscricao_id IS NULL");
    $stmt->execute(['importacao_id'=>$importId]);
    return (int) $stmt->fetchColumn();
}

/** @return array<string,mixed>|null */
function cm_import_existing_by_cpf(PDO $pdo, string $cpf): ?array
{
    $stmt = $pdo->prepare('SELECT
        p.id AS pessoa_id, p.nome, p.cpf, p.nis, p.rg, p.data_nascimento, p.telefone, p.email,
        f.id AS familia_id, f.codigo AS familia_codigo, f.zona, f.logradouro, f.numero, f.complemento,
        f.bairro, f.comunidade, f.ponto_referencia, f.cep, f.quantidade_membros, f.renda_familiar,
        i.id AS inscricao_id, i.polo_id, i.status AS inscricao_status, i.prioridade, i.data_inscricao,
        i.observacao, i.motivo_suspensao, COALESCE(i.atualizado_em, i.criado_em) AS versao_atualizacao
        FROM pessoas p
        LEFT JOIN familias f ON f.responsavel_pessoa_id = p.id
        LEFT JOIN comida_mesa_inscricoes i ON i.familia_id = f.id
        WHERE p.cpf = :cpf
        LIMIT 1');
    $stmt->execute(['cpf' => $cpf]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array<string,mixed>|null */
function cm_import_active_pole(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, nome, slug FROM comida_mesa_polos WHERE id = :id AND ativo = 1 LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return array<string,array<string,mixed>> */
function cm_import_pole_map(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, nome, slug FROM comida_mesa_polos WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $row) {
        $map[(string) (int) $row['id']] = $row;
        $map[cm_import_header_key((string) $row['nome'])] = $row;
        $map[cm_import_header_key((string) $row['slug'])] = $row;
    }
    return $map;
}

function cm_import_normalize_zone(string $value): ?string
{
    $key = cm_import_header_key($value);
    return match ($key) {
        'URBANA', 'URBANO', 'ZONA URBANA' => 'urbana',
        'RURAL', 'ZONA RURAL', 'INTERIOR' => 'rural',
        default => null,
    };
}

function cm_import_normalize_status(string $value): ?string
{
    $key = cm_import_header_key($value);
    return match ($key) {
        'ATIVA', 'ATIVO', 'BENEFICIARIA ATIVA', 'BENEFICIARIO ATIVO', 'APROVADA', 'APROVADO' => 'ativa',
        'EM ANALISE', 'ANALISE', 'PENDENTE' => 'em_analise',
        'LISTA DE ESPERA', 'ESPERA' => 'lista_espera',
        'SUSPENSA', 'SUSPENSO' => 'suspensa',
        'BLOQUEADA', 'BLOQUEADO' => 'bloqueada',
        'ENCERRADA', 'ENCERRADO', 'INATIVA', 'INATIVO' => 'encerrada',
        default => null,
    };
}

function cm_import_normalize_priority(string $value): ?string
{
    $key = cm_import_header_key($value);
    return match ($key) {
        'ALTA', 'URGENTE', 'PRIORITARIA', 'PRIORITARIO' => 'alta',
        'NORMAL', 'MEDIA', 'MEDIO' => 'normal',
        'BAIXA', 'BAIXO' => 'baixa',
        default => null,
    };
}

function cm_import_money(mixed $value): ?float
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') return null;
    $raw = preg_replace('/[^0-9,.-]/', '', $raw) ?: '';
    if ($raw === '') return null;
    if (str_contains($raw, ',') && str_contains($raw, '.')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } elseif (str_contains($raw, ',')) {
        $raw = str_replace(',', '.', $raw);
    }
    return is_numeric($raw) ? round((float) $raw, 2) : null;
}

/** @return array<string,string> */
function cm_import_detect_columns(array $headerRow): array
{
    $headerMap = [];
    foreach ($headerRow as $column => $value) {
        if ($column === '__row') continue;
        $key = cm_import_header_key((string) $value);
        if ($key !== '') $headerMap[$key] = (string) $column;
    }

    $aliases = [
        'ordem' => ['ORD', 'ORDEM', 'N', 'Nº'],
        'nome' => ['NOME', 'RESPONSAVEL FAMILIAR', 'RESPONSÁVEL FAMILIAR', 'NOME RESPONSAVEL', 'NOME DO RESPONSAVEL'],
        'cpf' => ['CPF', 'CPF RESPONSAVEL', 'CPF DO RESPONSAVEL', 'CPF RG', 'CPF/RG', 'CPF_RG'],
        'nis' => ['NIS', 'NIS RESPONSAVEL'],
        'rg' => ['RG', 'IDENTIDADE'],
        'data_nascimento' => ['DATA NASCIMENTO', 'DATA DE NASCIMENTO', 'NASCIMENTO', 'DATA NASC', 'DT NASC', 'DT. NASC.'],
        'conjuge' => ['CONJUGE', 'CÔNJUGE', 'NOME CONJUGE', 'NOME DO CONJUGE'],
        'telefone' => ['TELEFONE', 'CELULAR', 'CONTATO', 'WHATSAPP'],
        'email' => ['EMAIL', 'E MAIL'],
        'zona' => ['ZONA', 'ZONA RESIDENCIA', 'ZONA DE RESIDENCIA'],
        'logradouro' => ['ENDERECO', 'ENDEREÇO', 'LOGRADOURO', 'RUA'],
        'numero' => ['NUMERO', 'NÚMERO', 'N', 'Nº'],
        'complemento' => ['COMPLEMENTO'],
        'bairro' => ['BAIRRO'],
        'comunidade' => ['COMUNIDADE', 'LOCALIDADE RURAL'],
        'ponto_referencia' => ['PONTO DE REFERENCIA', 'REFERENCIA', 'PONTO REFERENCIA'],
        'cep' => ['CEP'],
        'quantidade_membros' => ['QUANTIDADE MEMBROS', 'QTD MEMBROS', 'MEMBROS', 'QUANTIDADE DE MEMBROS'],
        'renda_familiar' => ['RENDA FAMILIAR', 'RENDA', 'RENDA TOTAL'],
        'polo' => ['POLO', 'POLO DE ENTREGA', 'POLO ENTREGA', 'LOCAL', 'LOCAL DE ENTREGA'],
        'status' => ['STATUS', 'SITUACAO', 'SITUAÇÃO', 'SITUACAO NO PROGRAMA', 'SITUAÇÃO NO PROGRAMA'],
        'prioridade' => ['PRIORIDADE'],
        'data_inscricao' => ['DATA INSCRICAO', 'DATA DE INSCRICAO', 'DATA INSCRIÇÃO', 'INSCRICAO EM'],
        'observacao' => ['OBSERVACAO', 'OBSERVAÇÃO', 'OBS', 'ANOTACAO'],
        'motivo_suspensao' => ['MOTIVO SUSPENSAO', 'MOTIVO BLOQUEIO'],
    ];

    $columns = [];
    foreach ($aliases as $field => $options) {
        foreach ($options as $option) {
            $normalized = cm_import_header_key($option);
            if (isset($headerMap[$normalized])) {
                $columns[$field] = $headerMap[$normalized];
                break;
            }
        }
    }
    return $columns;
}

/**
 * Identifica linhas de cabeçalho, inclusive quando a planilha reúne vários blocos
 * com ordem de colunas diferente. Isso evita tratar cabeçalhos repetidos como pessoas
 * e permite remapear CPF/CONTATO a cada novo bloco.
 *
 * @param array<string,mixed> $row
 * @param array<string,string>|null $detected
 */
function cm_import_is_header_row(array $row, ?array $detected = null): bool
{
    $detected ??= cm_import_detect_columns($row);
    if (empty($detected['nome'])) {
        return false;
    }

    $recognized = count($detected);
    if ($recognized < 3) {
        return false;
    }

    foreach ($row as $column => $value) {
        if ($column === '__row') continue;
        if (cm_import_header_key((string) $value) === 'NOME') {
            return true;
        }
    }

    return false;
}

/** @return array<string,mixed> */
function cm_import_prepare(PDO $pdo, array $rows, array $options = []): array
{
    if (count($rows) < 2) {
        throw new InvalidArgumentException('A planilha não possui linhas suficientes.');
    }

    $defaultPoleId = isset($options['polo_padrao_id']) && (int) $options['polo_padrao_id'] > 0 ? (int) $options['polo_padrao_id'] : null;
    $defaultStatus = cm_import_normalize_status((string) ($options['status_padrao'] ?? 'em_analise')) ?? 'em_analise';
    $defaultPriority = cm_import_normalize_priority((string) ($options['prioridade_padrao'] ?? 'normal')) ?? 'normal';
    $defaultZone = cm_import_normalize_zone((string) ($options['zona_padrao'] ?? ''));
    $updateExisting = !empty($options['atualizar_existentes']);

    if ($defaultPoleId !== null && cm_import_active_pole($pdo, $defaultPoleId) === null) {
        throw new InvalidArgumentException('O polo padrão selecionado não está ativo.');
    }

    $poleMap = cm_import_pole_map($pdo);
    $seenCpf = [];
    $prepared = [];
    $activeColumns = [];
    $recognizedColumns = [];
    $headerCount = 0;
    $summary = [
        'total' => 0,
        'novos' => 0,
        'atualizar' => 0,
        'ignorar' => 0,
        'revisar' => 0,
        'cpf_invalidos' => 0,
        'telefone_invalido' => 0,
        'polo_pendente' => 0,
        'cadastro_pendente' => 0,
        'cabecalhos_detectados' => 0,
    ];

    foreach ($rows as $row) {
        $detected = cm_import_detect_columns($row);
        if (cm_import_is_header_row($row, $detected)) {
            $activeColumns = $detected;
            $recognizedColumns = array_replace($recognizedColumns, $detected);
            $headerCount++;
            continue;
        }

        // Ignora qualquer conteúdo anterior ao primeiro cabeçalho reconhecido.
        if (empty($activeColumns['nome'])) {
            continue;
        }

        $columns = $activeColumns;
        $get = static function (string $field) use ($columns, $row): string {
            return isset($columns[$field], $row[$columns[$field]])
                ? cm_import_clean_text((string) $row[$columns[$field]])
                : '';
        };

        $name = $get('nome');
        $cpfSource = $get('cpf');
        $cpfRaw = cm_import_digits($cpfSource);
        $phoneSource = $get('telefone');
        $phone = cm_import_digits($phoneSource);

        if ($name === '' && $cpfRaw === '' && $phone === '') {
            continue;
        }

        $summary['total']++;
        $rowNumber = (int) ($row['__row'] ?? 0);
        $issues = [];

        if (mb_strlen($name) < 3) {
            $issues['cadastro'][] = 'Nome do responsável não informado ou incompleto.';
        }

        // Excel costuma remover um único zero à esquerda de CPFs. Recuperamos
        // somente esse caso, e apenas quando o CPF resultante é matematicamente válido.
        // Documentos menores não são preenchidos artificialmente porque a coluna da
        // lista pode conter RG em vez de CPF.
        $cpfCandidate = $cpfRaw;
        if (strlen($cpfRaw) === 10) {
            $withLeadingZero = '0' . $cpfRaw;
            if (Validator::cpf($withLeadingZero)) {
                $cpfCandidate = $withLeadingZero;
            }
        }
        $cpfValid = strlen($cpfCandidate) === 11 && Validator::cpf($cpfCandidate);
        if (!$cpfValid) {
            $issues['cpf'][] = $cpfRaw === '' ? 'CPF não informado.' : 'CPF/RG informado não corresponde a um CPF válido.';
            $summary['cpf_invalidos']++;
        }

        if ($cpfValid) {
            if (isset($seenCpf[$cpfCandidate])) {
                $issues['cpf'][] = 'CPF duplicado na planilha; primeira ocorrência na linha ' . $seenCpf[$cpfCandidate] . '.';
            } else {
                $seenCpf[$cpfCandidate] = $rowNumber;
            }
        }

        $existing = $cpfValid ? cm_import_existing_by_cpf($pdo, $cpfCandidate) : null;
        $existingPhone = cm_import_digits($existing['telefone'] ?? '');
        $hasExistingPhone = $existing !== null && in_array(strlen($existingPhone), [10, 11], true);
        if (($phone === '' || !in_array(strlen($phone), [10, 11], true)) && !($updateExisting && $hasExistingPhone && !isset($columns['telefone']))) {
            $issues['telefone'][] = $phone === '' ? 'Telefone não informado.' : 'Telefone fora do padrão de 10/11 dígitos.';
            $summary['telefone_invalido']++;
        }

        $birthRaw = $get('data_nascimento');
        $birthDate = cm_import_excel_date($birthRaw);
        if ($birthRaw !== '' && $birthDate === null) {
            $issues['cadastro'][] = 'Data de nascimento inválida.';
        }

        $zoneRaw = $get('zona');
        $zone = cm_import_normalize_zone($zoneRaw) ?? $defaultZone;
        $district = $get('bairro') !== '' ? $get('bairro') : null;
        $community = $get('comunidade') !== '' ? $get('comunidade') : null;
        if ($zone === null) {
            if ($community !== null && $district === null) $zone = 'rural';
            elseif ($district !== null) $zone = 'urbana';
        }
        if ($zone === null) {
            $issues['cadastro'][] = 'Zona urbana/rural não pôde ser determinada.';
        } elseif ($zone === 'urbana' && $district === null) {
            $issues['cadastro'][] = 'Bairro obrigatório para zona urbana.';
        } elseif ($zone === 'rural' && $community === null) {
            $issues['cadastro'][] = 'Comunidade obrigatória para zona rural.';
        }

        $statusRaw = $get('status');
        $status = $statusRaw !== '' ? cm_import_normalize_status($statusRaw) : $defaultStatus;
        if ($status === null) {
            $status = $defaultStatus;
            $issues['cadastro'][] = 'Situação do programa não reconhecida: ' . $statusRaw . '.';
        }

        $priorityRaw = $get('prioridade');
        $priority = $priorityRaw !== '' ? cm_import_normalize_priority($priorityRaw) : $defaultPriority;
        if ($priority === null) {
            $priority = $defaultPriority;
            $issues['cadastro'][] = 'Prioridade não reconhecida: ' . $priorityRaw . '.';
        }

        $poleRaw = $get('polo');
        $pole = null;
        if ($poleRaw !== '') {
            $pole = $poleMap[(string) (int) $poleRaw] ?? $poleMap[cm_import_header_key($poleRaw)] ?? null;
        }
        if ($pole === null && $defaultPoleId !== null) {
            $pole = cm_import_active_pole($pdo, $defaultPoleId);
        }
        $poleId = $pole ? (int) $pole['id'] : null;
        if ($status === 'ativa' && $poleId === null) {
            $issues['polo'][] = $poleRaw === '' ? 'Inscrição ativa sem polo definido.' : 'Polo/local não localizado: ' . $poleRaw . '.';
            $summary['polo_pendente']++;
        }

        $membersRaw = cm_import_digits($get('quantidade_membros'));
        $membersCount = $membersRaw !== '' ? max(1, (int) $membersRaw) : 1;
        $incomeRaw = $get('renda_familiar');
        $familyIncome = cm_import_money($incomeRaw);
        if ($incomeRaw !== '' && $familyIncome === null) {
            $issues['cadastro'][] = 'Renda familiar inválida.';
        }

        $registrationDateRaw = $get('data_inscricao');
        $registrationDate = cm_import_excel_date($registrationDateRaw) ?? date('Y-m-d');
        if ($registrationDateRaw !== '' && cm_import_excel_date($registrationDateRaw) === null) {
            $issues['cadastro'][] = 'Data de inscrição inválida.';
        }

        if ($existing !== null && cm_import_header_key((string) $existing['nome']) !== cm_import_header_key($name)) {
            $issues['cadastro'][] = 'CPF já cadastrado para outra identificação: ' . (string) $existing['nome'] . '.';
        }

        $issueGroups = array_keys(array_filter($issues));
        $issueCount = 0;
        foreach ($issues as $messages) $issueCount += count($messages);

        if ($issueCount > 0) {
            if (count($issueGroups) > 1 || isset($issues['cadastro'])) {
                $classification = 'Revisar Cadastro';
                $summary['cadastro_pendente']++;
            } elseif (isset($issues['cpf'])) {
                $classification = 'Revisar CPF';
            } elseif (isset($issues['telefone'])) {
                $classification = 'Revisar Telefone';
            } elseif (isset($issues['polo'])) {
                $classification = 'Revisar Polo';
            } else {
                $classification = 'Revisar Cadastro';
            }
            $action = 'Revisar';
            $summary['revisar']++;
        } elseif ($existing !== null && !empty($existing['inscricao_id'])) {
            $action = $updateExisting ? 'Atualizar' : 'Ignorar';
            $classification = $updateExisting ? 'Cadastro existente' : 'Já cadastrado';
            $summary[$updateExisting ? 'atualizar' : 'ignorar']++;
        } else {
            $action = 'Novo';
            $classification = 'Pronto para importar';
            $summary['novos']++;
        }

        $prepared[] = [
            'row' => $rowNumber,
            'action' => $action,
            'classification' => $classification,
            'issues' => $issues,
            'existing' => $existing,
            'data' => [
                'nome' => $name,
                'cpf' => $cpfValid ? $cpfCandidate : '',
                'cpf_informado' => $cpfSource !== '' ? $cpfSource : null,
                'telefone' => $phone,
                'telefone_informado' => $phoneSource !== '' ? $phoneSource : null,
                'nis' => (($v = cm_import_digits($get('nis'))) !== '' ? $v : null),
                'rg' => $get('rg') !== '' ? $get('rg') : null,
                'data_nascimento' => $birthDate,
                'email' => $get('email') !== '' ? $get('email') : null,
                'zona' => $zone,
                'logradouro' => $get('logradouro') !== '' ? $get('logradouro') : null,
                'numero' => $get('numero') !== '' ? $get('numero') : null,
                'complemento' => $get('complemento') !== '' ? $get('complemento') : null,
                'bairro' => $district,
                'comunidade' => $community,
                'ponto_referencia' => $get('ponto_referencia') !== '' ? $get('ponto_referencia') : null,
                'cep' => (($v = cm_import_digits($get('cep'))) !== '' ? $v : null),
                'quantidade_membros' => $membersCount,
                'renda_familiar' => $familyIncome,
                'polo_id' => $poleId,
                'polo_informado' => $poleRaw !== '' ? $poleRaw : null,
                'status' => $status,
                'prioridade' => $priority,
                'data_inscricao' => $registrationDate,
                'observacao' => $get('observacao') !== '' ? $get('observacao') : null,
                'motivo_suspensao' => $get('motivo_suspensao') !== '' ? $get('motivo_suspensao') : null,
                // Campos da fonte que não possuem coluna própria no cadastro oficial.
                // Permanecem em dados_json para auditoria e revisão da importação.
                'ordem_origem' => $get('ordem') !== '' ? $get('ordem') : null,
                'conjuge_origem' => $get('conjuge') !== '' ? $get('conjuge') : null,
                'documento_origem' => $cpfSource !== '' ? $cpfSource : null,
                'local_origem' => $poleRaw !== '' ? $poleRaw : null,
            ],
            'source_has' => array_fill_keys(array_keys($columns), true),
        ];
    }

    if ($headerCount === 0 || empty($recognizedColumns['nome'])) {
        throw new InvalidArgumentException('Não encontrei um cabeçalho válido com a coluna NOME ou RESPONSÁVEL FAMILIAR.');
    }

    $summary['cabecalhos_detectados'] = $headerCount;
    return ['rows' => $prepared, 'summary' => $summary, 'columns' => $recognizedColumns];
}

/** @return array<string,mixed> */
function cm_import_merge_existing(array $incoming, array $existing, array $sourceHas): array
{
    $fieldMap = [
        'nis'=>'nis','rg'=>'rg','data_nascimento'=>'data_nascimento','telefone'=>'telefone','email'=>'email',
        'zona'=>'zona','logradouro'=>'logradouro','numero'=>'numero','complemento'=>'complemento','bairro'=>'bairro',
        'comunidade'=>'comunidade','ponto_referencia'=>'ponto_referencia','cep'=>'cep','quantidade_membros'=>'quantidade_membros',
        'renda_familiar'=>'renda_familiar','polo'=>'polo_id','status'=>'status','prioridade'=>'prioridade',
        'data_inscricao'=>'data_inscricao','observacao'=>'observacao','motivo_suspensao'=>'motivo_suspensao',
    ];

    $merged = $incoming;
    $merged['nome'] = $incoming['nome'] ?: (string) ($existing['nome'] ?? '');
    $merged['cpf'] = (string) ($existing['cpf'] ?? $incoming['cpf']);

    foreach ($fieldMap as $sourceColumn => $targetField) {
        if (!isset($sourceHas[$sourceColumn])) {
            $existingKey = $targetField === 'status' ? 'inscricao_status' : $targetField;
            $merged[$targetField] = $existing[$existingKey] ?? $merged[$targetField] ?? null;
        }
    }

    $merged['inscricao_id'] = isset($existing['inscricao_id']) ? (int) $existing['inscricao_id'] : null;
    $merged['versao_atualizacao'] = $existing['versao_atualizacao'] ?? null;
    return $merged;
}

function cm_import_item_log(PDO $pdo, int $importId, array $row, string $status, ?array $ids = null, ?string $message = null): void
{
    $data = $row['data'] ?? [];
    $issues = [];
    foreach (($row['issues'] ?? []) as $messages) {
        foreach ($messages as $item) $issues[] = $item;
    }
    if ($message !== null && $message !== '') $issues[] = $message;

    $stmt = $pdo->prepare('INSERT INTO comida_mesa_importacao_itens
        (importacao_id, linha, status, situacao_programa, pessoa_id, familia_id, inscricao_id, nome, cpf_informado, cpf_validado,
         telefone_informado, polo_informado, classificacao, motivos, dados_json, efetivacao_status)
        VALUES (:importacao_id, :linha, :status, "Pendente", :pessoa_id, :familia_id, :inscricao_id, :nome, :cpf_informado, :cpf_validado,
                :telefone_informado, :polo_informado, :classificacao, :motivos, :dados_json, :efetivacao_status)');
    $stmt->execute([
        'importacao_id' => $importId,
        'linha' => (int) ($row['row'] ?? 0),
        'status' => $status,
        'pessoa_id' => $ids['pessoa_id'] ?? null,
        'familia_id' => $ids['familia_id'] ?? null,
        'inscricao_id' => $ids['inscricao_id'] ?? null,
        'nome' => (string) ($data['nome'] ?? ''),
        'cpf_informado' => $data['cpf_informado'] ?? null,
        'cpf_validado' => $data['cpf'] !== '' ? $data['cpf'] : null,
        'telefone_informado' => $data['telefone_informado'] ?? ($data['telefone'] ?? null),
        'polo_informado' => $data['polo_informado'] ?? null,
        'classificacao' => (string) ($row['classification'] ?? ''),
        'motivos' => $issues ? implode(' | ', $issues) : null,
        'dados_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'efetivacao_status' => !empty($ids['inscricao_id']) ? 'Vinculado' : 'Pendente',
    ]);
}

/** @return array<string,mixed> */
function cm_import_execute(PDO $pdo, array $prepared, string $filename, string $fileHash, array $options = []): array
{
    $app = cm_app();
    $audit = $app['audit'];
    $user = $app['user'];
    $userId = (int) $user->id;

    $stmt = $pdo->prepare('INSERT INTO comida_mesa_importacoes
        (arquivo_nome, arquivo_hash, status, total_linhas, polo_padrao_id, status_padrao, prioridade_padrao,
         zona_padrao, atualizar_existentes, pendentes_confirmacao, criado_por)
        VALUES (:arquivo_nome, :arquivo_hash, "Processando", :total_linhas, :polo_padrao_id, "em_analise",
                :prioridade_padrao, :zona_padrao, 0, :pendentes_confirmacao, :criado_por)');
    $stmt->execute([
        'arquivo_nome' => $filename,
        'arquivo_hash' => $fileHash,
        'total_linhas' => count($prepared),
        'polo_padrao_id' => !empty($options['polo_padrao_id']) ? (int) $options['polo_padrao_id'] : null,
        'prioridade_padrao' => (string) ($options['prioridade_padrao'] ?? 'normal'),
        'zona_padrao' => ($options['zona_padrao'] ?? '') !== '' ? (string) $options['zona_padrao'] : null,
        'pendentes_confirmacao' => count($prepared),
        'criado_por' => $userId,
    ]);
    $importId = (int) $pdo->lastInsertId();

    $counts = ['pendentes'=>0,'com_pendencia_cadastral'=>0,'localizados'=>0,'erros'=>0];
    $errors = [];

    foreach ($prepared as $row) {
        try {
            $ids = null;
            if (is_array($row['existing'] ?? null)) {
                $existing = $row['existing'];
                $ids = [
                    'pessoa_id' => $existing['pessoa_id'] ?? null,
                    'familia_id' => $existing['familia_id'] ?? null,
                    'inscricao_id' => $existing['inscricao_id'] ?? null,
                ];
                if (!empty($ids['inscricao_id'])) $counts['localizados']++;
            }

            $hasIssues = false;
            foreach (($row['issues'] ?? []) as $messages) {
                if (!empty($messages)) { $hasIssues = true; break; }
            }
            if ($hasIssues) $counts['com_pendencia_cadastral']++;

            cm_import_item_log($pdo, $importId, $row, 'Aguardando', $ids);
            $counts['pendentes']++;
        } catch (Throwable $e) {
            $counts['erros']++;
            $errors[] = ['row'=>(int)($row['row'] ?? 0),'message'=>$e->getMessage()];
        }
    }

    $done = $pdo->prepare('UPDATE comida_mesa_importacoes SET
        pendentes_confirmacao = :pendentes,
        erros = :erros,
        status = "Aguardando confirmação",
        finalizado_em = CURRENT_TIMESTAMP
        WHERE id = :id');
    $done->execute([
        'pendentes'=>$counts['pendentes'],
        'erros'=>$counts['erros'],
        'id'=>$importId,
    ]);

    $audit->record($userId, null, 'importacao_comida_mesa', 'comida_mesa', $filename, null, [
        'importacao_id'=>$importId,
        'pendentes_confirmacao'=>$counts['pendentes'],
        'com_pendencia_cadastral'=>$counts['com_pendencia_cadastral'],
        'cadastros_localizados'=>$counts['localizados'],
        'erros'=>$counts['erros'],
    ]);

    return ['import_id'=>$importId,'counts'=>$counts,'errors'=>$errors];
}

