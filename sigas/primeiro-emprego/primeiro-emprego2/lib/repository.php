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
        $dir = dirname(__DIR__) . '/storage/fotos';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível preparar o diretório de fotos.');
        }
        $filename = 'cand_' . $candidateId . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($files['foto']['tmp_name'], $dir . '/' . $filename)) {
            throw new RuntimeException('Não foi possível salvar a foto.');
        }
        $photoPath = 'storage/fotos/' . $filename;
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
        $where[] = '(c.nome LIKE :q OR c.cpf LIKE :q OR c.cpf_informado LIKE :q OR c.telefone LIKE :q OR c.bairro LIKE :q OR c.responsavel_familiar LIKE :q OR f.local_atuacao LIKE :q)';
        $params['q'] = '%' . $search . '%';
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
        (SELECT COUNT(*) FROM pe_candidatos WHERE cpf_duplicado = 1) cpf_duplicado';
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
        $where[] = '(c.nome LIKE :q OR c.cpf LIKE :q OR c.cpf_informado LIKE :q OR c.telefone LIKE :q OR c.bairro LIKE :q OR c.responsavel_familiar LIKE :q OR f.local_atuacao LIKE :q)';
        $params['q'] = '%' . $search . '%';
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
        $where[] = 'c.cpf_duplicado = 1';
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
                   c.cpf_duplicado, c.revisao_motivos, c.revisao_atualizada_em,
                   f.local_atuacao AS setor,
                   (SELECT v.decisao FROM pe_visitas_sociais v WHERE v.candidato_id=c.id ORDER BY v.data_visita DESC, v.id DESC LIMIT 1) AS parecer
            FROM pe_candidatos c
            LEFT JOIN pe_fichas_cadastrais f ON f.candidato_id=c.id' . $whereSql . '
            ORDER BY
                CASE WHEN c.cpf_duplicado = 1 THEN 0 WHEN c.revisao_status = "Revisar Cadastro" THEN 1 WHEN c.revisao_status IS NOT NULL THEN 2 ELSE 3 END,
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

