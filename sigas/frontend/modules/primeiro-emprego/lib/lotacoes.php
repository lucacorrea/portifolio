<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';

/**
 * Repositório específico de lotações.
 *
 * pe_lotacoes é a fonte oficial do histórico de lotações.
 * pe_fichas_cadastrais.local_atuacao/turno_atuacao são mantidos apenas
 * como compatibilidade temporária com telas antigas.
 */

function pe_lotacao_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'pe_lotacoes'");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function pe_lotacao_rows(PDO $pdo): array
{
    $partnerSigla = pe_partner_has_sigla($pdo)
        ? 'p.sigla AS parceiro_sigla,'
        : 'NULL AS parceiro_sigla,';

    $sql = '
        SELECT
            l.id,
            c.id AS candidato_id,
            l.parceiro_id,
            c.nome,
            c.cpf,
            c.cpf_informado,
            c.telefone,
            c.bairro,
            c.status AS candidato_status,
            c.revisao_status,
            c.cpf_duplicado,
            i.setor_informado,
            p.nome AS parceiro_nome,
            ' . $partnerSigla . '
            l.local_atuacao,
            l.setor,
            l.turno_atuacao,
            l.data_inicio,
            l.data_fim,
            l.status,
            l.origem,
            l.observacao,
            l.registrado_por,
            l.created_at,
            l.updated_at,
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
            ) ult
                ON ult.ultimo_id = i1.id
        ) i
            ON i.candidato_id = c.id
        ORDER BY
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
                    OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NÃO VEIO DA SARA%"
                    OR UPPER(TRIM(COALESCE(l.local_atuacao, ""))) LIKE "%NAO VEIO DA SARA%"
                ) THEN 1
                WHEN l.id IS NULL AND i.setor_informado IS NULL THEN 2
                WHEN l.id IS NULL THEN 3
                ELSE 4
            END,
            c.nome ASC
    ';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_lotacao_candidates(PDO $pdo): array
{
    $sql = '
        SELECT
            c.id,
            c.nome,
            c.cpf,
            c.cpf_informado,
            c.bairro,
            c.status,
            la.id AS lotacao_ativa_id,
            la.local_atuacao AS lotacao_ativa_local
        FROM pe_candidatos c
        LEFT JOIN pe_lotacoes la
            ON la.candidato_id = c.id
           AND la.status = "Ativa"
        ORDER BY c.nome ASC
    ';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_lotacao_partners(PDO $pdo): array
{
    $sigla = pe_partner_has_sigla($pdo) ? 'sigla,' : 'NULL AS sigla,';
    $sql = '
        SELECT id, nome, ' . $sigla . ' tipo, status
        FROM pe_parceiros
        ORDER BY
            CASE WHEN status = "Ativa" THEN 0 ELSE 1 END,
            nome ASC
    ';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_lotacao_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT
            l.*,
            c.nome,
            c.cpf,
            c.cpf_informado,
            c.telefone,
            c.bairro,
            p.nome AS parceiro_nome
        FROM pe_lotacoes l
        INNER JOIN pe_candidatos c
            ON c.id = l.candidato_id
        LEFT JOIN pe_parceiros p
            ON p.id = l.parceiro_id
        WHERE l.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pe_lotacao_candidate_history(PDO $pdo, int $candidateId): array
{
    if ($candidateId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('
        SELECT
            l.id,
            l.local_atuacao,
            l.setor,
            l.turno_atuacao,
            l.data_inicio,
            l.data_fim,
            l.status,
            l.origem,
            l.observacao,
            l.registrado_por,
            p.nome AS parceiro_nome
        FROM pe_lotacoes l
        LEFT JOIN pe_parceiros p
            ON p.id = l.parceiro_id
        WHERE l.candidato_id = :candidate_id
        ORDER BY l.data_inicio DESC, l.id DESC
    ');
    $stmt->execute(['candidate_id' => $candidateId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_lotacao_validate_partner(PDO $pdo, int $partnerId): ?int
{
    if ($partnerId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM pe_parceiros WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $partnerId]);

    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Órgão ou instituição parceira inválida.');
    }

    return $partnerId;
}

function pe_lotacao_sync_legacy_profile(PDO $pdo, int $candidateId): void
{
    $stmt = $pdo->prepare('
        SELECT local_atuacao, turno_atuacao
        FROM pe_lotacoes
        WHERE candidato_id = :candidate_id
          AND status = "Ativa"
        ORDER BY data_inicio DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute(['candidate_id' => $candidateId]);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active) {
        $sync = $pdo->prepare('
            INSERT INTO pe_fichas_cadastrais
                (candidato_id, local_atuacao, turno_atuacao)
            VALUES
                (:candidate_id, :local, :shift)
            ON DUPLICATE KEY UPDATE
                local_atuacao = VALUES(local_atuacao),
                turno_atuacao = VALUES(turno_atuacao),
                updated_at = CURRENT_TIMESTAMP
        ');
        $sync->execute([
            'candidate_id' => $candidateId,
            'local' => $active['local_atuacao'],
            'shift' => $active['turno_atuacao'],
        ]);

        $status = $pdo->prepare('
            UPDATE pe_candidatos
            SET status = "Contemplado",
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $status->execute(['id' => $candidateId]);

        return;
    }

    $clear = $pdo->prepare('
        UPDATE pe_fichas_cadastrais
        SET local_atuacao = NULL,
            turno_atuacao = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE candidato_id = :candidate_id
    ');
    $clear->execute(['candidate_id' => $candidateId]);
}

function pe_lotacao_save(PDO $pdo, array $input): int
{
    $id = (int) ($input['id'] ?? 0);
    $candidateId = (int) ($input['candidato_id'] ?? 0);
    $partnerId = pe_lotacao_validate_partner($pdo, (int) ($input['parceiro_id'] ?? 0));
    $local = trim((string) ($input['local_atuacao'] ?? ''));
    $sector = pe_nullable($input['setor'] ?? null);
    $shift = pe_nullable($input['turno_atuacao'] ?? null);
    $startDate = pe_date_or_null($input['data_inicio'] ?? '') ?: date('Y-m-d');
    $endDate = pe_date_or_null($input['data_fim'] ?? '');
    $status = trim((string) ($input['status'] ?? 'Ativa'));
    $origin = pe_nullable($input['origem'] ?? 'manual') ?: 'manual';
    $note = pe_nullable($input['observacao'] ?? null);
    $registeredBy = pe_nullable($input['registrado_por'] ?? pe_current_user_label());

    $candidate = pe_candidate_by_id($pdo, $candidateId);
    if (!$candidate) {
        throw new InvalidArgumentException('Selecione um candidato válido.');
    }

    if ($local === '') {
        throw new InvalidArgumentException('Informe o local de atuação.');
    }

    if (!in_array($status, ['Ativa', 'Encerrada'], true)) {
        throw new InvalidArgumentException('Status de lotação inválido.');
    }

    if ($status === 'Ativa') {
        $endDate = null;
    } elseif ($endDate === null) {
        $endDate = date('Y-m-d');
    }

    if ($endDate !== null && $endDate < $startDate) {
        throw new InvalidArgumentException('A data final não pode ser anterior à data de início.');
    }

    $pdo->beginTransaction();

    try {
        $oldCandidateId = null;

        if ($id > 0) {
            $existing = pe_lotacao_by_id($pdo, $id);
            if (!$existing) {
                throw new InvalidArgumentException('Lotação não encontrada.');
            }
            $oldCandidateId = (int) $existing['candidato_id'];

            if ($status === 'Ativa') {
                $other = $pdo->prepare('
                    SELECT id, data_inicio
                    FROM pe_lotacoes
                    WHERE candidato_id = :candidate_id
                      AND status = "Ativa"
                      AND id <> :id
                    LIMIT 1
                ');
                $other->execute([
                    'candidate_id' => $candidateId,
                    'id' => $id,
                ]);
                if ($other->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException(
                        'Este candidato já possui outra lotação ativa. Encerre a lotação atual ou crie uma transferência.'
                    );
                }
            }

            $stmt = $pdo->prepare('
                UPDATE pe_lotacoes
                SET
                    candidato_id = :candidate_id,
                    parceiro_id = :partner_id,
                    local_atuacao = :local,
                    setor = :sector,
                    turno_atuacao = :shift,
                    data_inicio = :start_date,
                    data_fim = :end_date,
                    status = :status,
                    origem = :origin,
                    observacao = :note,
                    registrado_por = :registered_by,
                    candidato_ativo_id = :active_candidate_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmt->execute([
                'candidate_id' => $candidateId,
                'partner_id' => $partnerId,
                'local' => $local,
                'sector' => $sector,
                'shift' => $shift,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'origin' => $origin,
                'note' => $note,
                'registered_by' => $registeredBy,
                'active_candidate_id' => $status === 'Ativa' ? $candidateId : null,
                'id' => $id,
            ]);

            pe_lotacao_sync_legacy_profile($pdo, $candidateId);
            if ($oldCandidateId !== null && $oldCandidateId !== $candidateId) {
                pe_lotacao_sync_legacy_profile($pdo, $oldCandidateId);
            }

            $pdo->commit();
            return $id;
        }

        if ($status === 'Ativa') {
            $activeStmt = $pdo->prepare('
                SELECT id, data_inicio
                FROM pe_lotacoes
                WHERE candidato_id = :candidate_id
                  AND status = "Ativa"
                ORDER BY data_inicio DESC, id DESC
                LIMIT 1
            ');
            $activeStmt->execute(['candidate_id' => $candidateId]);
            $active = $activeStmt->fetch(PDO::FETCH_ASSOC);

            if ($active) {
                $activeStart = (string) $active['data_inicio'];
                if ($startDate < $activeStart) {
                    throw new InvalidArgumentException(
                        'A nova lotação não pode iniciar antes da lotação ativa atual.'
                    );
                }

                $closeDate = $startDate > $activeStart
                    ? date('Y-m-d', strtotime($startDate . ' -1 day'))
                    : $startDate;

                $close = $pdo->prepare('
                    UPDATE pe_lotacoes
                    SET
                        status = "Encerrada",
                        data_fim = :end_date,
                        candidato_ativo_id = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $close->execute([
                    'end_date' => $closeDate,
                    'id' => (int) $active['id'],
                ]);
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO pe_lotacoes
            (
                candidato_id,
                parceiro_id,
                local_atuacao,
                setor,
                turno_atuacao,
                data_inicio,
                data_fim,
                status,
                origem,
                observacao,
                registrado_por,
                candidato_ativo_id
            )
            VALUES
            (
                :candidate_id,
                :partner_id,
                :local,
                :sector,
                :shift,
                :start_date,
                :end_date,
                :status,
                :origin,
                :note,
                :registered_by,
                :active_candidate_id
            )
        ');
        $stmt->execute([
            'candidate_id' => $candidateId,
            'partner_id' => $partnerId,
            'local' => $local,
            'sector' => $sector,
            'shift' => $shift,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'origin' => $origin,
            'note' => $note,
            'registered_by' => $registeredBy,
            'active_candidate_id' => $status === 'Ativa' ? $candidateId : null,
        ]);

        $placementId = (int) $pdo->lastInsertId();
        pe_lotacao_sync_legacy_profile($pdo, $candidateId);

        $pdo->commit();
        return $placementId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pe_lotacao_end(PDO $pdo, int $id, ?string $endDate = null): void
{
    $placement = pe_lotacao_by_id($pdo, $id);
    if (!$placement) {
        throw new InvalidArgumentException('Lotação não encontrada.');
    }

    if ((string) $placement['status'] === 'Encerrada') {
        return;
    }

    $finalDate = pe_date_or_null($endDate ?? '') ?: date('Y-m-d');
    $startDate = (string) $placement['data_inicio'];

    if ($finalDate < $startDate) {
        throw new InvalidArgumentException('A data de encerramento não pode ser anterior ao início da lotação.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            UPDATE pe_lotacoes
            SET
                status = "Encerrada",
                data_fim = :end_date,
                candidato_ativo_id = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute([
            'end_date' => $finalDate,
            'id' => $id,
        ]);

        pe_lotacao_sync_legacy_profile($pdo, (int) $placement['candidato_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
