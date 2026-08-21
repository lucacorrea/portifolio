<?php

declare(strict_types=1);

require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/spreadsheet.php';

function pe_waitlist_schema_ready(?PDO $pdo = null): bool
{
    try {
        $pdo ??= pe_db();
        $pdo->query('SELECT tipo_importacao FROM pe_importacoes LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function pe_waitlist_normalize_name(string $name): string
{
    $name = trim($name);
    $name = strtr($name, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        'á' => 'A', 'à' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A',
        'é' => 'E', 'è' => 'E', 'ê' => 'E', 'ë' => 'E',
        'í' => 'I', 'ì' => 'I', 'î' => 'I', 'ï' => 'I',
        'ó' => 'O', 'ò' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O',
        'ú' => 'U', 'ù' => 'U', 'û' => 'U', 'ü' => 'U', 'ç' => 'C',
    ]);
    $name = function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
    $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name) ?: '';
    return trim(preg_replace('/\s+/', ' ', $name) ?: '');
}

function pe_waitlist_name_tokens(string $name): array
{
    $ignore = ['DA', 'DE', 'DO', 'DAS', 'DOS', 'E'];
    $tokens = array_values(array_filter(
        explode(' ', pe_waitlist_normalize_name($name)),
        static fn(string $token): bool => $token !== '' && !in_array($token, $ignore, true)
    ));
    return array_values(array_unique($tokens));
}

function pe_waitlist_names_compatible(string $incoming, string $existing): bool
{
    $a = pe_waitlist_normalize_name($incoming);
    $b = pe_waitlist_normalize_name($existing);
    if ($a === '' || $b === '') {
        return true;
    }
    if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
        return true;
    }

    $ta = pe_waitlist_name_tokens($a);
    $tb = pe_waitlist_name_tokens($b);
    if (!$ta || !$tb || $ta[0] !== $tb[0]) {
        return false;
    }

    $intersection = count(array_intersect($ta, $tb));
    $base = max(1, min(count($ta), count($tb)));
    return ($intersection / $base) >= 0.60;
}

function pe_waitlist_identity_signature(array $row): string
{
    return pe_waitlist_normalize_name((string) ($row['nome'] ?? '')) . '|' . (string) ($row['data_nascimento'] ?? '');
}

function pe_waitlist_candidates_by_cpfs(PDO $pdo, array $cpfs): array
{
    $cpfs = array_values(array_unique(array_filter(array_map('strval', $cpfs))));
    if (!$cpfs) {
        return [];
    }

    $map = [];
    foreach (array_chunk($cpfs, 450) as $chunk) {
        $placeholders = [];
        $params = [];
        foreach ($chunk as $index => $cpf) {
            $key = 'cpf_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cpf;
        }
        $stmt = $pdo->prepare(
            'SELECT id, nome, cpf, cpf_informado, data_nascimento, telefone, bairro, endereco, responsavel_familiar, status
             FROM pe_candidatos
             WHERE cpf IN (' . implode(',', $placeholders) . ')
             ORDER BY id'
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $candidate) {
            $cpf = (string) ($candidate['cpf'] ?? '');
            if ($cpf === '') {
                continue;
            }
            $map[$cpf] ??= [];
            $map[$cpf][] = $candidate;
        }
    }
    return $map;
}

function pe_waitlist_analyze(PDO $pdo, array $prepared): array
{
    $cpfs = [];
    $groups = [];
    foreach ($prepared as $index => $row) {
        $cpf = (string) ($row['cpf'] ?? '');
        if ($cpf !== '') {
            $cpfs[] = $cpf;
            $groups[$cpf] ??= [];
            $groups[$cpf][] = $index;
        }
    }

    $dbByCpf = pe_waitlist_candidates_by_cpfs($pdo, $cpfs);
    $groupMeta = [];
    foreach ($groups as $cpf => $indices) {
        $signatures = [];
        foreach ($indices as $index) {
            $signatures[pe_waitlist_identity_signature($prepared[$index])] = true;
        }
        $groupMeta[$cpf] = [
            'indices' => $indices,
            'conflicting_identity' => count($signatures) > 1,
            'first_index' => $indices[0],
        ];
    }

    $summary = [
        'total' => count($prepared),
        'novos' => 0,
        'novos_revisao' => 0,
        'atualizar' => 0,
        'ja_contemplados' => 0,
        'duplicados_ignorados' => 0,
        'conflitos_cpf_lista' => 0,
        'cpf_ambiguos' => 0,
        'divergencias_nome' => 0,
        'pendentes_revisao' => 0,
        'bloqueados' => 0,
        'prontos' => 0,
    ];
    $rows = [];

    foreach ($prepared as $index => $row) {
        $cpf = (string) ($row['cpf'] ?? '');
        $status = 'novo';
        $message = 'Novo candidato será incluído na Lista de espera.';
        $candidate = null;
        $blocked = false;

        if (!empty($row['revisao_status'])) {
            $summary['pendentes_revisao']++;
        }

        if ($cpf === '') {
            $status = 'novo_revisao';
            $message = 'Será incluído na Lista de espera e permanecerá com pendência cadastral para revisão.';
        } else {
            $meta = $groupMeta[$cpf] ?? ['indices' => [$index], 'conflicting_identity' => false, 'first_index' => $index];
            if ($meta['conflicting_identity']) {
                $status = 'conflito_cpf_lista';
                $message = 'O mesmo CPF aparece na planilha ligado a pessoas diferentes. Nenhuma atualização automática será feita.';
                $blocked = true;
            } elseif (count($meta['indices']) > 1 && $index !== $meta['first_index']) {
                $status = 'duplicado_lista';
                $message = 'Linha repetida da mesma pessoa na própria lista. Será ignorada para evitar processamento duplicado.';
            } else {
                $matches = $dbByCpf[$cpf] ?? [];
                if (count($matches) > 1) {
                    $status = 'cpf_ambiguo';
                    $message = 'Há mais de um cadastro no SIGAS com este CPF. Resolva a duplicidade antes de importar esta linha.';
                    $blocked = true;
                } elseif (count($matches) === 1) {
                    $candidate = $matches[0];
                    if (!pe_waitlist_names_compatible((string) $row['nome'], (string) $candidate['nome'])) {
                        $status = 'divergencia_nome';
                        $message = 'O CPF existe no SIGAS, mas o nome difere de forma relevante. A linha foi bloqueada para evitar associação incorreta.';
                        $blocked = true;
                    } elseif ((string) ($candidate['status'] ?? '') === 'Contemplado') {
                        $status = 'ja_contemplado';
                        $message = 'Cadastro já contemplado. Os dados informados poderão ser atualizados, mas o status Contemplado será preservado.';
                    } else {
                        $status = 'atualizar';
                        $message = 'Cadastro localizado por CPF. Dados informados serão atualizados sem apagar campos existentes e o status passará para Lista de espera.';
                    }
                }
            }
        }

        if ($blocked) {
            $summary['bloqueados']++;
        } elseif ($status !== 'duplicado_lista') {
            $summary['prontos']++;
        }

        $summaryKey = match ($status) {
            'novo' => 'novos',
            'novo_revisao' => 'novos_revisao',
            'atualizar' => 'atualizar',
            'ja_contemplado' => 'ja_contemplados',
            'duplicado_lista' => 'duplicados_ignorados',
            'conflito_cpf_lista' => 'conflitos_cpf_lista',
            'cpf_ambiguo' => 'cpf_ambiguos',
            'divergencia_nome' => 'divergencias_nome',
            default => null,
        };
        if ($summaryKey !== null) {
            $summary[$summaryKey]++;
        }

        $rows[] = $row + [
            'waitlist_status' => $status,
            'waitlist_message' => $message,
            'waitlist_blocked' => $blocked,
            'candidate_id' => $candidate ? (int) $candidate['id'] : null,
            'candidate_name' => $candidate['nome'] ?? null,
            'candidate_status' => $candidate['status'] ?? null,
        ];
    }

    return ['summary' => $summary, 'rows' => $rows];
}

function pe_waitlist_history(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min($limit, 50));
    $stmt = $pdo->prepare(
        'SELECT id, arquivo_nome, total_linhas, importados, atualizados, bloqueados, avisos, pendentes_revisao, erros,
                responsavel, status, criado_em, finalizada_em
         FROM pe_importacoes
         WHERE tipo_importacao = "lista_espera"
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pe_waitlist_merge_existing(PDO $pdo, int $candidateId, array $row): array
{
    $stmt = $pdo->prepare(
        'UPDATE pe_candidatos SET
            nome = CASE WHEN :nome <> "" THEN :nome ELSE nome END,
            data_nascimento = COALESCE(:data_nascimento, data_nascimento),
            responsavel_familiar = COALESCE(:responsavel, responsavel_familiar),
            bairro = COALESCE(:bairro, bairro),
            endereco = COALESCE(:endereco, endereco),
            telefone = COALESCE(:telefone, telefone),
            cpf = COALESCE(:cpf, cpf),
            cpf_informado = COALESCE(:cpf_informado, cpf_informado),
            status = CASE WHEN status = "Contemplado" THEN status ELSE "Lista de espera" END,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute([
        'nome' => (string) ($row['nome'] ?? ''),
        'data_nascimento' => $row['data_nascimento'] ?? null,
        'responsavel' => $row['responsavel'] ?? null,
        'bairro' => $row['bairro'] ?? null,
        'endereco' => $row['endereco'] ?? null,
        'telefone' => $row['telefone'] ?? null,
        'cpf' => $row['cpf'] ?? null,
        'cpf_informado' => $row['cpf_informado'] ?? null,
        'id' => $candidateId,
    ]);

    return pe_recalculate_review($pdo, $candidateId);
}

function pe_waitlist_insert_candidate(PDO $pdo, array $row, int $importId): int
{
    $reasons = empty($row['revisao_motivos']) ? null : implode(' | ', (array) $row['revisao_motivos']);
    $reviewDate = empty($row['revisao_status']) ? null : date('Y-m-d H:i:s');
    $sourceKey = hash('sha256', (string) ($row['chave_importacao'] ?? '') . '|waitlist:' . $importId . '|row:' . (int) ($row['row'] ?? 0));

    $stmt = $pdo->prepare(
        'INSERT INTO pe_candidatos
            (nome, data_nascimento, responsavel_familiar, bairro, endereco, telefone,
             cpf, cpf_informado, chave_importacao, status,
             revisao_status, revisao_cpf, revisao_telefone, revisao_nascimento, cpf_duplicado,
             revisao_motivos, revisao_atualizada_em, origem, importacao_id)
         VALUES
            (:nome, :data_nascimento, :responsavel, :bairro, :endereco, :telefone,
             :cpf, :cpf_informado, :chave, "Lista de espera",
             :revisao_status, :revisao_cpf, :revisao_telefone, :revisao_nascimento, :cpf_duplicado,
             :revisao_motivos, :revisao_atualizada_em, "importacao", :importacao_id)'
    );
    $stmt->execute([
        'nome' => (string) $row['nome'],
        'data_nascimento' => $row['data_nascimento'] ?? null,
        'responsavel' => $row['responsavel'] ?? null,
        'bairro' => $row['bairro'] ?? null,
        'endereco' => $row['endereco'] ?? null,
        'telefone' => $row['telefone'] ?? null,
        'cpf' => $row['cpf'] ?? null,
        'cpf_informado' => $row['cpf_informado'] ?? null,
        'chave' => $sourceKey,
        'revisao_status' => $row['revisao_status'] ?? null,
        'revisao_cpf' => (int) ($row['revisao_cpf'] ?? 0),
        'revisao_telefone' => (int) ($row['revisao_telefone'] ?? 0),
        'revisao_nascimento' => (int) ($row['revisao_nascimento'] ?? 0),
        'cpf_duplicado' => 0,
        'revisao_motivos' => $reasons,
        'revisao_atualizada_em' => $reviewDate,
        'importacao_id' => $importId,
    ]);
    return (int) $pdo->lastInsertId();
}

function pe_waitlist_import(PDO $pdo, array $prepared, string $filename, array $options = []): array
{
    if (!pe_waitlist_schema_ready($pdo)) {
        throw new RuntimeException('Execute database/primeiroEmprego/0007-primeiroEmprego-lista-espera.sql antes de usar a Lista de espera.');
    }

    $fileHash = isset($options['file_hash']) ? pe_nullable($options['file_hash']) : null;
    $responsavel = isset($options['responsavel']) ? pe_nullable($options['responsavel']) : null;
    $analysis = pe_waitlist_analyze($pdo, $prepared);

    $previousImportId = 0;
    $previousRows = [];
    if ($fileHash) {
        $same = $pdo->prepare(
            'SELECT id, total_linhas, bloqueados, erros
             FROM pe_importacoes
             WHERE arquivo_hash = :hash AND tipo_importacao = "lista_espera" AND status = "Concluída"
             ORDER BY id DESC LIMIT 1'
        );
        $same->execute(['hash' => $fileHash]);
        $previous = $same->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            $previousImportId = (int) $previous['id'];
            $done = $pdo->prepare(
                'SELECT linha, candidato_id, status
                 FROM pe_importacao_itens
                 WHERE importacao_id = :id AND candidato_id IS NOT NULL AND status IN ("Importado", "Atualizado", "Mantido")'
            );
            $done->execute(['id' => $previousImportId]);
            $doneRows = $done->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($doneRows as $doneRow) {
                $previousRows[(int) $doneRow['linha']] = (int) $doneRow['candidato_id'];
            }
            if ((int) $previous['bloqueados'] === 0 && (int) $previous['erros'] === 0 && count($doneRows) >= (int) $previous['total_linhas']) {
                throw new RuntimeException('Esta mesma Lista de espera já foi importada integralmente na importação #' . $previousImportId . '. O SIGAS impediu uma duplicação acidental.');
            }
        }
    }

    $imported = 0;
    $updated = 0;
    $keptContemplated = 0;
    $blocked = 0;
    $ignoredDuplicates = 0;
    $reviewPending = 0;
    $warnings = 0;
    $errors = [];

    $pdo->beginTransaction();
    try {
        $log = $pdo->prepare(
            'INSERT INTO pe_importacoes
                (arquivo_nome, arquivo_hash, tipo_importacao, total_linhas, status, marcar_como_contemplados, responsavel)
             VALUES (:arquivo, :hash, "lista_espera", :total, "Processando", 0, :responsavel)'
        );
        $log->execute([
            'arquivo' => $filename,
            'hash' => $fileHash,
            'total' => count($prepared),
            'responsavel' => $responsavel,
        ]);
        $importId = (int) $pdo->lastInsertId();

        foreach ($analysis['rows'] as $row) {
            $pdo->exec('SAVEPOINT pe_waitlist_row');
            try {
                $rowNumber = (int) ($row['row'] ?? 0);
                $candidateId = 0;
                $itemStatus = '';
                $message = (string) ($row['waitlist_message'] ?? '');

                if (!empty($row['waitlist_blocked'])) {
                    $blocked++;
                    $warnings++;
                    pe_import_item_log($pdo, [
                        'importacao_id' => $importId,
                        'linha' => $rowNumber,
                        'candidato_id' => $row['candidate_id'] ?? null,
                        'status' => 'Bloqueado',
                        'nome' => (string) $row['nome'],
                        'cpf_informado' => $row['cpf_informado'] ?? null,
                        'cpf_validado' => $row['cpf'] ?? null,
                        'data_nascimento' => $row['data_nascimento'] ?? null,
                        'setor_informado' => $row['setor'] ?? null,
                        'mensagem' => $message,
                    ]);
                    continue;
                }

                if (($row['waitlist_status'] ?? '') === 'duplicado_lista') {
                    $ignoredDuplicates++;
                    $warnings++;
                    pe_import_item_log($pdo, [
                        'importacao_id' => $importId,
                        'linha' => $rowNumber,
                        'candidato_id' => null,
                        'status' => 'Ignorado',
                        'nome' => (string) $row['nome'],
                        'cpf_informado' => $row['cpf_informado'] ?? null,
                        'cpf_validado' => $row['cpf'] ?? null,
                        'data_nascimento' => $row['data_nascimento'] ?? null,
                        'setor_informado' => $row['setor'] ?? null,
                        'mensagem' => $message,
                    ]);
                    continue;
                }

                if (isset($previousRows[$rowNumber]) && $previousRows[$rowNumber] > 0) {
                    $candidateId = (int) $previousRows[$rowNumber];
                    $current = pe_candidate_by_id($pdo, $candidateId);
                    if (!$current) {
                        throw new RuntimeException('O cadastro vinculado à importação anterior não existe mais.');
                    }
                    pe_waitlist_merge_existing($pdo, $candidateId, $row);
                    if ((string) ($current['status'] ?? '') === 'Contemplado') {
                        $keptContemplated++;
                        $itemStatus = 'Mantido';
                        $message .= ' Linha recuperada da importação parcial #' . $previousImportId . '; status Contemplado preservado.';
                    } else {
                        $updated++;
                        $itemStatus = 'Atualizado';
                        $message .= ' Linha recuperada da importação parcial #' . $previousImportId . '.';
                    }
                } elseif (!empty($row['candidate_id'])) {
                    $candidateId = (int) $row['candidate_id'];
                    $current = pe_candidate_by_id($pdo, $candidateId);
                    if (!$current) {
                        throw new RuntimeException('Cadastro localizado na pré-análise não foi encontrado ao aplicar a importação.');
                    }
                    pe_waitlist_merge_existing($pdo, $candidateId, $row);
                    if ((string) ($current['status'] ?? '') === 'Contemplado') {
                        $keptContemplated++;
                        $itemStatus = 'Mantido';
                    } else {
                        $updated++;
                        $itemStatus = 'Atualizado';
                    }
                } else {
                    $candidateId = pe_waitlist_insert_candidate($pdo, $row, $importId);
                    $imported++;
                    $itemStatus = 'Importado';
                }

                $recalculated = pe_candidate_by_id($pdo, $candidateId);
                if (!empty($recalculated['revisao_status'])) {
                    $reviewPending++;
                }
                if (!empty($row['issues'])) {
                    $warnings += count((array) $row['issues']);
                }
                if (!empty($row['setor'])) {
                    $message .= ' Setor informado preservado apenas no histórico da importação; a lotação não é criada enquanto a pessoa estiver na Lista de espera.';
                }

                pe_import_item_log($pdo, [
                    'importacao_id' => $importId,
                    'linha' => $rowNumber,
                    'candidato_id' => $candidateId,
                    'status' => $itemStatus,
                    'nome' => (string) $row['nome'],
                    'cpf_informado' => $row['cpf_informado'] ?? null,
                    'cpf_validado' => $row['cpf'] ?? null,
                    'data_nascimento' => $row['data_nascimento'] ?? null,
                    'setor_informado' => $row['setor'] ?? null,
                    'mensagem' => trim($message),
                ]);
            } catch (Throwable $rowError) {
                $pdo->exec('ROLLBACK TO SAVEPOINT pe_waitlist_row');
                $errors[] = ['row' => (int) ($row['row'] ?? 0), 'message' => $rowError->getMessage()];
                pe_import_item_log($pdo, [
                    'importacao_id' => $importId,
                    'linha' => (int) ($row['row'] ?? 0),
                    'candidato_id' => null,
                    'status' => 'Erro',
                    'nome' => (string) ($row['nome'] ?? 'Linha sem nome'),
                    'cpf_informado' => $row['cpf_informado'] ?? null,
                    'cpf_validado' => $row['cpf'] ?? null,
                    'data_nascimento' => $row['data_nascimento'] ?? null,
                    'setor_informado' => $row['setor'] ?? null,
                    'mensagem' => $rowError->getMessage(),
                ]);
            }
        }

        $done = $pdo->prepare(
            'UPDATE pe_importacoes SET
                importados = :importados,
                atualizados = :atualizados,
                bloqueados = :bloqueados,
                avisos = :avisos,
                pendentes_revisao = :pendentes,
                erros = :erros,
                status = "Concluída",
                finalizada_em = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $done->execute([
            'importados' => $imported,
            'atualizados' => $updated + $keptContemplated,
            'bloqueados' => $blocked,
            'avisos' => $warnings,
            'pendentes' => $reviewPending,
            'erros' => count($errors),
            'id' => $importId,
        ]);

        $pdo->commit();
        return [
            'import_id' => $importId,
            'imported' => $imported,
            'updated' => $updated,
            'kept_contemplated' => $keptContemplated,
            'blocked' => $blocked,
            'ignored_duplicates' => $ignoredDuplicates,
            'review_pending' => $reviewPending,
            'warnings' => $warnings,
            'errors' => $errors,
            'resumed_from' => $previousImportId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
