<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function pe_candidate_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM pe_candidatos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pe_recent_candidates(PDO $pdo, int $limit = 300): array
{
    $limit = max(1, min($limit, 1000));
    $sql = 'SELECT id, nome, cpf, cpf_informado, telefone, endereco, bairro, nis, data_nascimento, escolaridade, instituicao_ensino, situacao_escolar, turno_estudo
            FROM pe_candidatos ORDER BY nome ASC LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}

function pe_save_triage(PDO $pdo, array $input): int
{
    $name = trim((string) ($input['nome'] ?? ''));
    $cpfInformed = pe_digits($input['cpf'] ?? '');
    $cpfCandidate = $cpfInformed;
    if ($cpfCandidate !== '' && strlen($cpfCandidate) < 11) {
        $cpfCandidate = str_pad($cpfCandidate, 11, '0', STR_PAD_LEFT);
    }
    $cpfValid = strlen($cpfCandidate) === 11 && pe_validate_cpf($cpfCandidate);
    $birth = pe_date_or_null($input['data_nascimento'] ?? '');
    $phone = pe_digits($input['telefone'] ?? '');
    $whatsapp = pe_digits($input['whatsapp'] ?? '');

    if ($name === '' || strlen($name) < 3) {
        throw new InvalidArgumentException('Informe o nome completo.');
    }
    if ($whatsapp !== '' && !in_array(strlen($whatsapp), [10, 11], true)) {
        throw new InvalidArgumentException('WhatsApp inválido.');
    }

    $cpfDuplicate = false;
    if ($cpfValid) {
        $checkCpf = $pdo->prepare('SELECT COUNT(*) FROM pe_candidatos WHERE cpf = :cpf');
        $checkCpf->execute(['cpf' => $cpfCandidate]);
        $cpfDuplicate = (int) $checkCpf->fetchColumn() > 0;
    }

    $reviewCpf = ($cpfInformed === '' || !$cpfValid || $cpfDuplicate) ? 1 : 0;
    $reviewPhone = ($phone === '' || !in_array(strlen($phone), [10, 11], true)) ? 1 : 0;
    $reviewBirth = $birth === null ? 1 : 0;
    $reviewStatus = pe_review_status_from_flags($reviewCpf, $reviewPhone, $reviewBirth);
    $reviewReasons = [];

    if ($cpfInformed === '') {
        $reviewReasons[] = 'CPF não informado';
    } elseif (!$cpfValid) {
        $reviewReasons[] = 'CPF inconsistente';
    } elseif ($cpfDuplicate) {
        $reviewReasons[] = 'CPF duplicado com outro cadastro';
    }
    if ($phone === '') {
        $reviewReasons[] = 'Telefone não informado';
    } elseif (!in_array(strlen($phone), [10, 11], true)) {
        $reviewReasons[] = 'Telefone fora do padrão';
    }
    if ($birth === null) {
        $reviewReasons[] = trim((string) ($input['data_nascimento'] ?? '')) === '' ? 'Data de nascimento não informada' : 'Data de nascimento inválida';
    }

    $data = [
        'nome' => $name,
        'sexo' => pe_nullable($input['sexo'] ?? null),
        'data_nascimento' => $birth,
        'rg' => pe_nullable($input['rg'] ?? null),
        'cpf' => $cpfValid ? $cpfCandidate : null,
        'cpf_informado' => $cpfInformed === '' ? null : $cpfInformed,
        'estado_civil' => pe_nullable($input['estado_civil'] ?? null),
        'nis' => pe_nullable($input['nis'] ?? null),
        'cor_raca' => pe_nullable($input['cor_raca'] ?? null),
        'rua' => pe_nullable($input['rua'] ?? null),
        'bairro' => pe_nullable($input['bairro'] ?? null),
        'ponto_referencia' => pe_nullable($input['ponto_referencia'] ?? null),
        'municipio' => pe_nullable($input['municipio'] ?? 'Coari'),
        'cep' => pe_nullable(pe_digits($input['cep'] ?? '')),
        'telefone' => $phone === '' ? null : $phone,
        'whatsapp' => $whatsapp === '' ? null : $whatsapp,
        'email' => pe_nullable($input['email'] ?? null),
        'responsavel_familiar' => pe_nullable($input['responsavel_familiar'] ?? null),
        'total_membros_familia' => ($input['total_membros_familia'] ?? '') === '' ? null : max(0, (int) $input['total_membros_familia']),
        'atividade_responsaveis' => pe_nullable($input['atividade_responsaveis'] ?? null),
        'renda_familiar_mensal' => ($input['renda_familiar_mensal'] ?? '') === '' ? null : (float) str_replace(',', '.', str_replace('.', '', (string) $input['renda_familiar_mensal'])),
        'matriculado' => pe_nullable($input['matriculado'] ?? null),
        'escolaridade' => pe_nullable($input['escolaridade'] ?? null),
        'instituicao_ensino' => pe_nullable($input['instituicao_ensino'] ?? null),
        'situacao_escolar' => pe_nullable($input['situacao_escolar'] ?? null),
        'turno_estudo' => pe_nullable($input['turno_estudo'] ?? null),
        'situacao_habitacional' => pe_nullable($input['situacao_habitacional'] ?? null),
        'condicao_moradia' => pe_nullable($input['condicao_moradia'] ?? null),
        'numero_comodos' => ($input['numero_comodos'] ?? '') === '' ? null : max(0, (int) $input['numero_comodos']),
        'agua_tratada' => pe_nullable($input['agua_tratada'] ?? null),
        'energia_eletrica' => pe_nullable($input['energia_eletrica'] ?? null),
        'coleta_lixo' => pe_nullable($input['coleta_lixo'] ?? null),
        'vulnerabilidades' => pe_json_list($input['vulnerabilidades'] ?? []),
        'vulnerabilidade_outro' => pe_nullable($input['vulnerabilidade_outro'] ?? null),
        'data_entrevista' => pe_date_or_null($input['data_entrevista'] ?? '') ?: date('Y-m-d'),
        'tecnico_triagem' => pe_nullable($input['tecnico_triagem'] ?? null),
        'status' => 'Em triagem',
        'revisao_status' => $reviewStatus,
        'revisao_cpf' => $reviewCpf,
        'revisao_telefone' => $reviewPhone,
        'revisao_nascimento' => $reviewBirth,
        'cpf_duplicado' => $cpfDuplicate ? 1 : 0,
        'cpf_revisado_confirmado' => 0,
        'telefone_revisado_confirmado' => 0,
        'nascimento_revisado_confirmado' => 0,
        'cpf_duplicado_confirmado' => 0,
        'revisao_motivos' => $reviewReasons ? implode(' | ', $reviewReasons) : null,
        'revisao_atualizada_em' => $reviewStatus ? date('Y-m-d H:i:s') : null,
        'origem' => 'manual',
    ];

    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('E-mail inválido.');
    }

    $pdo->beginTransaction();
    try {
        if ($cpfDuplicate && $data['cpf']) {
            $markExisting = $pdo->prepare('UPDATE pe_candidatos SET
                revisao_cpf=1,
                cpf_duplicado=1,
                cpf_duplicado_confirmado=0,
                revisao_status=CASE WHEN revisao_telefone=1 OR revisao_nascimento=1 THEN "Revisar Cadastro" ELSE "Revisar CPF" END,
                revisao_motivos=CASE
                    WHEN revisao_motivos IS NULL OR revisao_motivos="" THEN "CPF duplicado com outro cadastro"
                    WHEN revisao_motivos NOT LIKE "%CPF duplicado%" THEN CONCAT(revisao_motivos, " | CPF duplicado com outro cadastro")
                    ELSE revisao_motivos
                END,
                revisao_atualizada_em=CURRENT_TIMESTAMP
                WHERE cpf=:cpf');
            $markExisting->execute(['cpf' => $data['cpf']]);
        }

        $columns = array_keys($data);
        $sql = 'INSERT INTO pe_candidatos (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pe_save_visit(PDO $pdo, array $input): int
{
    $candidateId = (int) ($input['candidato_id'] ?? 0);
    if ($candidateId <= 0 || !pe_candidate_by_id($pdo, $candidateId)) {
        throw new InvalidArgumentException('Selecione um candidato válido.');
    }
    $visitDate = pe_date_or_null($input['data_visita'] ?? '') ?: date('Y-m-d');
    $decision = trim((string) ($input['decisao'] ?? 'Pendente'));
    if (!in_array($decision, ['Deferido', 'Indeferido', 'Pendente'], true)) {
        $decision = 'Pendente';
    }
    $technical = trim((string) ($input['parecer_tecnico'] ?? ''));
    if ($technical === '') {
        throw new InvalidArgumentException('Informe o parecer técnico.');
    }

    $stmt = $pdo->prepare('INSERT INTO pe_visitas_sociais
        (candidato_id, entrevistador, data_visita, informacoes_complementares, parecer_tecnico, decisao, tecnico_responsavel)
        VALUES (:candidato_id, :entrevistador, :data_visita, :informacoes_complementares, :parecer_tecnico, :decisao, :tecnico_responsavel)');
    $stmt->execute([
        'candidato_id' => $candidateId,
        'entrevistador' => pe_nullable($input['entrevistador'] ?? null),
        'data_visita' => $visitDate,
        'informacoes_complementares' => pe_nullable($input['informacoes_complementares'] ?? null),
        'parecer_tecnico' => $technical,
        'decisao' => $decision,
        'tecnico_responsavel' => pe_nullable($input['tecnico_responsavel'] ?? null),
    ]);

    $status = $decision === 'Deferido' ? 'Deferido' : ($decision === 'Indeferido' ? 'Indeferido' : 'Em análise');
    $upd = $pdo->prepare('UPDATE pe_candidatos SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $upd->execute(['status' => $status, 'id' => $candidateId]);
    return (int) $pdo->lastInsertId();
}

function pe_save_profile(PDO $pdo, array $input, array $files): int
{
    $candidateId = (int) ($input['candidato_id'] ?? 0);
    $candidate = pe_candidate_by_id($pdo, $candidateId);
    if (!$candidate) {
        throw new InvalidArgumentException('Selecione um candidato válido.');
    }

    $photoPath = null;
    if (!empty($files['foto']['tmp_name']) && is_uploaded_file($files['foto']['tmp_name'])) {
        if ((int) $files['foto']['size'] > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('A foto deve ter no máximo 3 MB.');
        }
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? finfo_file($finfo, $files['foto']['tmp_name']) : (string) ($files['foto']['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Formato de foto não permitido. Use JPG, PNG ou WEBP.');
        }
        $relativeDirectory = 'primeiro-emprego/' . \App\Core\Storage::buildRelativeDirectory();
        $dir = \App\Core\Storage::ensureImageDirectory($relativeDirectory);
        $filename = \App\Core\Storage::generateRandomFilename($allowed[$mime]);
        if (!move_uploaded_file($files['foto']['tmp_name'], $dir . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Não foi possível salvar a foto.');
        }
        $photoPath = $relativeDirectory . '/' . $filename;
    }

    $params = [
        'candidato_id' => $candidateId,
        'foto_path' => $photoPath,
        'nivel_escolaridade' => pe_nullable($input['nivel_escolaridade'] ?? null),
        'situacao_escolar' => pe_nullable($input['situacao_escolar'] ?? null),
        'instituicao_ensino' => pe_nullable($input['instituicao_ensino'] ?? null),
        'serie_periodo' => pe_nullable($input['serie_periodo'] ?? null),
        'turno_estudo' => pe_nullable($input['turno_estudo'] ?? null),
        'local_atuacao' => pe_nullable($input['local_atuacao'] ?? null),
        'turno_atuacao' => pe_nullable($input['turno_atuacao'] ?? null),
    ];

    $sql = 'INSERT INTO pe_fichas_cadastrais
            (candidato_id, foto_path, nivel_escolaridade, situacao_escolar, instituicao_ensino, serie_periodo, turno_estudo, local_atuacao, turno_atuacao)
            VALUES (:candidato_id, :foto_path, :nivel_escolaridade, :situacao_escolar, :instituicao_ensino, :serie_periodo, :turno_estudo, :local_atuacao, :turno_atuacao)
            ON DUPLICATE KEY UPDATE
              foto_path = COALESCE(VALUES(foto_path), foto_path),
              nivel_escolaridade = VALUES(nivel_escolaridade),
              situacao_escolar = VALUES(situacao_escolar),
              instituicao_ensino = VALUES(instituicao_ensino),
              serie_periodo = VALUES(serie_periodo),
              turno_estudo = VALUES(turno_estudo),
              local_atuacao = VALUES(local_atuacao),
              turno_atuacao = VALUES(turno_atuacao),
              updated_at = CURRENT_TIMESTAMP';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $upd = $pdo->prepare('UPDATE pe_candidatos SET
            escolaridade = COALESCE(:nivel, escolaridade),
            situacao_escolar = COALESCE(:situacao, situacao_escolar),
            instituicao_ensino = COALESCE(:instituicao, instituicao_ensino),
            turno_estudo = COALESCE(:turno, turno_estudo),
            status = CASE WHEN :local IS NOT NULL AND :local <> "" THEN "Contemplado" ELSE status END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id');
    $upd->execute([
        'nivel' => $params['nivel_escolaridade'],
        'situacao' => $params['situacao_escolar'],
        'instituicao' => $params['instituicao_ensino'],
        'turno' => $params['turno_estudo'],
        'local' => $params['local_atuacao'],
        'id' => $candidateId,
    ]);

    return $candidateId;
}

function pe_report_rows(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];
    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(c.nome LIKE :q_nome OR c.cpf LIKE :q_cpf OR c.cpf_informado LIKE :q_cpf_informado OR c.telefone LIKE :q_telefone OR c.bairro LIKE :q_bairro OR c.responsavel_familiar LIKE :q_responsavel OR f.local_atuacao LIKE :q_setor)';
        $params['q_nome'] = $like;
        $params['q_cpf'] = $like;
        $params['q_cpf_informado'] = $like;
        $params['q_telefone'] = $like;
        $params['q_bairro'] = $like;
        $params['q_responsavel'] = $like;
        $params['q_setor'] = $like;
    }
    if (!empty($filters['status'])) {
        $where[] = 'c.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['bairro'])) {
        $where[] = 'c.bairro = :bairro';
        $params['bairro'] = $filters['bairro'];
    }
    if (!empty($filters['setor'])) {
        $where[] = 'f.local_atuacao = :setor';
        $params['setor'] = $filters['setor'];
    }
    $sql = 'SELECT c.id, c.nome, c.data_nascimento, c.responsavel_familiar, c.bairro,
                   COALESCE(NULLIF(c.endereco, ""), TRIM(CONCAT(COALESCE(c.rua,""), " ", COALESCE(c.ponto_referencia,"")))) AS endereco,
                   c.telefone, COALESCE(c.cpf, c.cpf_informado) AS cpf, c.status, c.origem,
                   f.local_atuacao AS setor, f.turno_atuacao,
                   (SELECT v.decisao FROM pe_visitas_sociais v WHERE v.candidato_id = c.id ORDER BY v.data_visita DESC, v.id DESC LIMIT 1) AS parecer
            FROM pe_candidatos c
            LEFT JOIN pe_fichas_cadastrais f ON f.candidato_id = c.id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY c.nome ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pe_dashboard_stats(PDO $pdo): array
{
    $sql = 'SELECT
        (SELECT COUNT(*) FROM pe_candidatos) total,
        (SELECT COUNT(*) FROM pe_candidatos WHERE status = "Contemplado") contemplados,
        (SELECT COUNT(*) FROM pe_visitas_sociais) visitas,
        (SELECT COUNT(*) FROM pe_visitas_sociais WHERE decisao = "Deferido") deferidos,
        (SELECT COUNT(*) FROM pe_visitas_sociais WHERE decisao = "Indeferido") indeferidos,
        (SELECT COUNT(*) FROM pe_candidatos WHERE origem = "importacao") importados,
        (SELECT COUNT(*) FROM pe_candidatos WHERE revisao_status IS NOT NULL AND revisao_status <> "") revisao_pendente,
        (SELECT COUNT(*) FROM pe_candidatos WHERE revisao_status = "Revisar Cadastro") revisar_cadastro,
        (SELECT COUNT(*) FROM pe_candidatos WHERE cpf_duplicado = 1 AND cpf_duplicado_confirmado = 0) cpf_duplicado';
    return (array) $pdo->query($sql)->fetch();
}

function pe_candidate_filters(PDO $pdo): array
{
    $bairros = $pdo->query('SELECT DISTINCT bairro FROM pe_candidatos WHERE bairro IS NOT NULL AND bairro <> "" ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN);
    $setores = $pdo->query('SELECT DISTINCT local_atuacao FROM pe_fichas_cadastrais WHERE local_atuacao IS NOT NULL AND local_atuacao <> "" ORDER BY local_atuacao')->fetchAll(PDO::FETCH_COLUMN);
    return ['bairros' => $bairros, 'setores' => $setores];
}

function pe_candidate_page(PDO $pdo, array $filters, int $page = 1, int $perPage = 50): array
{
    $page = max(1, $page);
    $perPage = max(10, min($perPage, 100));
    $where = [];
    $params = [];

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(c.nome LIKE :q_nome OR c.cpf LIKE :q_cpf OR c.cpf_informado LIKE :q_cpf_informado OR c.telefone LIKE :q_telefone OR c.bairro LIKE :q_bairro OR c.responsavel_familiar LIKE :q_responsavel OR f.local_atuacao LIKE :q_setor)';
        $params['q_nome'] = $like;
        $params['q_cpf'] = $like;
        $params['q_cpf_informado'] = $like;
        $params['q_telefone'] = $like;
        $params['q_bairro'] = $like;
        $params['q_responsavel'] = $like;
        $params['q_setor'] = $like;
    }
    if (!empty($filters['status'])) {
        $where[] = 'c.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['bairro'])) {
        $where[] = 'c.bairro = :bairro';
        $params['bairro'] = $filters['bairro'];
    }
    if (!empty($filters['setor'])) {
        $where[] = 'f.local_atuacao = :setor';
        $params['setor'] = $filters['setor'];
    }
    if (!empty($filters['origem'])) {
        $where[] = 'c.origem = :origem';
        $params['origem'] = $filters['origem'];
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

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $countSql = 'SELECT COUNT(*) FROM pe_candidatos c LEFT JOIN pe_fichas_cadastrais f ON f.candidato_id=c.id' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT c.id, c.nome, c.data_nascimento, c.responsavel_familiar, c.bairro,
                   COALESCE(NULLIF(c.endereco, ""), TRIM(CONCAT(COALESCE(c.rua,""), " ", COALESCE(c.ponto_referencia,"")))) AS endereco,
                   c.telefone, c.cpf, c.cpf_informado, c.status, c.origem, c.updated_at,
                   c.revisao_status, c.revisao_cpf, c.revisao_telefone, c.revisao_nascimento,
                   c.cpf_duplicado, c.cpf_revisado_confirmado, c.telefone_revisado_confirmado, c.nascimento_revisado_confirmado,
                   c.cpf_duplicado_confirmado, c.revisao_motivos, c.revisao_atualizada_em,
                   f.local_atuacao AS setor,
                   (SELECT v.decisao FROM pe_visitas_sociais v WHERE v.candidato_id=c.id ORDER BY v.data_visita DESC, v.id DESC LIMIT 1) AS parecer
            FROM pe_candidatos c
            LEFT JOIN pe_fichas_cadastrais f ON f.candidato_id=c.id' . $whereSql . '
            ORDER BY
                CASE WHEN c.cpf_duplicado = 1 AND c.cpf_duplicado_confirmado = 0 THEN 0 WHEN c.revisao_status = "Revisar Cadastro" THEN 1 WHEN c.revisao_status IS NOT NULL THEN 2 ELSE 3 END,
                c.nome ASC
            LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ];
}

function pe_import_history(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min($limit, 50));
    $sql = 'SELECT id, arquivo_nome, total_linhas, importados, atualizados, bloqueados, avisos, pendentes_revisao, erros,
                   marcar_como_contemplados, responsavel, status, criado_em, finalizada_em
            FROM pe_importacoes ORDER BY id DESC LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}


function pe_candidate_duplicate_peers(PDO $pdo, int $candidateId, ?string $cpf): array
{
    if (!$cpf || !pe_validate_cpf($cpf)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, nome, data_nascimento, telefone, status FROM pe_candidatos WHERE cpf=:cpf AND id<>:id ORDER BY nome');
    $stmt->execute(['cpf' => $cpf, 'id' => $candidateId]);
    return $stmt->fetchAll();
}

function pe_recalculate_review(PDO $pdo, int $candidateId): array
{
    $row = pe_candidate_by_id($pdo, $candidateId);
    if (!$row) {
        throw new InvalidArgumentException('Candidato não encontrado.');
    }

    $cpf = pe_digits($row['cpf'] ?? '');
    $cpfValid = strlen($cpf) === 11 && pe_validate_cpf($cpf);
    $duplicates = $cpfValid ? pe_candidate_duplicate_peers($pdo, $candidateId, $cpf) : [];
    $hasDuplicate = $duplicates !== [];

    $reviewCpf = 0;
    $cpfReason = null;
    if ((!$cpfValid || $cpf === '') && empty($row['cpf_revisado_confirmado'])) {
        $reviewCpf = 1;
        $cpfReason = empty($row['cpf_informado']) ? 'CPF não informado' : 'CPF inconsistente';
    }
    if ($hasDuplicate && empty($row['cpf_duplicado_confirmado'])) {
        $reviewCpf = 1;
        $cpfReason = 'CPF duplicado com outro cadastro';
    }

    $phone = pe_digits($row['telefone'] ?? '');
    $phoneValid = in_array(strlen($phone), [10, 11], true);
    $reviewPhone = (!$phoneValid && empty($row['telefone_revisado_confirmado'])) ? 1 : 0;
    $phoneReason = null;
    if ($reviewPhone) {
        $phoneReason = $phone === '' ? 'Telefone não informado' : 'Telefone fora do padrão';
    }

    $birthValid = !empty($row['data_nascimento']) && pe_date_or_null($row['data_nascimento']) !== null;
    $reviewBirth = (!$birthValid && empty($row['nascimento_revisado_confirmado'])) ? 1 : 0;
    $birthReason = $reviewBirth ? 'Data de nascimento não informada' : null;

    $reviewStatus = pe_review_status_from_flags($reviewCpf, $reviewPhone, $reviewBirth);
    $reasons = array_values(array_filter([$cpfReason, $phoneReason, $birthReason]));

    $stmt = $pdo->prepare('UPDATE pe_candidatos SET
        revisao_status=:status,
        revisao_cpf=:cpf_review,
        revisao_telefone=:phone_review,
        revisao_nascimento=:birth_review,
        cpf_duplicado=:cpf_duplicate,
        revisao_motivos=:motivos,
        revisao_atualizada_em=:updated
        WHERE id=:id');
    $stmt->execute([
        'status' => $reviewStatus,
        'cpf_review' => $reviewCpf,
        'phone_review' => $reviewPhone,
        'birth_review' => $reviewBirth,
        'cpf_duplicate' => $hasDuplicate ? 1 : 0,
        'motivos' => $reasons ? implode(' | ', $reasons) : null,
        'updated' => $reviewStatus ? date('Y-m-d H:i:s') : null,
        'id' => $candidateId,
    ]);

    return pe_candidate_by_id($pdo, $candidateId) ?: $row;
}

function pe_review_candidate(PDO $pdo, int $candidateId, array $input, ?string $reviewer): array
{
    $current = pe_candidate_by_id($pdo, $candidateId);
    if (!$current) {
        throw new InvalidArgumentException('Candidato não encontrado.');
    }

    $newCpfRaw = trim((string) ($input['cpf'] ?? ''));
    $newPhoneRaw = trim((string) ($input['telefone'] ?? ''));
    $newBirthRaw = trim((string) ($input['data_nascimento'] ?? ''));

    $cpfInformed = pe_digits($newCpfRaw);
    $cpfCandidate = $cpfInformed;
    if ($cpfCandidate !== '' && strlen($cpfCandidate) < 11) {
        $cpfCandidate = str_pad($cpfCandidate, 11, '0', STR_PAD_LEFT);
    }
    $cpfValid = $cpfCandidate !== '' && pe_validate_cpf($cpfCandidate);
    $phone = pe_digits($newPhoneRaw);
    $birth = pe_date_or_null($newBirthRaw);

    $confirmCpf = !empty($input['confirmar_cpf_atual']);
    $confirmPhone = !empty($input['confirmar_telefone_atual']);
    $confirmBirth = !empty($input['confirmar_nascimento_atual']);
    $confirmDuplicate = !empty($input['confirmar_cpf_duplicado']);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE pe_candidatos SET
            cpf=:cpf,
            cpf_informado=:cpf_informado,
            telefone=:telefone,
            data_nascimento=:nascimento,
            cpf_revisado_confirmado=:cpf_confirmado,
            telefone_revisado_confirmado=:telefone_confirmado,
            nascimento_revisado_confirmado=:nascimento_confirmado,
            cpf_duplicado_confirmado=:duplicado_confirmado,
            revisao_revisado_por=:revisor,
            revisao_revisado_em=CURRENT_TIMESTAMP,
            updated_at=CURRENT_TIMESTAMP
            WHERE id=:id');
        $stmt->execute([
            'cpf' => $cpfValid ? $cpfCandidate : null,
            'cpf_informado' => $cpfInformed !== '' ? $cpfInformed : null,
            'telefone' => $phone !== '' ? $phone : null,
            'nascimento' => $birth,
            'cpf_confirmado' => $confirmCpf ? 1 : 0,
            'telefone_confirmado' => $confirmPhone ? 1 : 0,
            'nascimento_confirmado' => $confirmBirth ? 1 : 0,
            'duplicado_confirmado' => $confirmDuplicate ? 1 : 0,
            'revisor' => pe_nullable($reviewer),
            'id' => $candidateId,
        ]);

        $log = $pdo->prepare('INSERT INTO pe_revisoes_cadastrais
            (candidato_id, cpf_anterior, cpf_novo, telefone_anterior, telefone_novo, nascimento_anterior, nascimento_novo,
             confirmou_cpf, confirmou_telefone, confirmou_nascimento, confirmou_cpf_duplicado, observacao, revisado_por)
            VALUES (:id,:cpf_old,:cpf_new,:phone_old,:phone_new,:birth_old,:birth_new,:ccpf,:cphone,:cbirth,:cdup,:obs,:reviewer)');
        $log->execute([
            'id' => $candidateId,
            'cpf_old' => pe_nullable($current['cpf_informado'] ?: $current['cpf'] ?? null),
            'cpf_new' => $cpfInformed !== '' ? $cpfInformed : null,
            'phone_old' => pe_nullable($current['telefone'] ?? null),
            'phone_new' => $phone !== '' ? $phone : null,
            'birth_old' => $current['data_nascimento'] ?? null,
            'birth_new' => $birth,
            'ccpf' => $confirmCpf ? 1 : 0,
            'cphone' => $confirmPhone ? 1 : 0,
            'cbirth' => $confirmBirth ? 1 : 0,
            'cdup' => $confirmDuplicate ? 1 : 0,
            'obs' => pe_nullable($input['observacao'] ?? null),
            'reviewer' => pe_nullable($reviewer),
        ]);

        $updated = pe_recalculate_review($pdo, $candidateId);

        // Recalcula os candidatos que compartilham o CPF anterior ou o novo, para remover/adicionar alertas corretamente.
        $cpfs = array_unique(array_filter([pe_digits($current['cpf'] ?? ''), $cpfValid ? $cpfCandidate : '']));
        foreach ($cpfs as $cpf) {
            $ids = $pdo->prepare('SELECT id FROM pe_candidatos WHERE cpf=:cpf');
            $ids->execute(['cpf' => $cpf]);
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $peerId) {
                if ((int) $peerId !== $candidateId) {
                    pe_recalculate_review($pdo, (int) $peerId);
                }
            }
        }

        $pdo->commit();
        return $updated;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pe_review_history(PDO $pdo, int $candidateId, int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $stmt = $pdo->prepare('SELECT * FROM pe_revisoes_cadastrais WHERE candidato_id=:id ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute(['id' => $candidateId]);
    return $stmt->fetchAll();
}

function pe_dashboard_review_counts(PDO $pdo): array
{
    $rows = $pdo->query('SELECT COALESCE(revisao_status, "Sem pendência") status, COUNT(*) total FROM pe_candidatos GROUP BY COALESCE(revisao_status, "Sem pendência")')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[(string) $row['status']] = (int) $row['total'];
    }
    return $out;
}

function pe_dashboard_monthly(PDO $pdo, int $months = 6): array
{
    $months = max(3, min($months, 12));
    $start = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
    $stmt = $pdo->prepare('SELECT DATE_FORMAT(created_at, "%Y-%m") ym, COUNT(*) total FROM pe_candidatos WHERE created_at >= :start GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY ym');
    $stmt->execute(['start' => $start->format('Y-m-01 00:00:00')]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['ym']] = (int) $row['total'];
    }
    $labels = [];
    $values = [];
    $cursor = $start;
    $monthsPt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
    for ($i = 0; $i < $months; $i++) {
        $key = $cursor->format('Y-m');
        $labels[] = $monthsPt[(int) $cursor->format('n')] . '/' . $cursor->format('y');
        $values[] = $map[$key] ?? 0;
        $cursor = $cursor->modify('+1 month');
    }
    return ['labels' => $labels, 'values' => $values];
}

function pe_recent_activity(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min($limit, 30));
    $sql = '(SELECT created_at data_evento, "Candidato cadastrado" titulo, CONCAT(nome, " · ", origem) descricao FROM pe_candidatos)
            UNION ALL
            (SELECT criado_em data_evento, "Importação de candidatos" titulo, CONCAT(arquivo_nome, " · ", importados, " incluídos") descricao FROM pe_importacoes)
            UNION ALL
            (SELECT created_at data_evento, "Visita social registrada" titulo, CONCAT("Candidato #", candidato_id, " · ", decisao) descricao FROM pe_visitas_sociais)
            ORDER BY data_evento DESC LIMIT ' . $limit;
    return $pdo->query($sql)->fetchAll();
}

function pe_program_schema_ready(): bool
{
    try {
        $pdo = pe_db();
        foreach (['pe_parceiros','pe_vagas','pe_encaminhamentos','pe_documentos','pe_frequencias','pe_bolsas','pe_capacitacoes','pe_acompanhamentos_programa','pe_configuracoes'] as $table) {
            $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        }
        return true;
    } catch (Throwable) {
        return false;
    }
}

function pe_decimal(mixed $value): float
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') return 0.0;
    if (str_contains($raw, ',')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    return (float) $raw;
}

function pe_program_candidates(PDO $pdo, int $limit = 2000): array
{
    $limit = max(1, min($limit, 5000));
    return $pdo->query('SELECT id,nome,cpf,cpf_informado,status FROM pe_candidatos ORDER BY nome LIMIT ' . $limit)->fetchAll();
}

function pe_partners(PDO $pdo): array
{
    return $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM pe_vagas v WHERE v.parceiro_id=p.id) vagas, (SELECT COUNT(*) FROM pe_fichas_cadastrais f WHERE f.local_atuacao=p.nome) lotados FROM pe_parceiros p ORDER BY p.nome')->fetchAll();
}

function pe_save_partner(PDO $pdo, array $input): int
{
    $name = trim((string) ($input['nome'] ?? ''));
    if (mb_strlen($name) < 3) throw new InvalidArgumentException('Informe o nome da instituição parceira.');
    $cnpj = pe_digits($input['cnpj'] ?? '');
    if ($cnpj !== '' && strlen($cnpj) !== 14) throw new InvalidArgumentException('CNPJ deve possuir 14 dígitos ou ficar em branco.');
    $email = pe_nullable($input['email'] ?? null);
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail inválido.');
    $stmt = $pdo->prepare('INSERT INTO pe_parceiros (nome,tipo,cnpj,responsavel,telefone,email,termo_parceria,status,observacao) VALUES (:nome,:tipo,:cnpj,:responsavel,:telefone,:email,:termo,:status,:obs)');
    $stmt->execute([
        'nome'=>$name,'tipo'=>pe_nullable($input['tipo'] ?? null),'cnpj'=>$cnpj ?: null,
        'responsavel'=>pe_nullable($input['responsavel'] ?? null),'telefone'=>pe_nullable(pe_digits($input['telefone'] ?? '')),
        'email'=>$email,'termo'=>pe_nullable($input['termo_parceria'] ?? null),'status'=>pe_nullable($input['status'] ?? 'Ativa') ?: 'Ativa','obs'=>pe_nullable($input['observacao'] ?? null),
    ]);
    return (int) $pdo->lastInsertId();
}

function pe_vacancies(PDO $pdo): array
{
    return $pdo->query('SELECT v.*, p.nome parceiro FROM pe_vagas v LEFT JOIN pe_parceiros p ON p.id=v.parceiro_id ORDER BY CASE v.status WHEN "Aberta" THEN 0 WHEN "Em seleção" THEN 1 ELSE 2 END, v.id DESC')->fetchAll();
}

function pe_save_vacancy(PDO $pdo, array $input): int
{
    $cargo = trim((string) ($input['cargo'] ?? ''));
    if (mb_strlen($cargo) < 3) throw new InvalidArgumentException('Informe o cargo/oportunidade.');
    $partnerId = (int) ($input['parceiro_id'] ?? 0);
    $stmt = $pdo->prepare('INSERT INTO pe_vagas (parceiro_id,cargo,setor,quantidade,requisitos,escolaridade,carga_horaria,remuneracao,prazo,status,observacao) VALUES (:parceiro,:cargo,:setor,:qtd,:req,:esc,:carga,:rem,:prazo,:status,:obs)');
    $stmt->execute([
        'parceiro'=>$partnerId > 0 ? $partnerId : null,'cargo'=>$cargo,'setor'=>pe_nullable($input['setor'] ?? null),'qtd'=>max(1,(int)($input['quantidade'] ?? 1)),
        'req'=>pe_nullable($input['requisitos'] ?? null),'esc'=>pe_nullable($input['escolaridade'] ?? null),'carga'=>pe_nullable($input['carga_horaria'] ?? null),
        'rem'=>($input['remuneracao'] ?? '') !== '' ? pe_decimal($input['remuneracao']) : null,'prazo'=>pe_date_or_null($input['prazo'] ?? ''),'status'=>pe_nullable($input['status'] ?? 'Aberta') ?: 'Aberta','obs'=>pe_nullable($input['observacao'] ?? null),
    ]);
    return (int)$pdo->lastInsertId();
}

function pe_referrals(PDO $pdo): array
{
    return $pdo->query('SELECT e.*, c.nome candidato, v.cargo vaga, p.nome parceiro FROM pe_encaminhamentos e JOIN pe_candidatos c ON c.id=e.candidato_id LEFT JOIN pe_vagas v ON v.id=e.vaga_id LEFT JOIN pe_parceiros p ON p.id=e.parceiro_id ORDER BY e.data_encaminhamento DESC,e.id DESC')->fetchAll();
}

function pe_save_referral(PDO $pdo, array $input, ?string $responsavel): int
{
    $candidateId=(int)($input['candidato_id']??0);
    if ($candidateId<=0 || !pe_candidate_by_id($pdo,$candidateId)) throw new InvalidArgumentException('Selecione um candidato.');
    $vagaId=(int)($input['vaga_id']??0);
    $partnerId=(int)($input['parceiro_id']??0);
    if ($vagaId>0 && $partnerId<=0) {
        $st=$pdo->prepare('SELECT parceiro_id FROM pe_vagas WHERE id=:id');$st->execute(['id'=>$vagaId]);$partnerId=(int)$st->fetchColumn();
    }
    $stmt=$pdo->prepare('INSERT INTO pe_encaminhamentos (candidato_id,vaga_id,parceiro_id,data_encaminhamento,responsavel,retorno,status,data_retorno) VALUES (:cand,:vaga,:parceiro,:data,:resp,:retorno,:status,:data_ret)');
    $stmt->execute(['cand'=>$candidateId,'vaga'=>$vagaId?:null,'parceiro'=>$partnerId?:null,'data'=>pe_date_or_null($input['data_encaminhamento']??'')?:date('Y-m-d'),'resp'=>pe_nullable($responsavel),'retorno'=>pe_nullable($input['retorno']??null),'status'=>pe_nullable($input['status']??'Pendente')?:'Pendente','data_ret'=>pe_date_or_null($input['data_retorno']??'')]);
    $pdo->prepare('UPDATE pe_candidatos SET status="Encaminhado",updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$candidateId]);
    return (int)$pdo->lastInsertId();
}

function pe_documents(PDO $pdo): array
{
    return $pdo->query('SELECT d.*,c.nome candidato FROM pe_documentos d JOIN pe_candidatos c ON c.id=d.candidato_id ORDER BY d.id DESC')->fetchAll();
}

function pe_save_document(PDO $pdo, array $input, array $files, ?string $responsavel): int
{
    $candidateId=(int)($input['candidato_id']??0);
    if ($candidateId<=0 || !pe_candidate_by_id($pdo,$candidateId)) throw new InvalidArgumentException('Selecione um candidato.');
    $type=trim((string)($input['tipo']??''));
    if ($type==='') throw new InvalidArgumentException('Informe o tipo de documento.');
    $path=$original=$mime=null;$size=null;
    if (!empty($files['arquivo']['tmp_name']) && is_uploaded_file($files['arquivo']['tmp_name'])) {
        $size=(int)($files['arquivo']['size']??0);
        if ($size<=0 || $size>10*1024*1024) throw new InvalidArgumentException('Documento deve ter até 10 MB.');
        $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=$finfo?finfo_file($finfo,$files['arquivo']['tmp_name']):'';if($finfo)finfo_close($finfo);
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$mime])) throw new InvalidArgumentException('Envie PDF, JPG, PNG ou WEBP.');
        $rel='primeiro-emprego/documentos/'.\App\Core\Storage::buildRelativeDirectory();
        $dir=\App\Core\Storage::ensureDocumentDirectory($rel);
        $filename=\App\Core\Storage::generateRandomFilename($allowed[$mime]);
        if(!move_uploaded_file($files['arquivo']['tmp_name'],$dir.DIRECTORY_SEPARATOR.$filename)) throw new RuntimeException('Não foi possível salvar o documento.');
        $path=$rel.'/'.$filename;$original=mb_substr(basename((string)($files['arquivo']['name']??'documento')),0,255);
    }
    $stmt=$pdo->prepare('INSERT INTO pe_documentos (candidato_id,tipo,status,validade,observacao,arquivo_path,nome_original,mime_type,size_bytes,registrado_por) VALUES (:cand,:tipo,:status,:validade,:obs,:path,:original,:mime,:size,:resp)');
    $stmt->execute(['cand'=>$candidateId,'tipo'=>$type,'status'=>pe_nullable($input['status']??'Pendente')?:'Pendente','validade'=>pe_date_or_null($input['validade']??''),'obs'=>pe_nullable($input['observacao']??null),'path'=>$path,'original'=>$original,'mime'=>$mime,'size'=>$size,'resp'=>pe_nullable($responsavel)]);
    return (int)$pdo->lastInsertId();
}

function pe_attendance_rows(PDO $pdo): array
{
    return $pdo->query('SELECT f.*,c.nome candidato,fc.local_atuacao parceiro FROM pe_frequencias f JOIN pe_candidatos c ON c.id=f.candidato_id LEFT JOIN pe_fichas_cadastrais fc ON fc.candidato_id=c.id ORDER BY f.competencia DESC,c.nome')->fetchAll();
}

function pe_save_attendance(PDO $pdo,array $input,?string $responsavel): int
{
    $candidateId=(int)($input['candidato_id']??0);if($candidateId<=0||!pe_candidate_by_id($pdo,$candidateId))throw new InvalidArgumentException('Selecione um candidato.');
    $comp=trim((string)($input['competencia']??''));if(!preg_match('/^\d{4}-\d{2}$/',$comp))throw new InvalidArgumentException('Competência inválida.');
    $prev=max(0,(int)($input['dias_previstos']??0));$pres=max(0,(int)($input['presencas']??0));$falt=max(0,(int)($input['faltas']??max(0,$prev-$pres)));
    $pct=$prev>0?round(min(100,($pres/$prev)*100),2):0.0;$min=(float)pe_config_value($pdo,'frequencia_minima','75.00');$status=$pct<$min?'Atenção':'Regular';
    $stmt=$pdo->prepare('INSERT INTO pe_frequencias (candidato_id,competencia,dias_previstos,presencas,faltas,percentual,status,observacao,registrado_por) VALUES (:cand,:comp,:prev,:pres,:falt,:pct,:status,:obs,:resp) ON DUPLICATE KEY UPDATE dias_previstos=VALUES(dias_previstos),presencas=VALUES(presencas),faltas=VALUES(faltas),percentual=VALUES(percentual),status=VALUES(status),observacao=VALUES(observacao),registrado_por=VALUES(registrado_por),updated_at=CURRENT_TIMESTAMP');
    $stmt->execute(['cand'=>$candidateId,'comp'=>$comp,'prev'=>$prev,'pres'=>$pres,'falt'=>$falt,'pct'=>$pct,'status'=>$status,'obs'=>pe_nullable($input['observacao']??null),'resp'=>pe_nullable($responsavel)]);return $candidateId;
}

function pe_grant_rows(PDO $pdo): array
{
    return $pdo->query('SELECT b.*,c.nome candidato,f.percentual frequencia FROM pe_bolsas b JOIN pe_candidatos c ON c.id=b.candidato_id LEFT JOIN pe_frequencias f ON f.candidato_id=b.candidato_id AND f.competencia=b.competencia ORDER BY b.competencia DESC,c.nome')->fetchAll();
}

function pe_save_grant(PDO $pdo,array $input,?string $responsavel): int
{
    $candidateId=(int)($input['candidato_id']??0);if($candidateId<=0||!pe_candidate_by_id($pdo,$candidateId))throw new InvalidArgumentException('Selecione um candidato.');
    $comp=trim((string)($input['competencia']??''));if(!preg_match('/^\d{4}-\d{2}$/',$comp))throw new InvalidArgumentException('Competência inválida.');
    $valor=pe_decimal($input['valor']??pe_config_value($pdo,'bolsa_valor_padrao','800.00'));if($valor<0)throw new InvalidArgumentException('Valor inválido.');
    $stmt=$pdo->prepare('INSERT INTO pe_bolsas (candidato_id,competencia,valor,status,data_pagamento,observacao,registrado_por) VALUES (:cand,:comp,:valor,:status,:data,:obs,:resp) ON DUPLICATE KEY UPDATE valor=VALUES(valor),status=VALUES(status),data_pagamento=VALUES(data_pagamento),observacao=VALUES(observacao),registrado_por=VALUES(registrado_por),updated_at=CURRENT_TIMESTAMP');
    $stmt->execute(['cand'=>$candidateId,'comp'=>$comp,'valor'=>$valor,'status'=>pe_nullable($input['status']??'Em análise')?:'Em análise','data'=>pe_date_or_null($input['data_pagamento']??''),'obs'=>pe_nullable($input['observacao']??null),'resp'=>pe_nullable($responsavel)]);return $candidateId;
}

function pe_trainings(PDO $pdo): array
{
    return $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM pe_capacitacao_participantes cp WHERE cp.capacitacao_id=c.id) inscritos,(SELECT COUNT(*) FROM pe_capacitacao_participantes cp WHERE cp.capacitacao_id=c.id AND cp.status="Concluído") concluintes FROM pe_capacitacoes c ORDER BY COALESCE(c.data_inicio,"9999-12-31"),c.id DESC')->fetchAll();
}

function pe_save_training(PDO $pdo,array $input): int
{
    $course=trim((string)($input['curso']??''));if(mb_strlen($course)<3)throw new InvalidArgumentException('Informe o nome da capacitação.');
    $stmt=$pdo->prepare('INSERT INTO pe_capacitacoes (curso,instituicao,turma,carga_horaria,data_inicio,data_fim,vagas,certificado,status,observacao) VALUES (:curso,:inst,:turma,:carga,:inicio,:fim,:vagas,:cert,:status,:obs)');
    $stmt->execute(['curso'=>$course,'inst'=>pe_nullable($input['instituicao']??null),'turma'=>pe_nullable($input['turma']??null),'carga'=>($input['carga_horaria']??'')===''?null:max(0,(int)$input['carga_horaria']),'inicio'=>pe_date_or_null($input['data_inicio']??''),'fim'=>pe_date_or_null($input['data_fim']??''),'vagas'=>($input['vagas']??'')===''?null:max(0,(int)$input['vagas']),'cert'=>pe_nullable($input['certificado']??'Previsto')?:'Previsto','status'=>pe_nullable($input['status']??'Planejada')?:'Planejada','obs'=>pe_nullable($input['observacao']??null)]);return (int)$pdo->lastInsertId();
}

function pe_enroll_training(PDO $pdo,array $input): void
{
    $trainingId=(int)($input['capacitacao_id']??0);$candidateId=(int)($input['candidato_id']??0);if($trainingId<=0||$candidateId<=0)throw new InvalidArgumentException('Selecione capacitação e candidato.');
    $stmt=$pdo->prepare('INSERT INTO pe_capacitacao_participantes (capacitacao_id,candidato_id,status) VALUES (:cap,:cand,"Inscrito") ON DUPLICATE KEY UPDATE status="Inscrito",updated_at=CURRENT_TIMESTAMP');$stmt->execute(['cap'=>$trainingId,'cand'=>$candidateId]);
}

function pe_followup_rows(PDO $pdo): array
{
    return $pdo->query('SELECT a.*,c.nome candidato,f.local_atuacao parceiro FROM pe_acompanhamentos_programa a JOIN pe_candidatos c ON c.id=a.candidato_id LEFT JOIN pe_fichas_cadastrais f ON f.candidato_id=c.id ORDER BY a.data_acompanhamento DESC,a.id DESC')->fetchAll();
}

function pe_save_followup(PDO $pdo,array $input,?string $responsavel): int
{
    $candidateId=(int)($input['candidato_id']??0);if($candidateId<=0||!pe_candidate_by_id($pdo,$candidateId))throw new InvalidArgumentException('Selecione um candidato.');$summary=trim((string)($input['resumo']??''));if($summary==='')throw new InvalidArgumentException('Informe o resumo do acompanhamento.');
    $stmt=$pdo->prepare('INSERT INTO pe_acompanhamentos_programa (candidato_id,data_acompanhamento,tipo,resumo,proxima_acao,data_proxima_acao,status,responsavel) VALUES (:cand,:data,:tipo,:resumo,:acao,:data_acao,:status,:resp)');$stmt->execute(['cand'=>$candidateId,'data'=>pe_date_or_null($input['data_acompanhamento']??'')?:date('Y-m-d'),'tipo'=>pe_nullable($input['tipo']??'Acompanhamento')?:'Acompanhamento','resumo'=>$summary,'acao'=>pe_nullable($input['proxima_acao']??null),'data_acao'=>pe_date_or_null($input['data_proxima_acao']??''),'status'=>pe_nullable($input['status']??'Regular')?:'Regular','resp'=>pe_nullable($responsavel)]);return (int)$pdo->lastInsertId();
}

function pe_config_value(PDO $pdo,string $key,string $default=''): string
{
    $stmt=$pdo->prepare('SELECT valor FROM pe_configuracoes WHERE chave=:key');$stmt->execute(['key'=>$key]);$v=$stmt->fetchColumn();return $v===false?$default:(string)$v;
}

function pe_save_config(PDO $pdo,string $key,string $value,?string $description=null): void
{
    $stmt=$pdo->prepare('INSERT INTO pe_configuracoes (chave,valor,descricao) VALUES (:chave,:valor,:descricao) ON DUPLICATE KEY UPDATE valor=VALUES(valor),descricao=COALESCE(VALUES(descricao),descricao),updated_at=CURRENT_TIMESTAMP');$stmt->execute(['chave'=>$key,'valor'=>$value,'descricao'=>$description]);
}