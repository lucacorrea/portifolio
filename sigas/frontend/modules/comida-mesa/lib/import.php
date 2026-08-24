<?php

declare(strict_types=1);

use App\Core\Validator;
use App\DTO\ComidaMesaCadastroData;

require_once __DIR__ . '/spreadsheet.php';

function cm_import_schema_ready(PDO $pdo): bool
{
    try {
        foreach (['comida_mesa_importacoes', 'comida_mesa_importacao_itens'] as $table) {
            $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        }
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
        (importacao_id, linha, status, pessoa_id, familia_id, inscricao_id, nome, cpf_informado, cpf_validado,
         telefone_informado, polo_informado, classificacao, motivos, dados_json)
        VALUES (:importacao_id, :linha, :status, :pessoa_id, :familia_id, :inscricao_id, :nome, :cpf_informado, :cpf_validado,
                :telefone_informado, :polo_informado, :classificacao, :motivos, :dados_json)');
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
    ]);
}

/** @return array<string,mixed> */
function cm_import_execute(PDO $pdo, array $prepared, string $filename, string $fileHash, array $options = []): array
{
    $app = cm_app();
    $service = $app['service'];
    $audit = $app['audit'];
    $user = $app['user'];
    $repository = $app['repository'];
    $userId = (int) $user->id;

    $stmt = $pdo->prepare('INSERT INTO comida_mesa_importacoes
        (arquivo_nome, arquivo_hash, status, total_linhas, polo_padrao_id, status_padrao, prioridade_padrao,
         zona_padrao, atualizar_existentes, criado_por)
        VALUES (:arquivo_nome, :arquivo_hash, "Processando", :total_linhas, :polo_padrao_id, :status_padrao,
                :prioridade_padrao, :zona_padrao, :atualizar_existentes, :criado_por)');
    $stmt->execute([
        'arquivo_nome' => $filename,
        'arquivo_hash' => $fileHash,
        'total_linhas' => count($prepared),
        'polo_padrao_id' => !empty($options['polo_padrao_id']) ? (int) $options['polo_padrao_id'] : null,
        'status_padrao' => (string) ($options['status_padrao'] ?? 'em_analise'),
        'prioridade_padrao' => (string) ($options['prioridade_padrao'] ?? 'normal'),
        'zona_padrao' => ($options['zona_padrao'] ?? '') !== '' ? (string) $options['zona_padrao'] : null,
        'atualizar_existentes' => !empty($options['atualizar_existentes']) ? 1 : 0,
        'criado_por' => $userId,
    ]);
    $importId = (int) $pdo->lastInsertId();

    $counts = ['novos'=>0,'atualizados'=>0,'ignorados'=>0,'revisar'=>0,'erros'=>0];
    $errors = [];

    foreach ($prepared as $row) {
        if (($row['action'] ?? '') === 'Revisar') {
            $counts['revisar']++;
            cm_import_item_log($pdo, $importId, $row, 'Revisar');
            continue;
        }
        if (($row['action'] ?? '') === 'Ignorar') {
            $counts['ignorados']++;
            cm_import_item_log($pdo, $importId, $row, 'Ignorado');
            continue;
        }

        try {
            $payload = $row['data'];
            if (($row['action'] ?? '') === 'Atualizar' && is_array($row['existing'] ?? null)) {
                $payload = cm_import_merge_existing($payload, $row['existing'], $row['source_has'] ?? []);
            }

            $dto = ComidaMesaCadastroData::fromArray($payload);
            $result = $service->saveRegistration($dto, $userId, $audit);
            $detail = $repository->detail((int) $result['id'], false, false) ?: [];
            $ids = [
                'pessoa_id' => $detail['responsavel_pessoa_id'] ?? null,
                'familia_id' => $detail['familia_id'] ?? null,
                'inscricao_id' => $result['id'],
            ];
            $status = ($row['action'] ?? '') === 'Atualizar' ? 'Atualizado' : 'Importado';
            $counts[$status === 'Atualizado' ? 'atualizados' : 'novos']++;
            cm_import_item_log($pdo, $importId, $row, $status, $ids);
        } catch (Throwable $e) {
            $counts['erros']++;
            $errors[] = ['row' => (int) ($row['row'] ?? 0), 'message' => $e->getMessage()];
            cm_import_item_log($pdo, $importId, $row, 'Erro', null, $e->getMessage());
        }
    }

    $done = $pdo->prepare('UPDATE comida_mesa_importacoes SET
        novos = :novos, atualizados = :atualizados, ignorados = :ignorados, revisar = :revisar,
        erros = :erros, status = "Concluída", finalizado_em = CURRENT_TIMESTAMP
        WHERE id = :id');
    $done->execute([
        'novos' => $counts['novos'],
        'atualizados' => $counts['atualizados'],
        'ignorados' => $counts['ignorados'],
        'revisar' => $counts['revisar'],
        'erros' => $counts['erros'],
        'id' => $importId,
    ]);

    $audit->record($userId, null, 'importacao_comida_mesa', 'comida_mesa', $filename, null, [
        'importacao_id' => $importId,
        'novos' => $counts['novos'],
        'atualizados' => $counts['atualizados'],
        'revisar' => $counts['revisar'],
        'erros' => $counts['erros'],
    ]);

    return ['import_id'=>$importId,'counts'=>$counts,'errors'=>$errors];
}
