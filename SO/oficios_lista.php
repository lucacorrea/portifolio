<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Solicitações";
$nivel_user = strtoupper($_SESSION['nivel'] ?? '');
$status_options = ['PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO'];

function oficios_lista_return_url(array $source, array $status_options): string {
    $safe = [];

    if (isset($source['busca']) && is_scalar($source['busca'])) {
        $safe['busca'] = substr(trim((string)$source['busca']), 0, 120);
    }

    if (isset($source['status']) && is_scalar($source['status']) && in_array((string)$source['status'], $status_options, true)) {
        $safe['status'] = (string)$source['status'];
    }

    if (isset($source['secretaria_id']) && is_numeric($source['secretaria_id']) && (int)$source['secretaria_id'] > 0) {
        $safe['secretaria_id'] = (int)$source['secretaria_id'];
    }

    if (isset($source['fornecedor_id']) && is_numeric($source['fornecedor_id']) && (int)$source['fornecedor_id'] > 0) {
        $safe['fornecedor_id'] = (int)$source['fornecedor_id'];
    }

    foreach (['data_inicio', 'data_fim'] as $dateKey) {
        if (isset($source[$dateKey]) && is_scalar($source[$dateKey]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$source[$dateKey])) {
            $safe[$dateKey] = (string)$source[$dateKey];
        }
    }

    if (isset($source['page']) && is_numeric($source['page']) && (int)$source['page'] > 1) {
        $safe['page'] = (int)$source['page'];
    }

    $query = http_build_query($safe);
    return 'oficios_lista.php' . ($query !== '' ? '?' . $query : '');
}

function oficios_lista_local_upload_path(?string $path): ?string {
    $path = trim((string)$path);

    if ($path === '') {
        return null;
    }

    $urlPath = parse_url($path, PHP_URL_PATH);
    $path = str_replace('\\', '/', $urlPath !== false && $urlPath !== null ? $urlPath : $path);
    $path = ltrim($path, '/');

    $uploadsRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));

    if ($uploadsRoot === false || $candidate === false || !is_file($candidate)) {
        return null;
    }

    $uploadsRoot = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($candidate, $uploadsRoot, strlen($uploadsRoot)) !== 0) {
        return null;
    }

    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_oficio'])) {
    $return_source = [];
    parse_str((string)($_POST['return_query'] ?? ''), $return_source);
    $return_url = oficios_lista_return_url($return_source, $status_options);

    if ($nivel_user !== 'SUPORTE') {
        flash_message('danger', 'Apenas usuário suporte pode excluir ofícios e aquisições vinculadas.');
        header("Location: {$return_url}");
        exit();
    }

    $oficio_id = (int)($_POST['oficio_id'] ?? 0);
    $senha_suporte = (string)($_POST['senha_suporte'] ?? '');

    if ($oficio_id <= 0 || $senha_suporte === '') {
        flash_message('danger', 'Informe a senha do suporte para confirmar a exclusão.');
        header("Location: {$return_url}");
        exit();
    }

    $stmt_user = $pdo->prepare("SELECT senha, nivel FROM usuarios WHERE id = ?");
    $stmt_user->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $support_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$support_user || strtoupper((string)$support_user['nivel']) !== 'SUPORTE' || !password_verify($senha_suporte, (string)$support_user['senha'])) {
        flash_message('danger', 'Senha do suporte inválida. Nenhum registro foi excluído.');
        header("Location: {$return_url}");
        exit();
    }

    $local_files_to_remove = [];

    try {
        $pdo->beginTransaction();

        $stmt_oficio = $pdo->prepare("
            SELECT id, numero, arquivo_orcamento, arquivo_oficio
            FROM oficios
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt_oficio->execute([$oficio_id]);
        $oficio_delete = $stmt_oficio->fetch(PDO::FETCH_ASSOC);

        if (!$oficio_delete) {
            $pdo->rollBack();
            flash_message('danger', 'Ofício não encontrado ou já excluído.');
            header("Location: {$return_url}");
            exit();
        }

        $stmt_anexos = $pdo->prepare("SELECT caminho FROM oficio_anexos WHERE oficio_id = ?");
        $stmt_anexos->execute([$oficio_id]);
        foreach ($stmt_anexos->fetchAll(PDO::FETCH_COLUMN) as $anexo_path) {
            $local_files_to_remove[] = $anexo_path;
        }
        $local_files_to_remove[] = $oficio_delete['arquivo_orcamento'] ?? null;
        $local_files_to_remove[] = $oficio_delete['arquivo_oficio'] ?? null;

        $stmt_count_aq = $pdo->prepare("SELECT COUNT(*) FROM aquisicoes WHERE oficio_id = ?");
        $stmt_count_aq->execute([$oficio_id]);
        $aquisicoes_count = (int)$stmt_count_aq->fetchColumn();

        $pdo->prepare("
            DELETE ia
            FROM itens_aquisicao ia
            INNER JOIN aquisicoes a ON a.id = ia.aquisicao_id
            WHERE a.oficio_id = ?
        ")->execute([$oficio_id]);

        $pdo->prepare("DELETE FROM aquisicoes WHERE oficio_id = ?")->execute([$oficio_id]);
        $pdo->prepare("DELETE FROM oficios WHERE id = ?")->execute([$oficio_id]);

        log_action(
            $pdo,
            'EXCLUSAO_OFICIO_AQUISICAO',
            'Ofício ' . $oficio_delete['numero'] . ' removido com ' . $aquisicoes_count . ' aquisição(ões) vinculada(s).'
        );

        $pdo->commit();

        $removed_files = [];
        foreach (array_unique(array_filter($local_files_to_remove)) as $file_path) {
            $local_file = oficios_lista_local_upload_path($file_path);
            if ($local_file !== null && !isset($removed_files[$local_file])) {
                @unlink($local_file);
                $removed_files[$local_file] = true;
            }
        }

        flash_message('success', 'Ofício e aquisição(ões) vinculada(s) excluídos com sucesso.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash_message('danger', 'Erro ao excluir o ofício. Verifique vínculos existentes e tente novamente.');
    }

    header("Location: {$return_url}");
    exit();
}

// Filtros simples
$where_clauses = ["TRUE"];
$params = [];
$busca_texto = trim((string)($_GET['busca'] ?? ''));
$status_filtro = trim((string)($_GET['status'] ?? ''));
$secretaria_id_filtro = trim((string)($_GET['secretaria_id'] ?? ''));
$fornecedor_id_filtro = trim((string)($_GET['fornecedor_id'] ?? ''));
$data_inicio_filtro = trim((string)($_GET['data_inicio'] ?? ''));
$data_fim_filtro = trim((string)($_GET['data_fim'] ?? ''));
$data_inicio_valida = $data_inicio_filtro !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio_filtro);
$data_fim_valida = $data_fim_filtro !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim_filtro);

function parse_money_filter_value($valor): ?float {
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
        return null;
    }

    return (float)$valor;
}

function secretaria_sigla_label($nome): string {
    $nome = trim((string)$nome);

    if ($nome === '') {
        return '';
    }

    if (preg_match('/-\s*([A-Z0-9]{2,15})\s*$/u', $nome, $matches)) {
        return $matches[1];
    }

    return $nome;
}

if ($status_filtro !== '' && in_array($status_filtro, $status_options, true)) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status_filtro;
}
if ($busca_texto !== '') {
    $busca = '%' . $busca_texto . '%';
    $valor_busca = parse_money_filter_value($busca_texto);

    $valor_clauses = ["CAST(o.valor_orcamento AS CHAR) LIKE ?"];
    $params_busca = [$busca, $busca, $busca, $busca];

    if ($valor_busca !== null) {
        $valor_clauses[] = "ABS(COALESCE(o.valor_orcamento, 0) - ?) < 0.01";
        $params_busca[] = $valor_busca;
    }

    $where_clauses[] = "(
        o.numero LIKE ?
        OR s.nome LIKE ?
        OR EXISTS (
            SELECT 1
            FROM aquisicoes aq_busca
            INNER JOIN fornecedores f_busca ON f_busca.id = aq_busca.fornecedor_id
            WHERE aq_busca.oficio_id = o.id
              AND f_busca.nome LIKE ?
        )
        OR " . implode(' OR ', $valor_clauses) . "
    )";
    foreach ($params_busca as $param_busca) {
        $params[] = $param_busca;
    }
}
if ($secretaria_id_filtro !== '' && ctype_digit($secretaria_id_filtro) && (int)$secretaria_id_filtro > 0) {
    $where_clauses[] = "o.secretaria_id = ?";
    $params[] = (int)$secretaria_id_filtro;
}
if ($fornecedor_id_filtro !== '' && ctype_digit($fornecedor_id_filtro) && (int)$fornecedor_id_filtro > 0) {
    $where_clauses[] = "EXISTS (
        SELECT 1
        FROM aquisicoes aq_filtro
        WHERE aq_filtro.oficio_id = o.id
          AND aq_filtro.fornecedor_id = ?
    )";
    $params[] = (int)$fornecedor_id_filtro;
}
if ($data_inicio_valida) {
    $where_clauses[] = "o.criado_em >= ?";
    $params[] = $data_inicio_filtro . ' 00:00:00';
}
if ($data_fim_valida) {
    $where_clauses[] = "o.criado_em <= ?";
    $params[] = $data_fim_filtro . ' 23:59:59';
}

$where = implode(' AND ', $where_clauses);

$secretarias_list = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll();
$fornecedores_list = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll();

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagina_atual = max(1, $pagina_atual);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM oficios o JOIN secretarias s ON o.secretaria_id = s.id WHERE $where");
$stmt_count->execute($params);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

// Query principal com LIMIT
$stmt = $pdo->prepare("
    SELECT
        o.*,
        s.nome as secretaria,
        u.nome as usuario,
        fornecedores_oficio.fornecedores
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN usuarios u ON o.usuario_id = u.id
    LEFT JOIN (
        SELECT
            aq.oficio_id,
            GROUP_CONCAT(DISTINCT f.nome ORDER BY f.nome ASC SEPARATOR ', ') AS fornecedores
        FROM aquisicoes aq
        INNER JOIN fornecedores f ON f.id = aq.fornecedor_id
        GROUP BY aq.oficio_id
    ) fornecedores_oficio ON fornecedores_oficio.oficio_id = o.id
    WHERE $where
    ORDER BY o.criado_em DESC
    LIMIT $itens_por_pagina OFFSET $offset
");
$stmt->execute($params);
$oficios = $stmt->fetchAll();

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'views/layout/header.php';
?>

<style>
    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filtro-busca {
        grid-column: span 3;
    }

    .filtro-status {
        grid-column: span 2;
    }

    .filtro-secretaria {
        grid-column: span 3;
    }

    .filtro-fornecedor {
        grid-column: span 4;
    }

    .filtro-data {
        grid-column: span 2;
    }

    .filtros-acoes {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        grid-column: span 8;
    }

    .filtros-acoes .btn {
        min-height: 40px;
        justify-content: center;
        white-space: nowrap;
    }

    @media (max-width: 1200px) {
        .filtro-busca,
        .filtro-fornecedor {
            grid-column: span 6;
        }

        .filtro-status,
        .filtro-secretaria,
        .filtro-data {
            grid-column: span 3;
        }

        .filtros-acoes {
            grid-column: span 6;
        }
    }

    .lista-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .lista-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table,
    .lista-table th,
    .lista-table td,
    .lista-table span,
    .lista-table a,
    .lista-table .badge {
        white-space: nowrap !important;
    }

    .lista-table {
        min-width: 1240px;
    }

    .fornecedor-lista {
        display: inline-block;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: bottom;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        align-items: center;
    }

    .btn-delete-oficio {
        color: #b91c1c;
        border-color: #fecaca;
    }

    .btn-delete-oficio:hover {
        color: #991b1b;
        border-color: #ef4444;
        background: #fef2f2;
    }

    .modal-backdrop-oficio {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.55);
    }

    .modal-backdrop-oficio.is-open {
        display: flex;
    }

    .modal-oficio {
        width: 100%;
        max-width: 460px;
        background: var(--white);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
    }

    .modal-oficio-header,
    .modal-oficio-body,
    .modal-oficio-footer {
        padding: 1rem 1.25rem;
    }

    .modal-oficio-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-oficio-title {
        margin: 0;
        color: var(--text-dark);
        font-size: 1rem;
        font-weight: 700;
    }

    .modal-oficio-close {
        width: 34px;
        height: 34px;
        padding: 0;
        justify-content: center;
    }

    .modal-oficio-alert {
        margin-bottom: 1rem;
        padding: 0.75rem;
        border: 1px solid #fecaca;
        border-radius: 6px;
        background: #fef2f2;
        color: #991b1b;
        font-size: 0.825rem;
        line-height: 1.45;
    }

    .modal-oficio-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
    }

    .btn-danger-action {
        background: #dc2626;
        border-color: #dc2626;
        color: var(--white);
    }

    .btn-danger-action:hover {
        background: #b91c1c;
        border-color: #b91c1c;
    }

    .paginacao-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        flex-wrap: wrap;
    }

    .paginacao-info {
        font-weight: 600;
        color: var(--text-dark);
    }

    .btn-limpar {
        width: 100%;
    }

    @media (max-width: 768px) {
        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .filtro-busca,
        .filtro-status,
        .filtro-secretaria,
        .filtro-fornecedor,
        .filtro-data,
        .filtros-acoes {
            grid-column: span 1;
        }

        .lista-header {
            flex-direction: column;
            align-items: stretch;
        }

        .lista-header .btn {
            width: 100%;
            justify-content: center;
        }

        .filtros-acoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .filtros-acoes .btn {
            width: 100%;
        }

        .paginacao-box {
            flex-direction: column;
        }

        .paginacao-box .btn {
            width: 100%;
            max-width: 260px;
            justify-content: center;
        }
    }
</style>

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group filtro-busca" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input type="text" name="busca" class="form-control" placeholder="Número, secretaria, fornecedor ou valor..." value="<?php echo htmlspecialchars($busca_texto, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group filtro-status" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="PENDENTE_ITENS" <?php echo $status_filtro === 'PENDENTE_ITENS' ? 'selected' : ''; ?>>PENDENTE_ITENS</option>
                    <option value="ENVIADO" <?php echo $status_filtro === 'ENVIADO' ? 'selected' : ''; ?>>ENVIADO</option>
                    <option value="EM_ANALISE" <?php echo $status_filtro === 'EM_ANALISE' ? 'selected' : ''; ?>>EM_ANALISE</option>
                    <option value="APROVADO" <?php echo $status_filtro === 'APROVADO' ? 'selected' : ''; ?>>APROVADO</option>
                    <option value="REPROVADO" <?php echo $status_filtro === 'REPROVADO' ? 'selected' : ''; ?>>REPROVADO</option>
                    <option value="ARQUIVADO" <?php echo $status_filtro === 'ARQUIVADO' ? 'selected' : ''; ?>>ARQUIVADO</option>
                </select>
            </div>

            <div class="form-group filtro-secretaria" style="margin-bottom: 0;">
                <label class="form-label">Secretaria</label>
                <select name="secretaria_id" class="form-control">
                    <option value="">Todas as Secretarias</option>
                    <?php foreach ($secretarias_list as $sec): ?>
                        <option value="<?php echo $sec['id']; ?>" <?php echo $secretaria_id_filtro == $sec['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sec['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-fornecedor" style="margin-bottom: 0;">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor_id" class="form-control">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach ($fornecedores_list as $fornecedor): ?>
                        <option value="<?php echo (int)$fornecedor['id']; ?>" <?php echo $fornecedor_id_filtro == $fornecedor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($fornecedor['cnpj'])): ?>
                                (<?php echo htmlspecialchars($fornecedor['cnpj'], ENT_QUOTES, 'UTF-8'); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data inicial</label>
                <input
                    type="date"
                    name="data_inicio"
                    class="form-control"
                    value="<?php echo htmlspecialchars($data_inicio_valida ? $data_inicio_filtro : '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data final</label>
                <input
                    type="date"
                    name="data_fim"
                    class="form-control"
                    value="<?php echo htmlspecialchars($data_fim_valida ? $data_fim_filtro : '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group filtros-acoes" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-outline btn-sm" title="Filtrar">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="oficios_lista.php" class="btn btn-outline btn-sm" title="Limpar Filtros">
                    <i class="fas fa-eraser"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="lista-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem; margin: 0;">
                <i class="fas fa-list-ul" style="margin-right: 10px; color: var(--primary);"></i> Solicitações Recebidas
            </h3>
            <?php if (strtoupper($_SESSION['nivel'] ?? '') !== 'SECRETARIO'): ?>
                <a href="oficios_novo.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Novo Cadastro
                </a>
            <?php endif; ?>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive lista-table-wrap">
            <table class="table-vcenter text-nowrap lista-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Secretaria</th>
                        <th>Fornecedor</th>
                        <th>Data</th>
                        <th style="text-align: right;">Valor</th>
                        <th>Status</th>
                        <th>Cadastrado por</th>
                        <th class="w-1">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oficios as $o): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--primary);">
                                <?php echo htmlspecialchars($o['numero'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <span class="text-muted" title="<?php echo htmlspecialchars($o['secretaria'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(secretaria_sigla_label($o['secretaria']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <?php $fornecedores_oficio = trim((string)($o['fornecedores'] ?? '')); ?>
                                <span class="text-muted fornecedor-lista" title="<?php echo htmlspecialchars($fornecedores_oficio !== '' ? $fornecedores_oficio : 'Sem aquisição vinculada', ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($fornecedores_oficio !== '' ? $fornecedores_oficio : '---', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($o['criado_em']); ?></td>
                            <td style="text-align: right; font-weight: 700; color: #157347;">
                                <?php echo $o['valor_orcamento'] !== null && $o['valor_orcamento'] !== '' ? format_money($o['valor_orcamento']) : '---'; ?>
                            </td>
                            <td>
                                <?php
                                $status_badge = 'pending';
                                if ($o['status'] == 'APROVADO') {
                                    $status_badge = 'approved';
                                } elseif (in_array($o['status'], ['REPROVADO', 'ARQUIVADO'], true)) {
                                    $status_badge = 'rejected';
                                }
                                ?>
                                <span class="badge badge-<?php echo $status_badge; ?>">
                                    <?php echo htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted"><?php echo htmlspecialchars($o['usuario'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <div class="acoes-wrap">
                                    <a href="oficios_visualizar.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Visualizar/Imprimir">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if (in_array($nivel_user, ['ADMIN', 'SUPORTE'], true)): ?>
                                        <a href="oficios_editar.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Editar Ofício e Itens">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php 
                                        if ($o['status'] == 'ENVIADO' && ($nivel_user == 'ADMIN' || $nivel_user == 'SUPORTE')): 
                                    ?>
                                        <a href="analisar_oficio.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Analisar">
                                            <i class="fas fa-gavel"></i> Analisar
                                        </a>
                                    <?php endif; ?>
 
                                    <a href="oficios_anexar.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Anexar Ofício de Solicitação" style="color: var(--secondary); border-color: var(--secondary);">
                                        <i class="fas fa-paperclip"></i>
                                    </a>

                                    <?php if ($o['status'] == 'APROVADO' && ($nivel_user == 'ADMIN' || $nivel_user == 'SUPORTE')): ?>
                                        <a href="gerar_aquisicao.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline btn-sm" title="Gerar Aquisição">
                                            <i class="fas fa-shopping-cart"></i> Gerar
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($nivel_user === 'SUPORTE'): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm btn-delete-oficio"
                                            title="Excluir ofício e aquisições vinculadas"
                                            data-delete-oficio
                                            data-oficio-id="<?php echo (int)$o['id']; ?>"
                                            data-oficio-numero="<?php echo htmlspecialchars($o['numero'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($oficios)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 2rem; color: var(--text-muted);">
                                Nenhuma solicitação encontrada.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-box">
                <a href="<?php echo $pagina_atual > 1 ? get_pagination_url($pagina_atual - 1) : '#'; ?>"
                   class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>

                <span class="paginacao-info">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </span>

                <a href="<?php echo $pagina_atual < $total_paginas ? get_pagination_url($pagina_atual + 1) : '#'; ?>"
                   class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($nivel_user === 'SUPORTE'): ?>
    <div class="modal-backdrop-oficio" id="delete-oficio-modal" aria-hidden="true">
        <div class="modal-oficio" role="dialog" aria-modal="true" aria-labelledby="delete-oficio-title">
            <form method="POST" id="delete-oficio-form" autocomplete="off">
                <input type="hidden" name="delete_oficio" value="1">
                <input type="hidden" name="oficio_id" id="delete-oficio-id" value="">
                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-oficio-header">
                    <h3 class="modal-oficio-title" id="delete-oficio-title">Confirmar exclusão</h3>
                    <button type="button" class="btn btn-outline btn-sm modal-oficio-close" data-close-delete-modal aria-label="Fechar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-oficio-body">
                    <div class="modal-oficio-alert">
                        Esta ação removerá o ofício <strong id="delete-oficio-numero"></strong>, suas aquisições vinculadas e os itens/anexos relacionados. A operação não pode ser desfeita.
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="senha-suporte" class="form-label">Senha do suporte</label>
                        <input type="password" name="senha_suporte" id="senha-suporte" class="form-control" required autocomplete="current-password">
                    </div>
                </div>

                <div class="modal-oficio-footer">
                    <button type="button" class="btn btn-outline btn-sm" data-close-delete-modal>Cancelar</button>
                    <button type="submit" class="btn btn-danger-action btn-sm">
                        <i class="fas fa-trash"></i> Excluir definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('delete-oficio-modal');
        const form = document.getElementById('delete-oficio-form');
        const oficioId = document.getElementById('delete-oficio-id');
        const oficioNumero = document.getElementById('delete-oficio-numero');
        const senhaInput = document.getElementById('senha-suporte');

        if (!modal || !form || !oficioId || !oficioNumero || !senhaInput) {
            return;
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            form.reset();
            oficioId.value = '';
            oficioNumero.textContent = '';
        }

        document.querySelectorAll('[data-delete-oficio]').forEach(function (button) {
            button.addEventListener('click', function () {
                oficioId.value = button.getAttribute('data-oficio-id') || '';
                oficioNumero.textContent = button.getAttribute('data-oficio-numero') || '';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                setTimeout(function () {
                    senhaInput.focus();
                }, 50);
            });
        });

        document.querySelectorAll('[data-close-delete-modal]').forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
    </script>
<?php endif; ?>

<?php include 'views/layout/footer.php'; ?>
