<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = (int)($_GET['id'] ?? 0);

function parse_oficio_money($valor) {
    $valor = trim((string)$valor);

    if ($valor === '') {
        return null;
    }

    $valor = str_ireplace('R$', '', $valor);
    $valor = preg_replace('/\s+/', '', $valor);

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
        $valor = str_replace('.', '', $valor);
    }

    if (!is_numeric($valor)) {
        throw new Exception("Informe um valor monetário válido.");
    }

    $valor = (float)$valor;

    if ($valor < 0) {
        throw new Exception("Valores monetários não podem ser negativos.");
    }

    return $valor;
}

function format_money_input($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }

    return number_format((float)$valor, 2, ',', '.');
}

function format_quantity_input($valor) {
    return rtrim(rtrim(number_format((float)$valor, 2, '.', ''), '0'), '.');
}

function parse_datetime_local_required($valor, string $campo): string {
    $valor = trim((string)$valor);

    if ($valor === '') {
        throw new Exception("Informe {$campo}.");
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $valor);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $valor) {
        throw new Exception("Informe uma data válida para {$campo}.");
    }

    return $dt->format('Y-m-d H:i:s');
}

function parse_oficio_datetime($valor): string {
    return parse_datetime_local_required($valor, 'a data do ofício');
}

function format_datetime_local_input($valor): string {
    if (empty($valor)) {
        return '';
    }

    $timestamp = strtotime((string)$valor);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

$oficio_status_options = [
    'PENDENTE_ITENS' => 'PENDENTE_ITENS',
    'ENVIADO' => 'ENVIADO',
    'EM_ANALISE' => 'EM_ANALISE',
    'APROVADO' => 'APROVADO',
    'REPROVADO' => 'REPROVADO',
    'ARQUIVADO' => 'ARQUIVADO',
];

$aquisicao_status_options = [
    'AGUARDANDO ENTREGA' => 'AGUARDANDO ENTREGA',
    'FINALIZADO' => 'FINALIZADO',
];

function oficio_edit_current_user_id(): ?int {
    foreach (['usuario_id', 'user_id', 'id'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            return (int)$_SESSION[$key];
        }
    }

    return null;
}

if (($_GET['ajax'] ?? '') === 'sugerir_itens') {
    header('Content-Type: application/json; charset=utf-8');

    $termo = trim((string)($_GET['q'] ?? ''));
    if (strlen($termo) < 2) {
        echo json_encode([]);
        exit;
    }

    try {
        $termo_like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);

        $stmt_sugestoes = $pdo->prepare("
            SELECT
                produto,
                unidade,
                CAST(SUBSTRING_INDEX(GROUP_CONCAT(valor_unitario ORDER BY ultima_data DESC SEPARATOR ','), ',', 1) AS DECIMAL(15,2)) AS valor_unitario,
                COUNT(*) AS usos,
                MAX(ultima_data) AS ultima_data,
                GROUP_CONCAT(DISTINCT origem ORDER BY origem SEPARATOR ',') AS origens
            FROM (
                SELECT
                    TRIM(io.produto) AS produto,
                    COALESCE(NULLIF(TRIM(io.unidade), ''), 'UN') AS unidade,
                    COALESCE(io.valor_unitario, 0) AS valor_unitario,
                    COALESCE(o.criado_em, NOW()) AS ultima_data,
                    'oficio' AS origem
                FROM itens_oficio io
                LEFT JOIN oficios o ON o.id = io.oficio_id
                WHERE io.produto LIKE :termo_oficio ESCAPE '\\\\'

                UNION ALL

                SELECT
                    TRIM(ia.produto) AS produto,
                    COALESCE(
                        NULLIF(TRIM((
                            SELECT io2.unidade
                            FROM itens_oficio io2
                            JOIN aquisicoes aq2 ON aq2.oficio_id = io2.oficio_id
                            WHERE aq2.id = ia.aquisicao_id
                              AND (
                                  io2.id = ia.oficio_item_id
                                  OR (
                                      ia.oficio_item_id IS NULL
                                      AND TRIM(UPPER(io2.produto)) = TRIM(UPPER(ia.produto))
                                  )
                              )
                            ORDER BY io2.id ASC
                            LIMIT 1
                        )), ''),
                        'UN'
                    ) AS unidade,
                    COALESCE(ia.valor_unitario, 0) AS valor_unitario,
                    COALESCE(a.criado_em, NOW()) AS ultima_data,
                    'aquisicao' AS origem
                FROM itens_aquisicao ia
                LEFT JOIN aquisicoes a ON a.id = ia.aquisicao_id
                WHERE ia.produto LIKE :termo_aquisicao ESCAPE '\\\\'
            ) base
            WHERE produto <> ''
            GROUP BY produto, unidade
            ORDER BY
                CASE WHEN produto LIKE :termo_prefixo ESCAPE '\\\\' THEN 0 ELSE 1 END,
                usos DESC,
                ultima_data DESC,
                produto ASC
            LIMIT 12
        ");

        $stmt_sugestoes->execute([
            ':termo_oficio' => '%' . $termo_like . '%',
            ':termo_aquisicao' => '%' . $termo_like . '%',
            ':termo_prefixo' => $termo_like . '%',
        ]);

        $sugestoes = [];
        foreach ($stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origens = array_filter(explode(',', (string)($row['origens'] ?? '')));
            $labels = [];

            if (in_array('oficio', $origens, true)) {
                $labels[] = 'Ofícios';
            }

            if (in_array('aquisicao', $origens, true)) {
                $labels[] = 'Aquisições';
            }

            $sugestoes[] = [
                'produto' => (string)$row['produto'],
                'unidade' => (string)($row['unidade'] ?: 'UN'),
                'valor_unitario' => (float)($row['valor_unitario'] ?? 0),
                'usos' => (int)($row['usos'] ?? 0),
                'origem' => !empty($labels) ? implode(' + ', $labels) : 'Histórico',
            ];
        }

        echo json_encode($sugestoes, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Não foi possível buscar sugestões de itens.'], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die("Solicitação não encontrada.");
}

$secretarias = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$stmt_aquisicao = $pdo->prepare("
    SELECT a.id, a.numero_aq, a.status, a.criado_em, a.fornecedor_id, f.nome AS fornecedor
    FROM aquisicoes a
    LEFT JOIN fornecedores f ON f.id = a.fornecedor_id
    WHERE a.oficio_id = ?
    ORDER BY a.id ASC
");
$stmt_aquisicao->execute([$id]);
$aquisicoes_vinculadas = $stmt_aquisicao->fetchAll(PDO::FETCH_ASSOC);
$total_aquisicoes_vinculadas = count($aquisicoes_vinculadas);

$aquisicoes_by_id = [];
foreach ($aquisicoes_vinculadas as $aquisicao_vinculada) {
    $aquisicoes_by_id[(int)$aquisicao_vinculada['id']] = $aquisicao_vinculada;
}

$itens_por_aquisicao = [];
if ($total_aquisicoes_vinculadas > 0) {
    $stmt_itens_aquisicao = $pdo->prepare("
        SELECT ia.aquisicao_id, ia.oficio_item_id
        FROM itens_aquisicao ia
        JOIN aquisicoes a ON a.id = ia.aquisicao_id
        WHERE a.oficio_id = ?
          AND ia.oficio_item_id IS NOT NULL
        ORDER BY ia.id ASC
    ");
    $stmt_itens_aquisicao->execute([$id]);

    foreach ($stmt_itens_aquisicao->fetchAll(PDO::FETCH_ASSOC) as $item_aquisicao) {
        $aquisicao_id = (int)$item_aquisicao['aquisicao_id'];
        $itens_por_aquisicao[$aquisicao_id][] = 'item-' . (int)$item_aquisicao['oficio_item_id'];
    }
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$items_existentes_by_id = [];
foreach ($items_existentes as $item_existente) {
    $items_existentes_by_id[(int)$item_existente['id']] = $item_existente;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $numero_manual = mb_strtoupper(trim($_POST['numero_oficio'] ?? ''), 'UTF-8');
        $secretaria_id = (int)($_POST['secretaria_id'] ?? 0);
        $local = trim((string)($_POST['local'] ?? ''));
        $resumo_itens = trim((string)($_POST['resumo_itens'] ?? ''));
        $criado_em = parse_oficio_datetime($_POST['criado_em'] ?? '');
        $valor_orcamento = parse_oficio_money($_POST['valor_orcamento'] ?? '');
        $produtos = $_POST['produtos'] ?? [];
        $status_oficio = trim((string)($_POST['status_oficio'] ?? ($oficio['status'] ?? 'PENDENTE_ITENS')));
        $empresas = $_POST['empresas'] ?? [];

        if ($numero_manual === '') {
            throw new Exception("O número do ofício é obrigatório.");
        }

        $secretarias_validas = array_map('intval', array_column($secretarias, 'id'));
        $fornecedores_validos = array_map('intval', array_column($fornecedores, 'id'));
        if ($secretaria_id <= 0 || !in_array($secretaria_id, $secretarias_validas, true)) {
            throw new Exception("Selecione uma secretaria válida.");
        }

        if ($local === '') {
            throw new Exception("Informe o local do ofício.");
        }

        if (!array_key_exists($status_oficio, $oficio_status_options)) {
            throw new Exception("Selecione um status válido para o ofício.");
        }

        $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ? AND id <> ?");
        $stmt_check->execute([$numero_manual, $id]);
        if ($stmt_check->fetch()) {
            throw new Exception("O número de ofício '{$numero_manual}' já está cadastrado em outra solicitação.");
        }

        $itens_sanitizados = [];
        $total_calculado = 0;

        $itens_sanitizados_by_key = [];
        foreach ($produtos as $idx => $p) {
            $item_id_original = (int)($p['id'] ?? 0);
            $item_key = trim((string)($p['key'] ?? ''));
            $nome = trim((string)($p['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            if ($item_id_original > 0 && !isset($items_existentes_by_id[$item_id_original])) {
                throw new Exception("Um dos itens enviados não pertence a esta solicitação.");
            }

            if (!preg_match('/^(item-\d+|new-[a-zA-Z0-9_-]+)$/', $item_key) || isset($itens_sanitizados_by_key[$item_key])) {
                throw new Exception("A identificação de um dos itens é inválida. Recarregue a página e tente novamente.");
            }

            $qtd = (float)str_replace(',', '.', (string)($p['qtd'] ?? 0));
            $unidade = trim((string)($p['unidade'] ?? 'UN'));
            $valor_unitario = parse_oficio_money($p['valor'] ?? '0');
            $valor_unitario = $valor_unitario ?? 0;

            if ($qtd <= 0) {
                throw new Exception("A quantidade do item " . ($idx + 1) . " deve ser maior que zero.");
            }

            if ($unidade === '') {
                $unidade = 'UN';
            }

            $total_calculado += ($qtd * $valor_unitario);

            $item_sanitizado = [
                'key' => $item_key,
                'id_original' => $item_id_original,
                'produto' => $nome,
                'quantidade' => $qtd,
                'unidade' => $unidade,
                'valor_unitario' => $valor_unitario,
            ];
            $itens_sanitizados[] = $item_sanitizado;
            $itens_sanitizados_by_key[$item_key] = $item_sanitizado;
        }

        if (empty($itens_sanitizados)) {
            throw new Exception("Informe pelo menos um item para a solicitação.");
        }

        if ($valor_orcamento !== null && $valor_orcamento > 0 && abs($total_calculado - $valor_orcamento) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($valor_orcamento, 2, ',', '.'));
        }

        $empresas_sanitizadas = [];
        if ($total_aquisicoes_vinculadas > 0) {
            if (count($empresas) < $total_aquisicoes_vinculadas) {
                throw new Exception("As aquisições existentes não podem ser removidas nesta edição.");
            }

            if (count($empresas) > count($fornecedores)) {
                throw new Exception("A quantidade de empresas não pode ser maior que a quantidade de fornecedores cadastrados.");
            }

            $aquisicoes_informadas = [];
            $fornecedores_usados = [];
            $itens_atribuidos = [];

            foreach (array_values($empresas) as $empresa_idx => $empresa) {
                $aquisicao_id = (int)($empresa['aquisicao_id'] ?? 0);
                $fornecedor_id = (int)($empresa['fornecedor_id'] ?? 0);
                $status_aquisicao = trim((string)($empresa['status'] ?? 'AGUARDANDO ENTREGA'));
                $numero_referencia = $aquisicao_id > 0 && isset($aquisicoes_by_id[$aquisicao_id])
                    ? $aquisicoes_by_id[$aquisicao_id]['numero_aq']
                    : 'nova aquisição ' . ($empresa_idx + 1);

                if ($aquisicao_id > 0) {
                    if (!isset($aquisicoes_by_id[$aquisicao_id]) || isset($aquisicoes_informadas[$aquisicao_id])) {
                        throw new Exception("Uma das aquisições informadas é inválida.");
                    }
                    $aquisicoes_informadas[$aquisicao_id] = true;
                }

                if ($fornecedor_id <= 0 || !in_array($fornecedor_id, $fornecedores_validos, true)) {
                    throw new Exception("Selecione um fornecedor válido para {$numero_referencia}.");
                }
                if (isset($fornecedores_usados[$fornecedor_id])) {
                    throw new Exception("O mesmo fornecedor não pode ser usado em mais de uma aquisição.");
                }
                $fornecedores_usados[$fornecedor_id] = true;

                if (!array_key_exists($status_aquisicao, $aquisicao_status_options)) {
                    throw new Exception("Selecione um status válido para {$numero_referencia}.");
                }

                $data_aquisicao = $criado_em;

                $item_keys = array_values(array_unique(array_map('strval', $empresa['itens'] ?? [])));
                if (empty($item_keys)) {
                    throw new Exception("Selecione pelo menos um item para {$numero_referencia}.");
                }

                $itens_empresa = [];
                $valor_total_empresa = 0;
                foreach ($item_keys as $item_key) {
                    if (!isset($itens_sanitizados_by_key[$item_key])) {
                        throw new Exception("Um item inválido foi atribuído a {$numero_referencia}.");
                    }
                    if (isset($itens_atribuidos[$item_key])) {
                        throw new Exception("O item '{$itens_sanitizados_by_key[$item_key]['produto']}' foi atribuído a mais de um fornecedor.");
                    }

                    $itens_atribuidos[$item_key] = true;
                    $item_empresa = $itens_sanitizados_by_key[$item_key];
                    $valor_total_empresa += $item_empresa['quantidade'] * $item_empresa['valor_unitario'];
                    $itens_empresa[] = $item_empresa;
                }

                $empresas_sanitizadas[] = [
                    'aquisicao_id' => $aquisicao_id,
                    'fornecedor_id' => $fornecedor_id,
                    'criado_em' => $data_aquisicao,
                    'status' => $status_aquisicao,
                    'itens' => $itens_empresa,
                    'valor_total' => $valor_total_empresa,
                ];
            }

            if (count($aquisicoes_informadas) !== $total_aquisicoes_vinculadas) {
                throw new Exception("Todas as aquisições existentes devem permanecer na distribuição.");
            }

            if (count($itens_atribuidos) !== count($itens_sanitizados_by_key)) {
                $pendentes = [];
                foreach ($itens_sanitizados_by_key as $item_key => $item) {
                    if (!isset($itens_atribuidos[$item_key])) {
                        $pendentes[] = $item['produto'];
                    }
                }
                throw new Exception("Distribua todos os itens entre os fornecedores. Pendentes: " . implode(', ', $pendentes) . ".");
            }
        }

        $pdo->beginTransaction();

        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET numero = ?, secretaria_id = ?, local = ?, resumo_itens = ?, criado_em = ?, valor_orcamento = ?, status = ?
            WHERE id = ?
        ");
        $stmt_update->execute([
            $numero_manual,
            $secretaria_id,
            $local,
            $resumo_itens !== '' ? $resumo_itens : null,
            $criado_em,
            $valor_orcamento,
            $status_oficio,
            $id
        ]);

        $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);

        $stmt_item = $pdo->prepare("
            INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        $itens_reinseridos = [];

        foreach ($itens_sanitizados as $item) {
            $stmt_item->execute([
                $id,
                $item['produto'],
                $item['quantidade'],
                $item['unidade'],
                $item['valor_unitario'],
            ]);

            $novo_item_id = (int)$pdo->lastInsertId();
            $item['id_novo'] = $novo_item_id;
            $itens_reinseridos[] = $item;

        }

        if ($total_aquisicoes_vinculadas > 0) {
            $itens_reinseridos_by_key = [];
            foreach ($itens_reinseridos as $item_reinserido) {
                $itens_reinseridos_by_key[$item_reinserido['key']] = $item_reinserido;
            }

            $stmt_update_aquisicao = $pdo->prepare("
                UPDATE aquisicoes
                SET criado_em = ?, fornecedor_id = ?, valor_total = ?, status = ?,
                    data_finalizacao = CASE
                        WHEN ? = 'FINALIZADO' THEN COALESCE(data_finalizacao, CURRENT_TIMESTAMP)
                        ELSE NULL
                    END,
                    usuario_id_finalizou = CASE
                        WHEN ? = 'FINALIZADO' THEN COALESCE(usuario_id_finalizou, ?)
                        ELSE NULL
                    END
                WHERE id = ? AND oficio_id = ?
            ");
            $stmt_insert_aquisicao = $pdo->prepare("
                INSERT INTO aquisicoes (
                    numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total,
                    status, criado_em, data_finalizacao, usuario_id_finalizou
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_delete_itens_aquisicao = $pdo->prepare("DELETE FROM itens_aquisicao WHERE aquisicao_id = ?");
            $stmt_insert_item_aquisicao = $pdo->prepare("
                INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
                VALUES (?, ?, ?, ?, ?)
            ");

            $novas_aquisicoes = 0;
            foreach ($empresas_sanitizadas as $empresa) {
                $aquisicao_id = (int)$empresa['aquisicao_id'];
                $status_aquisicao = $empresa['status'];
                $usuario_finalizou = $status_aquisicao === 'FINALIZADO' ? oficio_edit_current_user_id() : null;
                $data_finalizacao = $status_aquisicao === 'FINALIZADO' ? date('Y-m-d H:i:s') : null;

                if ($aquisicao_id > 0) {
                    $stmt_update_aquisicao->execute([
                        $empresa['criado_em'],
                        $empresa['fornecedor_id'],
                        $empresa['valor_total'],
                        $status_aquisicao,
                        $status_aquisicao,
                        $status_aquisicao,
                        oficio_edit_current_user_id(),
                        $aquisicao_id,
                        $id,
                    ]);
                    $stmt_delete_itens_aquisicao->execute([$aquisicao_id]);
                } else {
                    $stmt_insert_aquisicao->execute([
                        generate_aquisicao_number($pdo),
                        generate_unique_code($pdo),
                        $id,
                        $empresa['fornecedor_id'],
                        $empresa['valor_total'],
                        $status_aquisicao,
                        $empresa['criado_em'],
                        $data_finalizacao,
                        $usuario_finalizou,
                    ]);
                    $aquisicao_id = (int)$pdo->lastInsertId();
                    $novas_aquisicoes++;
                }

                foreach ($empresa['itens'] as $item_empresa) {
                    $item_reinserido = $itens_reinseridos_by_key[$item_empresa['key']];
                    $stmt_insert_item_aquisicao->execute([
                        $aquisicao_id,
                        (int)$item_reinserido['id_novo'],
                        $item_reinserido['produto'],
                        $item_reinserido['quantidade'],
                        $item_reinserido['valor_unitario'],
                    ]);
                }
            }
        }

        log_action($pdo, "EDITAR_OFICIO", "Solicitação {$oficio['numero']} editada para {$numero_manual}");
        $pdo->commit();

        $msg = "Solicitação {$numero_manual} atualizada com sucesso.";
        if ($total_aquisicoes_vinculadas > 0) {
            $msg .= " As aquisições e a distribuição por fornecedor também foram sincronizadas.";
            if (!empty($novas_aquisicoes)) {
                $msg .= " {$novas_aquisicoes} nova(s) aquisição(ões) criada(s).";
            }
        }

        flash_message('success', $msg);
        header("Location: oficios_visualizar.php?id={$id}");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = "Erro ao editar: " . $e->getMessage();
    }
}

$stmt_resumo = $pdo->prepare("
    SELECT
        TRIM(produto) AS produto,
        COALESCE(NULLIF(TRIM(unidade), ''), 'UN') AS unidade,
        COUNT(*) AS total_registros,
        SUM(quantidade) AS quantidade_total,
        SUM(quantidade * valor_unitario) AS valor_total_produto
    FROM itens_oficio
    WHERE oficio_id = ?
    GROUP BY TRIM(produto), COALESCE(NULLIF(TRIM(unidade), ''), 'UN')
    ORDER BY produto ASC
");
$stmt_resumo->execute([$id]);
$resumo_produtos = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_form = [];
    foreach (($_POST['produtos'] ?? []) as $p) {
        $items_form[] = [
            'id' => (int)($p['id'] ?? 0),
            'key' => (string)($p['key'] ?? ''),
            'produto' => $p['nome'] ?? '',
            'quantidade_input' => $p['qtd'] ?? '1',
            'unidade' => $p['unidade'] ?? 'UN',
            'valor_input' => $p['valor'] ?? '',
        ];
    }
} else {
    $items_form = !empty($items_existentes)
        ? $items_existentes
        : [['id' => 0, 'key' => 'new-initial-0', 'produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]];
}

$numero_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['numero_oficio'] ?? '')
    : ($oficio['numero'] ?? '');

$secretaria_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['secretaria_id'] ?? 0)
    : (int)($oficio['secretaria_id'] ?? 0);

$local_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['local'] ?? '')
    : ($oficio['local'] ?? '');

$resumo_itens_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['resumo_itens'] ?? '')
    : ($oficio['resumo_itens'] ?? '');

$oficio_status_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['status_oficio'] ?? ($oficio['status'] ?? 'PENDENTE_ITENS'))
    : ($oficio['status'] ?? 'PENDENTE_ITENS');

$criado_em_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['criado_em'] ?? '')
    : format_datetime_local_input($oficio['criado_em'] ?? '');

$secretaria_nome_atual = $oficio['secretaria'] ?? '';
foreach ($secretarias as $sec) {
    if ((int)$sec['id'] === (int)$secretaria_value) {
        $secretaria_nome_atual = $sec['nome'];
        break;
    }
}

$criado_em_label = '-';
$criado_em_timestamp = strtotime(str_replace('T', ' ', (string)$criado_em_value));
if ($criado_em_timestamp !== false) {
    $criado_em_label = date('d/m/Y H:i', $criado_em_timestamp);
}

$empresas_form = [];
if ($total_aquisicoes_vinculadas > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $empresas_form = array_values($_POST['empresas'] ?? []);
    } else {
        $itens_atribuidos_iniciais = [];
        foreach ($aquisicoes_vinculadas as $aq) {
            $aq_id = (int)$aq['id'];
            $item_keys = array_values(array_unique($itens_por_aquisicao[$aq_id] ?? []));
            foreach ($item_keys as $item_key) {
                $itens_atribuidos_iniciais[$item_key] = true;
            }

            $empresas_form[] = [
                'aquisicao_id' => $aq_id,
                'numero_aq' => $aq['numero_aq'],
                'fornecedor_id' => (int)($aq['fornecedor_id'] ?? 0),
                'criado_em' => format_datetime_local_input($aq['criado_em'] ?? ''),
                'status' => $aq['status'] ?? 'AGUARDANDO ENTREGA',
                'itens' => $item_keys,
            ];
        }

        if (!empty($empresas_form)) {
            foreach ($items_existentes as $item_existente) {
                $item_key = 'item-' . (int)$item_existente['id'];
                if (!isset($itens_atribuidos_iniciais[$item_key])) {
                    $empresas_form[0]['itens'][] = $item_key;
                }
            }
        }
    }
}

$qtd_empresas_form = max($total_aquisicoes_vinculadas, count($empresas_form));

$orcamento_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['valor_orcamento'] ?? '')
    : format_money_input($oficio['valor_orcamento'] ?? null);

$page_title = "Editar Solicitação - " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .oficio-edit-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .oficio-edit-span-2 {
        grid-column: span 2;
    }

    .form-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0 0 1rem;
        color: var(--text-dark);
        font-size: 1rem;
        font-weight: 800;
    }

    .aquisicoes-date-panel {
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 1.25rem;
        margin: 0 0 1.5rem;
        background: #fff;
    }

    .aquisicoes-section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin: 0 0 1rem;
        flex-wrap: wrap;
    }

    .aquisicoes-date-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        color: var(--text-dark);
        font-size: 1rem;
        font-weight: 800;
    }

    .aquisicoes-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: .35rem .75rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #3156a3;
        font-size: .78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .aquisicoes-date-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .85rem;
    }

    .aquisicao-date-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1rem;
        background: #f8fafc;
        display: grid;
        grid-template-columns: minmax(150px, .8fr) minmax(280px, 1.7fr) minmax(220px, 1fr) minmax(150px, auto);
        gap: 1rem;
        align-items: center;
    }

    .aquisicao-readonly {
        background: #fff;
        font-weight: 900;
        color: var(--text-dark);
    }

    .aquisicao-status-pill {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: .65rem .8rem;
        border-radius: 10px;
        background: #eef2f7;
        color: #475569;
        font-size: .78rem;
        font-weight: 900;
        text-transform: uppercase;
        text-align: center;
        white-space: nowrap;
    }

    .distribuicao-status {
        margin-bottom: 1rem;
        padding: .75rem 1rem;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        background: #eff6ff;
        color: #1e40af;
        font-size: .88rem;
        font-weight: 800;
    }

    #empresas-container {
        display: grid;
        gap: 1rem;
    }

    .empresa-card {
        overflow: hidden;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        background: #fff;
    }

    .empresa-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .9rem 1rem;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
    }

    .empresa-card-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        color: #0f172a;
        font-size: .95rem;
    }

    .empresa-card-title small {
        color: #64748b;
        font-size: .75rem;
        font-weight: 700;
    }

    .empresa-total {
        color: #166534;
        font-weight: 900;
        white-space: nowrap;
    }

    .empresa-fields {
        display: grid;
        grid-template-columns: 1.5fr 1fr .8fr;
        gap: 1rem;
        padding: 1rem;
    }

    .empresa-items {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
        padding: 0 1rem 1rem;
    }

    .item-choice {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .75rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
    }

    .item-choice.is-disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .item-choice span {
        min-width: 0;
    }

    .item-choice-name,
    .item-choice-meta {
        display: block;
    }

    .item-choice-name {
        color: #0f172a;
        overflow-wrap: anywhere;
    }

    .item-choice-meta {
        margin-top: .2rem;
        color: #64748b;
        font-size: .76rem;
        font-weight: 700;
    }

    .item-name-group {
        position: relative;
    }

    .item-suggestions {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        z-index: 40;
        display: none;
        max-height: 320px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
        padding: .45rem;
    }

    .item-suggestions.show {
        display: block;
    }

    .suggestion-option {
        width: 100%;
        border: 0;
        background: transparent;
        text-align: left;
        cursor: pointer;
        border-radius: 10px;
        padding: .72rem .78rem;
        display: block;
        color: #0f172a;
        transition: background .16s ease, transform .16s ease;
    }

    .suggestion-option:hover,
    .suggestion-option.active {
        background: #eef6ff;
    }

    .suggestion-option:active {
        transform: scale(.99);
    }

    .suggestion-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .suggestion-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .suggestion-price {
        flex-shrink: 0;
        color: #157347;
        font-weight: 900;
        white-space: nowrap;
    }

    .suggestion-meta {
        margin-top: .4rem;
        display: flex;
        align-items: center;
        gap: .4rem;
        flex-wrap: wrap;
        color: #64748b;
        font-size: .76rem;
        font-weight: 700;
    }

    .suggestion-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        padding: .22rem .5rem;
    }

    .suggestion-empty,
    .suggestion-loading {
        padding: .85rem .9rem;
        color: #64748b;
        font-weight: 700;
        font-size: .85rem;
    }

    .item-row {
        display: grid;
        grid-template-columns: 80px 2fr 1fr 1fr 1fr 1.2fr auto;
        gap: 1rem;
        margin-bottom: 1rem;
        align-items: end;
        padding: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: #fff;
    }

    .budget-info {
        background: #f1f5f9;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .total-calc {
        font-size: 1.25rem;
        font-weight: 700;
    }

    .diff-warning {
        color: #dc3545;
    }

    .diff-ok {
        color: #198754;
    }

    .item-seq {
        text-align: center;
        font-weight: 800;
        background: #f8fafc;
    }

    .item-total {
        background: #f8fafc;
        font-weight: 800;
        color: #198754;
        text-align: right;
    }

    .edit-actions {
        text-align: right;
        border-top: 1px solid var(--border-color);
        padding-top: 2rem;
    }

    @media (max-width: 1200px) {
        .item-row {
            grid-template-columns: 70px 1.8fr 1fr 1fr 1fr 1fr auto;
        }
    }

    @media (max-width: 992px) {
        .oficio-edit-grid,
        .item-row {
            grid-template-columns: 1fr;
        }

        .oficio-edit-span-2 {
            grid-column: span 1;
        }

        .aquisicoes-date-grid {
            grid-template-columns: 1fr;
        }

        .aquisicao-date-card {
            grid-template-columns: 1fr;
        }

        .empresa-fields,
        .empresa-items {
            grid-template-columns: 1fr;
        }

        .budget-info {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="edit-header">
            <h3 style="margin: 0;">
                <i class="fas fa-edit"></i> Editar Solicitação - <?php echo htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($total_aquisicoes_vinculadas === 1): ?>
            <div class="alert alert-warning">
                Esta solicitação já possui a aquisição
                <strong><?php echo htmlspecialchars($aquisicoes_vinculadas[0]['numero_aq'], ENT_QUOTES, 'UTF-8'); ?></strong>
                gerada. Você pode aumentar a quantidade de empresas e distribuir os itens entre fornecedores diferentes.
            </div>
        <?php elseif ($total_aquisicoes_vinculadas > 1): ?>
            <div class="alert alert-warning">
                Esta solicitação já possui
                <strong><?php echo (int)$total_aquisicoes_vinculadas; ?> aquisições</strong>
                geradas. Você pode redistribuir os itens e acrescentar novos fornecedores sem excluir as aquisições existentes.
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="items-form">
            <h4 class="form-section-title">
                <i class="fas fa-file-alt"></i> Dados do Ofício
            </h4>

            <div class="oficio-edit-grid">
                <div class="form-group">
                    <label class="form-label">Número do Ofício <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="numero_oficio"
                        class="form-control"
                        value="<?php echo htmlspecialchars($numero_value, ENT_QUOTES, 'UTF-8'); ?>"
                        oninput="this.value = this.value.toUpperCase()"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Secretaria <span style="color:red">*</span></label>
                    <select name="secretaria_id" class="form-control" required>
                        <option value="">Selecione a secretaria</option>
                        <?php foreach ($secretarias as $sec): ?>
                            <option
                                value="<?php echo (int)$sec['id']; ?>"
                                <?php echo (int)$secretaria_value === (int)$sec['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sec['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Data do Ofício <span style="color:red">*</span></label>
                    <input
                        type="datetime-local"
                        name="criado_em"
                        class="form-control"
                        value="<?php echo htmlspecialchars($criado_em_value, ENT_QUOTES, 'UTF-8'); ?>"
                        required>
                </div>

                <div class="form-group oficio-edit-span-2">
                    <label class="form-label">Local <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="local"
                        class="form-control"
                        placeholder="Ex: Secretaria Municipal de Administração"
                        value="<?php echo htmlspecialchars($local_value, ENT_QUOTES, 'UTF-8'); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Valor do Orçamento</label>
                    <input
                        type="text"
                        name="valor_orcamento"
                        id="valor-orcamento"
                        class="form-control"
                        placeholder="0,00"
                        value="<?php echo htmlspecialchars($orcamento_value, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Status do Ofício <span style="color:red">*</span></label>
                    <select name="status_oficio" class="form-control" required>
                        <?php foreach ($oficio_status_options as $status_value => $status_label): ?>
                            <option
                                value="<?php echo htmlspecialchars($status_value, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $oficio_status_value === $status_value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group oficio-edit-span-2">
                    <label class="form-label">Resumo dos Itens a Cadastrar</label>
                    <textarea
                        name="resumo_itens"
                        class="form-control"
                        placeholder="Ex: material de expediente, gêneros alimentícios, equipamentos, serviços ou observações sobre os itens que serão detalhados depois..."
                        rows="3"><?php echo htmlspecialchars($resumo_itens_value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <small class="text-muted">Use este campo para registrar uma prévia dos itens antes da atribuição detalhada.</small>
                </div>
            </div>

            <?php if (!empty($aquisicoes_vinculadas)): ?>
                <div class="aquisicoes-date-panel">
                    <div class="aquisicoes-section-head">
                        <h4 class="aquisicoes-date-title">
                            <i class="fas fa-building"></i> Distribuição por Fornecedor
                        </h4>
                        <div class="form-group" style="margin:0; min-width:240px;">
                            <label class="form-label" for="qtd-empresas">Quantidade de empresas participantes</label>
                            <input
                                type="number"
                                id="qtd-empresas"
                                class="form-control"
                                min="<?php echo (int)$total_aquisicoes_vinculadas; ?>"
                                max="<?php echo count($fornecedores); ?>"
                                value="<?php echo (int)$qtd_empresas_form; ?>"
                                required>
                        </div>
                    </div>

                    <div id="distribuicao-status" class="distribuicao-status" aria-live="polite"></div>

                    <div id="empresas-container">
                        <?php foreach ($empresas_form as $empresa_idx => $empresa): ?>
                            <?php
                            $empresa_aquisicao_id = (int)($empresa['aquisicao_id'] ?? 0);
                            $empresa_numero = $empresa_aquisicao_id > 0 && isset($aquisicoes_by_id[$empresa_aquisicao_id])
                                ? $aquisicoes_by_id[$empresa_aquisicao_id]['numero_aq']
                                : 'Nova aquisição';
                            $empresa_itens = array_map('strval', $empresa['itens'] ?? []);
                            ?>
                            <div
                                class="empresa-card"
                                data-company-index="<?php echo (int)$empresa_idx; ?>"
                                data-acquisition-number="<?php echo htmlspecialchars($empresa_numero, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="empresas[<?php echo (int)$empresa_idx; ?>][aquisicao_id]" value="<?php echo $empresa_aquisicao_id; ?>">

                                <div class="empresa-card-header">
                                    <h5 class="empresa-card-title">
                                        <i class="fas fa-building"></i>
                                        Empresa <?php echo (int)$empresa_idx + 1; ?>
                                        <small><?php echo htmlspecialchars($empresa_numero, ENT_QUOTES, 'UTF-8'); ?></small>
                                    </h5>
                                    <span class="empresa-total">Total: R$ 0,00</span>
                                </div>

                                <div class="empresa-fields">
                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label">Fornecedor</label>
                                        <select name="empresas[<?php echo (int)$empresa_idx; ?>][fornecedor_id]" class="form-control fornecedor-select" required>
                                            <option value="">Selecione o fornecedor...</option>
                                            <?php foreach ($fornecedores as $fornecedor): ?>
                                                <option value="<?php echo (int)$fornecedor['id']; ?>" <?php echo (int)($empresa['fornecedor_id'] ?? 0) === (int)$fornecedor['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if (!empty($fornecedor['cnpj'])): ?>(<?php echo htmlspecialchars($fornecedor['cnpj'], ENT_QUOTES, 'UTF-8'); ?>)<?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label">Data da Aquisição (igual ao ofício)</label>
                                        <input type="datetime-local" name="empresas[<?php echo (int)$empresa_idx; ?>][criado_em]" class="form-control" value="<?php echo htmlspecialchars($criado_em_value, ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                                    </div>

                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label">Status</label>
                                        <select name="empresas[<?php echo (int)$empresa_idx; ?>][status]" class="form-control" required>
                                            <?php foreach ($aquisicao_status_options as $status_value => $status_label): ?>
                                                <option value="<?php echo htmlspecialchars($status_value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($empresa['status'] ?? 'AGUARDANDO ENTREGA') === $status_value ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="empresa-items">
                                    <?php foreach ($items_form as $item_form_idx => $item_form): ?>
                                        <?php
                                        $item_form_id = (int)($item_form['id'] ?? 0);
                                        $item_form_key = (string)($item_form['key'] ?? ($item_form_id > 0 ? 'item-' . $item_form_id : 'new-initial-' . $item_form_idx));
                                        $item_form_qtd = (float)str_replace(',', '.', (string)($item_form['quantidade_input'] ?? ($item_form['quantidade'] ?? 1)));
                                        try {
                                            $item_form_valor = parse_oficio_money($item_form['valor_input'] ?? ($item_form['valor_unitario'] ?? 0)) ?? 0;
                                        } catch (Exception $e) {
                                            $item_form_valor = 0;
                                        }
                                        $item_form_total = $item_form_qtd * $item_form_valor;
                                        ?>
                                        <label class="item-choice" data-item-key="<?php echo htmlspecialchars($item_form_key, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input
                                                type="checkbox"
                                                class="item-check"
                                                name="empresas[<?php echo (int)$empresa_idx; ?>][itens][]"
                                                value="<?php echo htmlspecialchars($item_form_key, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-item-key="<?php echo htmlspecialchars($item_form_key, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-total="<?php echo htmlspecialchars((string)$item_form_total, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo in_array($item_form_key, $empresa_itens, true) ? 'checked' : ''; ?>>
                                            <span>
                                                <strong class="item-choice-name"><?php echo htmlspecialchars($item_form['produto'] ?? 'Item sem nome', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <small class="item-choice-meta">Subtotal <?php echo format_money($item_form_total); ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="budget-info">
                <div>
                    <span class="text-muted">Secretaria:</span>
                    <strong><?php echo htmlspecialchars($secretaria_nome_atual, ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Data:</span>
                    <strong><?php echo htmlspecialchars($criado_em_label, ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Local:</span>
                    <strong><?php echo htmlspecialchars($local_value !== '' ? $local_value : '-', ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Status atual:</span>
                    <strong><?php echo htmlspecialchars((string)$oficio_status_value, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>

                <div style="text-align: right;">
                    <span class="text-muted">Total Atual dos Itens:</span><br>
                    <span id="total-itens" class="total-calc">R$ 0,00</span>
                </div>
            </div>

            <?php if (!empty($resumo_produtos)): ?>
                <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <h4 style="margin-bottom: 1rem;">
                            <i class="fas fa-chart-bar"></i> Resumo dos Produtos
                        </h4>

                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th style="padding: 12px; text-align:left;">Produto</th>
                                        <th style="padding: 12px; text-align:center;">Unidade</th>
                                        <th style="padding: 12px; text-align:center;">Qtd. de Lançamentos</th>
                                        <th style="padding: 12px; text-align:center;">Quantidade Total</th>
                                        <th style="padding: 12px; text-align:right;">Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumo_produtos as $rp): ?>
                                        <tr style="border-top:1px solid #e5e7eb;">
                                            <td style="padding: 12px; font-weight:600;">
                                                <?php echo htmlspecialchars($rp['produto'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center;">
                                                <?php echo htmlspecialchars($rp['unidade'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center; font-weight:700;">
                                                <?php echo (int)$rp['total_registros']; ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center; font-weight:700;">
                                                <?php echo number_format((float)$rp['quantidade_total'], 2, ',', '.'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:right; font-weight:700; color:#198754;">
                                                <?php echo format_money((float)$rp['valor_total_produto']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h4 class="form-section-title">
                <i class="fas fa-boxes"></i> Itens do Ofício
            </h4>

            <div id="items-container">
                <?php foreach ($items_form as $idx => $it): ?>
                    <?php
                    $qtd_input = $it['quantidade_input'] ?? format_quantity_input($it['quantidade'] ?? 1);
                    $valor_input = $it['valor_input'] ?? format_money_input($it['valor_unitario'] ?? 0);
                    $qtd_item = (float)str_replace(',', '.', (string)$qtd_input);
                    try {
                        $valor_unit_item = parse_oficio_money($valor_input) ?? 0;
                    } catch (Exception $e) {
                        $valor_unit_item = 0;
                    }
                    $valor_total_item = $qtd_item * $valor_unit_item;
                    $item_id_form = (int)($it['id'] ?? 0);
                    $item_key_form = (string)($it['key'] ?? ($item_id_form > 0 ? 'item-' . $item_id_form : 'new-initial-' . $idx));
                    ?>
                    <div class="item-row" data-item-key="<?php echo htmlspecialchars($item_key_form, ENT_QUOTES, 'UTF-8'); ?>" data-calculation-source="unit">
                        <input type="hidden" name="produtos[<?php echo $idx; ?>][id]" value="<?php echo $item_id_form; ?>">
                        <input type="hidden" name="produtos[<?php echo $idx; ?>][key]" value="<?php echo htmlspecialchars($item_key_form, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nº</label>
                            <input type="text" class="form-control item-seq" value="<?php echo $idx + 1; ?>" readonly>
                        </div>

                        <div class="form-group item-name-group" style="margin:0;">
                            <label class="form-label">Nome do Item</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][nome]"
                                class="form-control item-name"
                                required
                                autocomplete="off"
                                placeholder="Ex: Papel A4"
                                value="<?php echo htmlspecialchars($it['produto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="item-suggestions" role="listbox"></div>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Quantidade</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="produtos[<?php echo $idx; ?>][qtd]"
                                class="form-control item-qtd"
                                required
                                value="<?php echo htmlspecialchars((string)$qtd_input, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Unidade</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][unidade]"
                                class="form-control"
                                value="<?php echo htmlspecialchars($it['unidade'] ?? 'UN', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Valor Unitário</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][valor]"
                                class="form-control item-valor"
                                required
                                placeholder="0,00"
                                value="<?php echo htmlspecialchars($valor_input, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Total do Item</label>
                            <input
                                type="text"
                                class="form-control item-total"
                                inputmode="decimal"
                                placeholder="0,00"
                                title="Digite o total para calcular automaticamente o valor unitário"
                                value="<?php echo number_format($valor_total_item, 2, ',', '.'); ?>">
                        </div>

                        <div style="margin-bottom: 5px;">
                            <button
                                type="button"
                                class="btn btn-outline btn-sm remove-item"
                                style="color:red; border-color:#ff000033;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button
                type="button"
                class="btn btn-outline"
                id="add-item"
                style="margin-bottom: 2rem;">
                <i class="fas fa-plus"></i> Adicionar Mais Itens
            </button>

            <div class="edit-actions">
                <button
                    type="submit"
                    class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalDisplay = document.getElementById('total-itens');
    const budgetInput = document.getElementById('valor-orcamento');
    const addButton = document.getElementById('add-item');
    const form = document.getElementById('items-form');
    const empresasContainer = document.getElementById('empresas-container');
    const empresasCountInput = document.getElementById('qtd-empresas');
    const distribuicaoStatus = document.getElementById('distribuicao-status');
    const fornecedores = <?php echo json_encode($fornecedores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const aquisicaoStatusOptions = <?php echo json_encode($aquisicao_status_options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const oficioId = <?php echo (int)$id; ?>;
    const autocompleteTimers = new WeakMap();
    const autocompleteControllers = new WeakMap();
    const minEmpresas = <?php echo (int)$total_aquisicoes_vinculadas; ?>;
    let newItemSequence = Date.now();

    function parseValorBR(valor) {
        if (!valor) return 0;
        let v = String(valor).trim();
        v = v.replace(/\s/g, '');
        v = v.replace(/\./g, '');
        v = v.replace(',', '.');
        return parseFloat(v) || 0;
    }

    function formatMoneyBR(valor) {
        return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatInputMoneyBR(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSuggestionPanel(input) {
        return input.closest('.item-name-group')?.querySelector('.item-suggestions') || null;
    }

    function hideSuggestions(input) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        panel.classList.remove('show');
        panel.innerHTML = '';
        input.dataset.activeSuggestion = '-1';
    }

    function hideAllSuggestions() {
        container.querySelectorAll('.item-name').forEach(input => hideSuggestions(input));
    }

    function setActiveSuggestion(input, nextIndex) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        const options = Array.from(panel.querySelectorAll('.suggestion-option'));
        if (!options.length) return;

        const safeIndex = Math.max(0, Math.min(nextIndex, options.length - 1));
        input.dataset.activeSuggestion = String(safeIndex);

        options.forEach((option, index) => {
            option.classList.toggle('active', index === safeIndex);
        });

        options[safeIndex].scrollIntoView({ block: 'nearest' });
    }

    function showSuggestionMessage(input, message, iconClass) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        panel.innerHTML = `
            <div class="suggestion-loading">
                <i class="${iconClass}"></i> ${escapeHtml(message)}
            </div>
        `;
        panel.classList.add('show');
        input.dataset.activeSuggestion = '-1';
    }

    function renderSuggestions(input, items) {
        const panel = getSuggestionPanel(input);
        if (!panel) return;

        input._itemSuggestions = items;
        input.dataset.activeSuggestion = '-1';

        if (!items.length) {
            panel.innerHTML = `
                <div class="suggestion-empty">
                    <i class="fas fa-search"></i> Nenhum item encontrado no histórico.
                </div>
            `;
            panel.classList.add('show');
            return;
        }

        panel.innerHTML = items.map((item, index) => {
            const valor = Number(item.valor_unitario || 0);
            const valorLabel = valor > 0 ? formatMoneyBR(valor) : 'Sem valor';
            const usos = Number(item.usos || 0);

            return `
                <button type="button" class="suggestion-option" data-index="${index}" role="option">
                    <div class="suggestion-title">
                        <span class="suggestion-name">${escapeHtml(item.produto)}</span>
                        <span class="suggestion-price">${escapeHtml(valorLabel)}</span>
                    </div>
                    <div class="suggestion-meta">
                        <span class="suggestion-chip"><i class="fas fa-ruler-combined"></i> ${escapeHtml(item.unidade || 'UN')}</span>
                        <span class="suggestion-chip"><i class="fas fa-database"></i> ${escapeHtml(item.origem || 'Histórico')}</span>
                        <span class="suggestion-chip"><i class="fas fa-redo"></i> ${usos} uso${usos === 1 ? '' : 's'}</span>
                    </div>
                </button>
            `;
        }).join('');

        panel.classList.add('show');
    }

    function searchItemSuggestions(input) {
        const term = input.value.trim();
        const previousTimer = autocompleteTimers.get(input);

        if (previousTimer) {
            clearTimeout(previousTimer);
        }

        if (term.length < 2) {
            hideSuggestions(input);
            return;
        }

        const timer = setTimeout(async () => {
            const previousController = autocompleteControllers.get(input);
            if (previousController) {
                previousController.abort();
            }

            const controller = new AbortController();
            autocompleteControllers.set(input, controller);

            showSuggestionMessage(input, 'Buscando itens cadastrados...', 'fas fa-spinner fa-spin');

            try {
                const response = await fetch(`oficios_editar.php?id=${encodeURIComponent(oficioId)}&ajax=sugerir_itens&q=${encodeURIComponent(term)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error('Falha na busca');
                }

                const data = await response.json();

                if (input.value.trim() !== term) {
                    return;
                }

                renderSuggestions(input, Array.isArray(data) ? data : []);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                showSuggestionMessage(input, 'Não foi possível carregar sugestões agora.', 'fas fa-exclamation-circle');
            }
        }, 260);

        autocompleteTimers.set(input, timer);
    }

    function applySuggestion(input, item) {
        const row = input.closest('.item-row');
        if (!row || !item) return;

        input.value = item.produto || '';

        const unidadeInput = row.querySelector('input[name$="[unidade]"]');
        const valorInput = row.querySelector('.item-valor');
        const qtdInput = row.querySelector('.item-qtd');

        if (unidadeInput) {
            unidadeInput.value = item.unidade || 'UN';
        }

        if (valorInput && Number(item.valor_unitario || 0) > 0) {
            valorInput.value = formatInputMoneyBR(item.valor_unitario);
            row.dataset.calculationSource = 'unit';
            updateRowFromUnitValue(row);
        }

        hideSuggestions(input);
        calculateTotal();
        syncCompanyItems();

        if (qtdInput) {
            qtdInput.focus();
            qtdInput.select();
        }
    }

    function getItemsState() {
        return Array.from(container.querySelectorAll('.item-row')).map((row, index) => {
            const key = row.dataset.itemKey || `new-${index}`;
            const quantidade = parseFloat(String(row.querySelector('.item-qtd')?.value || '').replace(',', '.')) || 0;
            const valorUnitario = parseValorBR(row.querySelector('.item-valor')?.value);

            return {
                key,
                nome: row.querySelector('.item-name')?.value.trim() || `Item ${index + 1}`,
                quantidade,
                unidade: row.querySelector('input[name$="[unidade]"]')?.value.trim() || 'UN',
                valorUnitario,
                total: quantidade * valorUnitario
            };
        });
    }

    function getEmpresasState() {
        if (!empresasContainer) return [];

        return Array.from(empresasContainer.querySelectorAll('.empresa-card')).map(card => ({
            aquisicao_id: card.querySelector('input[name$="[aquisicao_id]"]')?.value || '0',
            numero: card.dataset.acquisitionNumber || 'Nova aquisição',
            fornecedor_id: card.querySelector('.fornecedor-select')?.value || '',
            criado_em: card.querySelector('input[type="datetime-local"]')?.value || '',
            status: card.querySelector('select[name$="[status]"]')?.value || 'AGUARDANDO ENTREGA',
            itens: Array.from(card.querySelectorAll('.item-check:checked')).map(input => input.dataset.itemKey)
        }));
    }

    function buildFornecedorOptions(selectedId) {
        let options = '<option value="">Selecione o fornecedor...</option>';
        fornecedores.forEach(fornecedor => {
            const selected = String(fornecedor.id) === String(selectedId) ? 'selected' : '';
            const cnpj = fornecedor.cnpj ? ` (${escapeHtml(fornecedor.cnpj)})` : '';
            options += `<option value="${fornecedor.id}" ${selected}>${escapeHtml(fornecedor.nome)}${cnpj}</option>`;
        });
        return options;
    }

    function buildStatusOptions(selectedStatus) {
        return Object.entries(aquisicaoStatusOptions).map(([value, label]) => {
            const selected = value === selectedStatus ? 'selected' : '';
            return `<option value="${escapeHtml(value)}" ${selected}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    function buildItemChoices(companyIndex, selectedKeys) {
        const selected = new Set((selectedKeys || []).map(String));
        return getItemsState().map(item => `
            <label class="item-choice" data-item-key="${escapeHtml(item.key)}">
                <input
                    type="checkbox"
                    class="item-check"
                    name="empresas[${companyIndex}][itens][]"
                    value="${escapeHtml(item.key)}"
                    data-item-key="${escapeHtml(item.key)}"
                    data-total="${item.total}"
                    ${selected.has(String(item.key)) ? 'checked' : ''}>
                <span>
                    <strong class="item-choice-name">${escapeHtml(item.nome)}</strong>
                    <small class="item-choice-meta">${item.quantidade.toLocaleString('pt-BR', { maximumFractionDigits: 2 })} ${escapeHtml(item.unidade)} | Subtotal ${formatMoneyBR(item.total)}</small>
                </span>
            </label>
        `).join('');
    }

    function renderEmpresas(nextCount) {
        if (!empresasContainer || !empresasCountInput) return;

        const previous = getEmpresasState();
        const maxEmpresas = fornecedores.length;
        const count = Math.max(minEmpresas, Math.min(maxEmpresas, parseInt(nextCount, 10) || minEmpresas));
        empresasCountInput.value = count;

        let html = '';
        for (let index = 0; index < count; index++) {
            const state = previous[index] || {
                aquisicao_id: '0',
                numero: 'Nova aquisição',
                fornecedor_id: '',
                criado_em: document.querySelector('input[name="criado_em"]')?.value || '',
                status: 'AGUARDANDO ENTREGA',
                itens: []
            };

            html += `
                <div class="empresa-card" data-company-index="${index}" data-acquisition-number="${escapeHtml(state.numero)}">
                    <input type="hidden" name="empresas[${index}][aquisicao_id]" value="${escapeHtml(state.aquisicao_id)}">
                    <div class="empresa-card-header">
                        <h5 class="empresa-card-title"><i class="fas fa-building"></i> Empresa ${index + 1} <small>${escapeHtml(state.numero)}</small></h5>
                        <span class="empresa-total">Total: R$ 0,00</span>
                    </div>
                    <div class="empresa-fields">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Fornecedor</label>
                            <select name="empresas[${index}][fornecedor_id]" class="form-control fornecedor-select" required>${buildFornecedorOptions(state.fornecedor_id)}</select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Data da Aquisição (igual ao ofício)</label>
                            <input type="datetime-local" name="empresas[${index}][criado_em]" class="form-control" value="${escapeHtml(document.querySelector('input[name="criado_em"]')?.value || '')}" readonly required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Status</label>
                            <select name="empresas[${index}][status]" class="form-control" required>${buildStatusOptions(state.status)}</select>
                        </div>
                    </div>
                    <div class="empresa-items">${buildItemChoices(index, state.itens)}</div>
                </div>
            `;
        }

        empresasContainer.innerHTML = html;
        updateDistribution();
    }

    function syncCompanyItems() {
        if (!empresasContainer) return;

        getEmpresasState().forEach((state, index) => {
            const card = empresasContainer.querySelectorAll('.empresa-card')[index];
            const itemsArea = card?.querySelector('.empresa-items');
            if (itemsArea) {
                itemsArea.innerHTML = buildItemChoices(index, state.itens);
            }
        });
        updateDistribution();
    }

    function updateDistribution() {
        if (!empresasContainer || !distribuicaoStatus) return;

        const selectedByItem = {};
        empresasContainer.querySelectorAll('.item-check:checked').forEach(input => {
            const key = input.dataset.itemKey;
            selectedByItem[key] = selectedByItem[key] || [];
            selectedByItem[key].push(input);
        });

        empresasContainer.querySelectorAll('.item-check').forEach(input => {
            const selectedElsewhere = selectedByItem[input.dataset.itemKey]?.some(selected => selected !== input);
            input.disabled = Boolean(selectedElsewhere);
            input.closest('.item-choice')?.classList.toggle('is-disabled', Boolean(selectedElsewhere));
        });

        empresasContainer.querySelectorAll('.empresa-card').forEach(card => {
            let total = 0;
            card.querySelectorAll('.item-check:checked').forEach(input => {
                total += Number(input.dataset.total || 0);
            });
            const totalElement = card.querySelector('.empresa-total');
            if (totalElement) totalElement.textContent = 'Total: ' + formatMoneyBR(total);
        });

        const totalItems = getItemsState().length;
        const assignedCount = Object.keys(selectedByItem).length;
        distribuicaoStatus.textContent = `Itens distribuídos: ${assignedCount} de ${totalItems}`;
        const complete = assignedCount === totalItems;
        distribuicaoStatus.style.background = complete ? '#ecfdf5' : '#eff6ff';
        distribuicaoStatus.style.borderColor = complete ? '#bbf7d0' : '#bfdbfe';
        distribuicaoStatus.style.color = complete ? '#166534' : '#1e40af';
    }

    function renumberItems() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            const seqInput = row.querySelector('.item-seq');
            if (seqInput) {
                seqInput.value = index + 1;
            }

            row.querySelectorAll('input[name^="produtos["]').forEach(input => {
                input.name = input.name.replace(/produtos\[\d+\]/, `produtos[${index}]`);
            });
        });
    }

    function getItemQuantity(row) {
        return parseFloat(String(row.querySelector('.item-qtd')?.value || '').replace(',', '.')) || 0;
    }

    function updateRowFromUnitValue(row) {
        const qtd = getItemQuantity(row);
        const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
        const totalField = row.querySelector('.item-total');

        if (totalField) {
            totalField.value = formatInputMoneyBR(qtd * valorUnit);
        }
    }

    function updateRowFromTotalValue(row, normalizeTotal = false) {
        const qtd = getItemQuantity(row);
        const totalItem = parseValorBR(row.querySelector('.item-total')?.value);
        const valorField = row.querySelector('.item-valor');
        const totalField = row.querySelector('.item-total');

        if (valorField) {
            valorField.value = formatInputMoneyBR(qtd > 0 ? totalItem / qtd : 0);
        }

        if (normalizeTotal && totalField && qtd > 0) {
            totalField.value = formatInputMoneyBR(qtd * parseValorBR(valorField?.value));
        }
    }

    function updateRowByCalculationSource(row, normalizeTotal = false) {
        if (row.dataset.calculationSource === 'total') {
            updateRowFromTotalValue(row, normalizeTotal);
            return;
        }

        updateRowFromUnitValue(row);
    }

    function calculateTotal() {
        let total = 0;
        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');

        container.querySelectorAll('.item-row').forEach(row => {
            total += parseValorBR(row.querySelector('.item-total')?.value);
        });

        totalDisplay.textContent = formatMoneyBR(total);

        if (orcamentoPrevisto > 0) {
            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                totalDisplay.classList.add('diff-warning');
                totalDisplay.classList.remove('diff-ok');
            } else {
                totalDisplay.classList.add('diff-ok');
                totalDisplay.classList.remove('diff-warning');
            }
        } else {
            totalDisplay.classList.remove('diff-warning', 'diff-ok');
        }

    }

    container.addEventListener('input', function(e) {
        const row = e.target.closest('.item-row');

        if (e.target.classList.contains('item-valor') || e.target.classList.contains('item-total')) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
        }

        if (row && e.target.classList.contains('item-valor')) {
            row.dataset.calculationSource = 'unit';
            updateRowFromUnitValue(row);
        } else if (row && e.target.classList.contains('item-total')) {
            row.dataset.calculationSource = 'total';
            updateRowFromTotalValue(row);
        } else if (row && e.target.classList.contains('item-qtd')) {
            updateRowByCalculationSource(row, true);
        }

        if (e.target.classList.contains('item-name')) {
            searchItemSuggestions(e.target);
        }

        calculateTotal();
        syncCompanyItems();
    });

    container.addEventListener('focusout', function(e) {
        if (!e.target.classList.contains('item-valor') && !e.target.classList.contains('item-total')) {
            return;
        }

        const row = e.target.closest('.item-row');
        e.target.value = formatInputMoneyBR(parseValorBR(e.target.value));

        if (row && e.target.classList.contains('item-total')) {
            updateRowFromTotalValue(row, true);
        } else if (row) {
            updateRowFromUnitValue(row);
        }

        calculateTotal();
        syncCompanyItems();
    });

    container.addEventListener('focusin', function(e) {
        if (e.target.classList.contains('item-name') && e.target.value.trim().length >= 2) {
            container.querySelectorAll('.item-name').forEach(input => {
                if (input !== e.target) {
                    hideSuggestions(input);
                }
            });
            searchItemSuggestions(e.target);
        }
    });

    container.addEventListener('keydown', function(e) {
        if (!e.target.classList.contains('item-name')) {
            return;
        }

        const input = e.target;
        const panel = getSuggestionPanel(input);
        if (!panel || !panel.classList.contains('show')) {
            return;
        }

        const options = Array.from(panel.querySelectorAll('.suggestion-option'));
        if (!options.length) {
            return;
        }

        const currentIndex = parseInt(input.dataset.activeSuggestion || '-1', 10);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveSuggestion(input, currentIndex + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveSuggestion(input, currentIndex <= 0 ? options.length - 1 : currentIndex - 1);
        } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            const item = input._itemSuggestions?.[currentIndex];
            applySuggestion(input, item);
        } else if (e.key === 'Escape') {
            hideSuggestions(input);
        }
    });

    container.addEventListener('mousedown', function(e) {
        const option = e.target.closest('.suggestion-option');
        if (!option) {
            return;
        }

        e.preventDefault();
        const group = option.closest('.item-name-group');
        const input = group?.querySelector('.item-name');
        const item = input?._itemSuggestions?.[parseInt(option.dataset.index || '-1', 10)];
        applySuggestion(input, item);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.item-name-group')) {
            hideAllSuggestions();
        }
    });

    if (empresasCountInput) {
        empresasCountInput.addEventListener('change', function() {
            renderEmpresas(this.value);
        });
    }

    if (empresasContainer) {
        empresasContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-check') || e.target.classList.contains('fornecedor-select')) {
                updateDistribution();
            }
        });
    }

    if (budgetInput) {
        budgetInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
            calculateTotal();
        });
    }

    if (addButton) {
        addButton.addEventListener('click', function() {
            const index = container.querySelectorAll('.item-row').length;
            const itemKey = `new-${newItemSequence++}`;
            const row = document.createElement('div');
            row.className = 'item-row';
            row.dataset.itemKey = itemKey;
            row.dataset.calculationSource = 'unit';
            row.innerHTML = `
                <input type="hidden" name="produtos[${index}][id]" value="0">
                <input type="hidden" name="produtos[${index}][key]" value="${itemKey}">

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Nº</label>
                    <input type="text" class="form-control item-seq" value="${index + 1}" readonly>
                </div>

                <div class="form-group item-name-group" style="margin:0;">
                    <label class="form-label">Nome do Item</label>
                    <input type="text" name="produtos[${index}][nome]" class="form-control item-name" required autocomplete="off" placeholder="Ex: Papel A4">
                    <div class="item-suggestions" role="listbox"></div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Quantidade</label>
                    <input type="number" step="0.01" min="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Unidade</label>
                    <input type="text" name="produtos[${index}][unidade]" class="form-control" value="UN">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Valor Unitário</label>
                    <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Total do Item</label>
                    <input type="text" class="form-control item-total" inputmode="decimal" placeholder="0,00" title="Digite o total para calcular automaticamente o valor unitário" value="0,00">
                </div>

                <div style="margin-bottom: 5px;">
                    <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            container.appendChild(row);
            renumberItems();
            calculateTotal();
            syncCompanyItems();
        });
    }

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const rows = container.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                renumberItems();
                calculateTotal();
                syncCompanyItems();
            }
        }
    });

    form.addEventListener('submit', function(e) {
        renumberItems();
        container.querySelectorAll('.item-row').forEach(row => {
            updateRowByCalculationSource(row, true);
        });
        calculateTotal();

        if (empresasContainer) {
            updateDistribution();
            const suppliers = new Set();
            const selectedItems = new Set();
            let distributionError = '';

            empresasContainer.querySelectorAll('.empresa-card').forEach((card, index) => {
                const supplier = card.querySelector('.fornecedor-select')?.value || '';
                const checkedItems = card.querySelectorAll('.item-check:checked');

                if (!distributionError && !supplier) {
                    distributionError = `Selecione o fornecedor da empresa ${index + 1}.`;
                } else if (!distributionError && suppliers.has(supplier)) {
                    distributionError = 'O mesmo fornecedor não pode ser usado em mais de uma aquisição.';
                }

                if (supplier) suppliers.add(supplier);

                if (!distributionError && checkedItems.length === 0) {
                    distributionError = `Selecione pelo menos um item para a empresa ${index + 1}.`;
                }

                checkedItems.forEach(input => selectedItems.add(input.dataset.itemKey));
            });

            if (!distributionError && selectedItems.size !== getItemsState().length) {
                distributionError = 'Distribua todos os itens entre os fornecedores antes de salvar.';
            }

            if (distributionError) {
                e.preventDefault();
                alert(distributionError);
                return false;
            }
        }

        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');
        if (orcamentoPrevisto > 0) {
            let total = 0;

            container.querySelectorAll('.item-row').forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
                const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
                total += (qtd * valorUnit);
            });

            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                e.preventDefault();
                alert("Bloqueado: O valor total atual dos itens não corresponde ao Valor do Orçamento Previsto.");
                return false;
            }
        }
    });

    renumberItems();
    calculateTotal();
    updateDistribution();
});
</script>

<?php include 'views/layout/footer.php'; ?>
