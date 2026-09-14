<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
sefaz_check();

$page_title = 'Lista de Solicitações - SEMFAZ';

$status_options = ['PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO'];
$por_pagina_options = [6, 10, 25, 50, 100];

$busca = trim((string)($_GET['busca'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$secretaria = trim((string)($_GET['secretaria'] ?? ''));
$fornecedor = trim((string)($_GET['fornecedor'] ?? ''));
$data_inicio = trim((string)($_GET['data_inicio'] ?? ''));
$data_fim = trim((string)($_GET['data_fim'] ?? ''));
$por_pagina_raw = trim((string)($_GET['por_pagina'] ?? '10'));
$itens_por_pagina = ctype_digit($por_pagina_raw) && in_array((int)$por_pagina_raw, $por_pagina_options, true)
    ? (int)$por_pagina_raw
    : 10;
$pagina_atual = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

if ($status !== '' && in_array($status, $status_options, true)) {
    $where[] = 'o.status = :status';
    $params[':status'] = $status;
}
if ($busca !== '') {
    $where[] = '(o.numero LIKE :busca OR s.nome LIKE :busca OR COALESCE(f.nome, \'\') LIKE :busca OR o.local LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($secretaria !== '' && ctype_digit($secretaria) && (int)$secretaria > 0) {
    $where[] = 'o.secretaria_id = :secretaria';
    $params[':secretaria'] = (int)$secretaria;
}
if ($fornecedor !== '' && ctype_digit($fornecedor) && (int)$fornecedor > 0) {
    $where[] = 'o.fornecedor_indicado_id = :fornecedor';
    $params[':fornecedor'] = (int)$fornecedor;
}
if ($data_inicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
    $where[] = 'o.criado_em >= :data_inicio';
    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
}
if ($data_fim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
    $where[] = 'o.criado_em <= :data_fim';
    $params[':data_fim'] = $data_fim . ' 23:59:59';
}

$where_sql = implode(' AND ', $where);

$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM oficios o
    JOIN secretarias s ON s.id = o.secretaria_id
    LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
    WHERE $where_sql
");
$stmt_count->execute($params);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));
$pagina_atual = min($pagina_atual, $total_paginas);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$stmt = $pdo->prepare("
    SELECT
        o.*,
        s.nome AS secretaria,
        u.nome AS usuario,
        f.nome AS fornecedor_nome,
        f.cnpj AS fornecedor_cnpj,
        (SELECT COUNT(*) FROM itens_oficio io WHERE io.oficio_id = o.id) AS total_itens
    FROM oficios o
    JOIN secretarias s ON s.id = o.secretaria_id
    JOIN usuarios u ON u.id = o.usuario_id
    LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
    WHERE $where_sql
    ORDER BY o.criado_em DESC, o.id DESC
    LIMIT $itens_por_pagina OFFSET $offset
");
$stmt->execute($params);
$oficios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$secretarias_list = $pdo->query('SELECT id, nome FROM secretarias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_list = $pdo->query('SELECT id, nome FROM fornecedores ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

function sefaz_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sefaz_query(array $overrides = []): string
{
    $base = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($base[$key]);
        } else {
            $base[$key] = $value;
        }
    }
    return http_build_query($base);
}

include 'views/layout/header.php';
?>

<style>
.sefaz-filter-card{margin-bottom:1.5rem}.sefaz-filter-grid{display:grid;grid-template-columns:1.4fr 1fr 1.2fr 1.2fr 1fr 1fr auto;gap:.85rem;align-items:end}.sefaz-filter-grid .form-group{margin:0;min-width:0}.sefaz-actions{display:flex;gap:.5rem;align-items:end}.sefaz-table-wrap{overflow-x:auto}.sefaz-table{width:100%;min-width:1180px;border-collapse:collapse}.sefaz-table th,.sefaz-table td{padding:.8rem .7rem;vertical-align:middle}.sefaz-table th{white-space:nowrap}.supplier-empty{color:#b45309;font-weight:800}.supplier-ok{color:#0f172a;font-weight:700}.flow-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .6rem;border-radius:999px;font-size:.75rem;font-weight:800;white-space:nowrap}.flow-wait-supplier{background:#fff7ed;color:#9a3412}.flow-wait-items{background:#eff6ff;color:#1d4ed8}.flow-sent{background:#f0fdf4;color:#166534}.flow-approved{background:#dcfce7;color:#166534}.flow-rejected{background:#fef2f2;color:#991b1b}.acoes-nowrap{display:flex;gap:.4rem;justify-content:flex-end;align-items:center;white-space:nowrap}.paginacao-lista{display:flex;justify-content:center;align-items:center;gap:1rem;margin-top:1.25rem;flex-wrap:wrap}@media(max-width:1200px){.sefaz-filter-grid{grid-template-columns:1fr 1fr 1fr}}@media(max-width:768px){.sefaz-filter-grid{grid-template-columns:1fr}.sefaz-actions{display:grid;grid-template-columns:1fr 1fr}.sefaz-actions .btn{justify-content:center}}
</style>

<div class="card sefaz-filter-card no-print">
    <div class="card-body">
        <h4 class="card-title"><i class="fas fa-filter"></i> Filtros de Busca</h4>
        <form method="GET" class="sefaz-filter-grid">
            <div class="form-group">
                <label class="form-label">Busca</label>
                <input type="text" name="busca" class="form-control" placeholder="Número, secretaria, fornecedor ou local" value="<?= sefaz_h($busca) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($status_options as $op): ?><option value="<?= sefaz_h($op) ?>" <?= $status === $op ? 'selected' : '' ?>><?= sefaz_h($op) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Secretaria</label>
                <select name="secretaria" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($secretarias_list as $sl): ?><option value="<?= (int)$sl['id'] ?>" <?= (string)$secretaria === (string)$sl['id'] ? 'selected' : '' ?>><?= sefaz_h($sl['nome']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($fornecedores_list as $fl): ?><option value="<?= (int)$fl['id'] ?>" <?= (string)$fornecedor === (string)$fl['id'] ? 'selected' : '' ?>><?= sefaz_h($fl['nome']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Data inicial</label><input type="date" name="data_inicio" class="form-control" value="<?= sefaz_h($data_inicio) ?>"></div>
            <div class="form-group"><label class="form-label">Data final</label><input type="date" name="data_fim" class="form-control" value="<?= sefaz_h($data_fim) ?>"></div>
            <div class="sefaz-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i> Filtrar</button>
                <a href="oficios_lista_sefaz.php" class="btn btn-outline btn-sm"><i class="fas fa-eraser"></i> Limpar</a>
            </div>
            <div class="form-group">
                <label class="form-label">Linhas</label>
                <select name="por_pagina" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($por_pagina_options as $op): ?><option value="<?= $op ?>" <?= $itens_por_pagina === $op ? 'selected' : '' ?>><?= $op ?> linhas</option><?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-list"></i> Solicitações em Tempo Real</h3>
            <span class="text-muted"><?= $total_registros ?> registro(s)</span>
        </div>

        <?php display_flash(); ?>

        <div class="sefaz-table-wrap">
            <table class="sefaz-table">
                <thead>
                    <tr>
                        <th>Número</th><th>Secretaria</th><th>Fornecedor</th><th>Etapa</th><th>Data</th><th>Orçamento</th><th>Itens</th><th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($oficios)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:1.5rem;">Nenhuma solicitação encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($oficios as $o): ?>
                        <?php
                        $fornecedor_definido = (int)($o['fornecedor_indicado_id'] ?? 0) > 0;
                        $status_o = (string)$o['status'];
                        if ($status_o === 'PENDENTE_ITENS' && !$fornecedor_definido) {
                            $etapa_label = 'Aguardando fornecedor'; $etapa_class = 'flow-wait-supplier';
                        } elseif ($status_o === 'PENDENTE_ITENS') {
                            $etapa_label = 'Aguardando itens'; $etapa_class = 'flow-wait-items';
                        } elseif ($status_o === 'ENVIADO') {
                            $etapa_label = 'Enviado para aprovação'; $etapa_class = 'flow-sent';
                        } elseif ($status_o === 'APROVADO') {
                            $etapa_label = 'Aprovado'; $etapa_class = 'flow-approved';
                        } else {
                            $etapa_label = $status_o; $etapa_class = 'flow-rejected';
                        }
                        ?>
                        <tr>
                            <td style="font-weight:800;color:var(--primary);white-space:nowrap;"><?= sefaz_h($o['numero']) ?></td>
                            <td><?= sefaz_h($o['secretaria']) ?></td>
                            <td>
                                <?php if ($fornecedor_definido): ?>
                                    <span class="supplier-ok"><?= sefaz_h($o['fornecedor_nome']) ?></span>
                                <?php else: ?>
                                    <span class="supplier-empty"><i class="fas fa-exclamation-circle"></i> Não informado</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="flow-badge <?= $etapa_class ?>"><?= sefaz_h($etapa_label) ?></span></td>
                            <td style="white-space:nowrap;"><?= format_date($o['criado_em']) ?></td>
                            <td style="white-space:nowrap;"><?= !empty($o['valor_orcamento']) ? format_money($o['valor_orcamento']) : '---' ?></td>
                            <td style="text-align:center;font-weight:800;"><?= (int)$o['total_itens'] ?></td>
                            <td style="text-align:right;">
                                <div class="acoes-nowrap">
                                    <a href="oficios_anexar.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm" title="Anexar Ofício"><i class="fas fa-paperclip"></i></a>
                                    <a href="oficios_visualizar.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm" title="Ver detalhes"><i class="fas fa-eye"></i></a>
                                    <?php if ($status_o === 'PENDENTE_ITENS' && !$fornecedor_definido): ?>
                                        <a href="atribuir_itens.php?id=<?= (int)$o['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-truck"></i> Informar fornecedor</a>
                                    <?php elseif ($status_o === 'PENDENTE_ITENS'): ?>
                                        <a href="atribuir_itens.php?id=<?= (int)$o['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle"></i> Adicionar itens</a>
                                    <?php elseif ($status_o === 'ENVIADO'): ?>
                                        <a href="atribuir_itens.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm" title="Editar itens"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-lista">
                <a class="btn btn-outline btn-sm <?= $pagina_atual <= 1 ? 'disabled' : '' ?>" href="?<?= sefaz_h(sefaz_query(['page' => max(1, $pagina_atual - 1)])) ?>">Anterior</a>
                <span>Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
                <a class="btn btn-outline btn-sm <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>" href="?<?= sefaz_h(sefaz_query(['page' => min($total_paginas, $pagina_atual + 1)])) ?>">Próxima</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
