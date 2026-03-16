<?php include 'conexao.php'; ?>
<?php
$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT * FROM membros";
$params = [];

if ($busca !== '') {
    $sql .= " WHERE nome_completo LIKE :busca OR congregacao LIKE :busca OR telefone LIKE :busca";
    $params[':busca'] = '%' . $busca . '%';
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membros = $stmt->fetchAll();

$totalMembros = count($membros);
$totalBatismo = 0;
$totalMasc = 0;
$totalFem = 0;

foreach ($membros as $m) {
    if (($m['tipo_ingresso'] ?? '') === 'BATISMO') $totalBatismo++;
    if (($m['sexo'] ?? '') === 'M') $totalMasc++;
    if (($m['sexo'] ?? '') === 'F') $totalFem++;
}
?>
<?php include 'includes/header.php'; ?>

<style>
    .lista-page {
        margin-top: 8px;
    }

    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .page-head h2 {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
        margin: 0 0 6px;
    }

    .page-head p {
        margin: 0;
        color: #64748b;
        font-size: .95rem;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }

    .summary-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .summary-card span {
        display: block;
        color: #64748b;
        font-size: .85rem;
        margin-bottom: 6px;
    }

    .summary-card strong {
        display: block;
        font-size: 1.7rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }

    .summary-card small {
        display: block;
        margin-top: 6px;
        color: #94a3b8;
        font-size: .8rem;
    }

    .list-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .list-panel-head {
        padding: 20px 22px 14px;
        border-bottom: 1px solid #eef2f7;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .list-panel-body {
        padding: 20px 22px 22px;
    }

    .search-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 14px;
        margin-bottom: 18px;
    }

    .search-input {
        min-height: 48px;
        border-radius: 14px;
        border: 1px solid #dbe2ea;
        padding: 12px 14px;
        box-shadow: none !important;
    }

    .search-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10) !important;
    }

    .btn-search,
    .btn-create {
        min-height: 48px;
        border-radius: 14px;
        font-weight: 700;
    }

    .members-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .members-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: .82rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: 14px 12px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .members-table tbody td {
        padding: 14px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #eef2f7;
    }

    .members-table tbody tr:hover {
        background: #fafcff;
    }

    .member-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 220px;
    }

    .thumb-foto,
    .thumb-vazio {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        object-fit: cover;
        border: 1px solid #dbe2ea;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #64748b;
        flex-shrink: 0;
    }

    .member-name {
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }

    .member-meta {
        color: #64748b;
        font-size: .82rem;
        margin-top: 2px;
    }

    .ingresso-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 108px;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: .76rem;
        font-weight: 800;
        border: 1px solid transparent;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .badge-batismo {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .badge-aclamacao {
        background: #fff7ed;
        color: #c2410c;
        border-color: #fdba74;
    }

    .badge-mudanca {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .badge-default {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
        min-width: 250px;
    }

    .acoes-wrap .btn {
        border-radius: 12px;
        font-weight: 700;
        padding: 7px 12px;
    }

    .empty-state {
        text-align: center;
        padding: 38px 20px;
    }

    .empty-state-icon {
        width: 74px;
        height: 74px;
        border-radius: 22px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 14px;
    }

    .empty-state h5 {
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .empty-state p {
        color: #64748b;
        margin-bottom: 16px;
    }

    @media (max-width: 1199.98px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 991.98px) {
        .members-table {
            min-width: 980px;
        }
    }

    @media (max-width: 767.98px) {
        .page-head h2 {
            font-size: 1.55rem;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .list-panel,
        .summary-card {
            border-radius: 18px;
        }

        .list-panel-head,
        .list-panel-body {
            padding-left: 16px;
            padding-right: 16px;
        }
    }
</style>

<div class="lista-page">
    <div class="page-head">
        <div>
            <h2>Lista de membros</h2>
            <p>Busque por nome, congregação ou telefone e gerencie os cadastros com mais facilidade.</p>
        </div>

        <div>
            <a href="cadastrar.php" class="btn btn-primary btn-create">
                <i class="fas fa-user-plus me-2"></i>Cadastrar membro
            </a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span>Total listado</span>
            <strong><?= $totalMembros ?></strong>
            <small>registros encontrados</small>
        </div>

        <div class="summary-card">
            <span>Batismos</span>
            <strong><?= $totalBatismo ?></strong>
            <small>entrada por batismo</small>
        </div>

        <div class="summary-card">
            <span>Masculino</span>
            <strong><?= $totalMasc ?></strong>
            <small>membros listados</small>
        </div>

        <div class="summary-card">
            <span>Feminino</span>
            <strong><?= $totalFem ?></strong>
            <small>membros listados</small>
        </div>
    </div>

    <div class="list-panel">
        <div class="list-panel-head">
            <h5 class="mb-1 fw-bold">Cadastros</h5>
            <p class="mb-0 text-muted">Visualize, edite, gere ficha e exclua registros.</p>
        </div>

        <div class="list-panel-body">
            <div class="search-box">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col-md-10">
                        <input
                            type="text"
                            name="busca"
                            class="form-control search-input"
                            placeholder="Digite nome, congregação ou telefone..."
                            value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-dark btn-search">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!$membros): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Nenhum registro encontrado</h5>
                    <p>Não encontramos membros com os filtros informados.</p>
                    <a href="cadastrar.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar membro
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th width="80">Foto</th>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Congregação</th>
                                <th>Área</th>
                                <th>Ingresso</th>
                                <th class="text-end" width="260">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membros as $m): ?>
                                <?php
                                $tipo = strtoupper((string)($m['tipo_ingresso'] ?? ''));

                                $badgeClass = 'badge-default';
                                if ($tipo === 'BATISMO') $badgeClass = 'badge-batismo';
                                if ($tipo === 'ACLAMACAO') $badgeClass = 'badge-aclamacao';
                                if ($tipo === 'MUDANCA') $badgeClass = 'badge-mudanca';
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($m['foto'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($m['foto']) ?>" class="thumb-foto" alt="foto">
                                        <?php else: ?>
                                            <div class="thumb-vazio">
                                                <?= strtoupper(mb_substr($m['nome_completo'] ?? '-', 0, 1, 'UTF-8')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="member-cell">
                                            <div>
                                                <div class="member-name"><?= htmlspecialchars($m['nome_completo']) ?></div>
                                                <div class="member-meta">#<?= (int)$m['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td><?= htmlspecialchars($m['telefone'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($m['congregacao'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($m['area'] ?: '-') ?></td>

                                    <td>
                                        <span class="ingresso-badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($tipo ?: 'NÃO INFORMADO') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="acoes-wrap">
                                            <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Ver
                                            </a>
                                            <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-pen me-1"></i>Editar
                                            </a>
                                            <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-file-alt me-1"></i>Ficha
                                            </a>
                                            <a href="excluir.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger btn-excluir">
                                                <i class="fas fa-trash me-1"></i>Excluir
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>