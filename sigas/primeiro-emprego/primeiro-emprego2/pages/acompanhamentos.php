<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Visita social e parecer técnico',
    'description' => 'Ficha utilizada pela Assistência Social na visita, vinculada ao cadastro inicial do candidato.',
    'actions' => [],
    'modal' => ['title' => 'Visita social'],
];

$dbReady = pe_db_ready();
$message = null;
$candidates = [];
$selected = null;
$visits = [];
if ($dbReady) {
    $pdo = pe_db();
    $candidates = pe_recent_candidates($pdo, 1000);
    $candidateId = (int) ($_GET['candidato_id'] ?? $_POST['candidato_id'] ?? 0);
    if ($candidateId > 0) {
        $selected = pe_candidate_by_id($pdo, $candidateId);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'save_visit') {
        try {
            pe_verify_csrf();
            pe_save_visit($pdo, $_POST);
            $message = ['type' => 'success', 'text' => 'Visita social e parecer técnico registrados com sucesso.'];
            $selected = pe_candidate_by_id($pdo, (int) $_POST['candidato_id']);
        } catch (Throwable $e) {
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }
    if ($selected) {
        $stmt = $pdo->prepare('SELECT * FROM pe_visitas_sociais WHERE candidato_id=:id ORDER BY data_visita DESC, id DESC LIMIT 10');
        $stmt->execute(['id' => $selected['id']]);
        $visits = $stmt->fetchAll();
    }
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>
    <div class="pe-form-header">
        <div><div class="card-kicker">Assistência Social</div><h2>Ficha de visita social</h2><p>Selecione o candidato; os dados de identificação são carregados automaticamente da triagem.</p></div>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>

    <?php if ($dbReady): ?>
    <form method="get" class="row g-2 align-items-end pe-no-print mb-4">
        <div class="col-md-9"><label class="form-label">Candidato</label><select class="form-select" name="candidato_id" required><option value="">Selecione...</option><?php foreach ($candidates as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"<?= $selected && (int)$selected['id'] === (int)$candidate['id'] ? ' selected' : '' ?>><?= pe_h($candidate['nome']) ?><?= $candidate['cpf'] ? ' — ' . pe_h($candidate['cpf']) : '' ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Carregar cadastro</button></div>
    </form>
    <?php endif; ?>

    <?php if ($selected): ?>
        <div class="pe-prefill-grid">
            <div><span>Adolescente/Jovem</span><strong><?= pe_h($selected['nome']) ?></strong></div>
            <div><span>Endereço</span><strong><?= pe_h($selected['endereco'] ?: $selected['rua']) ?></strong></div>
            <div><span>Bairro</span><strong><?= pe_h($selected['bairro']) ?></strong></div>
            <div><span>Contato</span><strong><?= pe_h($selected['telefone']) ?></strong></div>
        </div>

        <form method="post" class="pe-real-form mt-4">
            <?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="save_visit"><input type="hidden" name="candidato_id" value="<?= (int) $selected['id'] ?>">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Nome do entrevistado(a)</label><input class="form-control" name="entrevistador" maxlength="160" value="<?= pe_h($_POST['entrevistador'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Data da visita</label><input class="form-control" type="date" name="data_visita" value="<?= pe_h($_POST['data_visita'] ?? date('Y-m-d')) ?>"></div>
                <div class="col-12"><label class="form-label">1.0 Informações complementares</label><textarea class="form-control pe-textarea-large" name="informacoes_complementares" rows="6" maxlength="5000"><?= pe_h($_POST['informacoes_complementares'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label required">1.1 Parecer técnico</label><textarea class="form-control pe-textarea-large" name="parecer_tecnico" rows="9" maxlength="8000" required><?= pe_h($_POST['parecer_tecnico'] ?? '') ?></textarea></div>
                <div class="col-md-4"><label class="form-label">Resultado</label><select class="form-select" name="decisao"><option>Pendente</option><option>Deferido</option><option>Indeferido</option></select></div>
                <div class="col-md-8"><label class="form-label">Técnico responsável</label><input class="form-control" name="tecnico_responsavel" maxlength="160" value="<?= pe_h($_POST['tecnico_responsavel'] ?? '') ?>"></div>
            </div>
            <div class="d-flex justify-content-end mt-4 pe-no-print"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Salvar parecer</button></div>
        </form>

        <?php if ($visits): ?>
            <hr class="my-4 pe-no-print"><div class="pe-no-print"><h3 class="h6">Histórico de visitas</h3><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Entrevistador</th><th>Decisão</th><th>Técnico</th></tr></thead><tbody><?php foreach ($visits as $visit): ?><tr><td><?= pe_h(date('d/m/Y', strtotime($visit['data_visita']))) ?></td><td><?= pe_h($visit['entrevistador']) ?></td><td><span class="badge text-bg-light"><?= pe_h($visit['decisao']) ?></span></td><td><?= pe_h($visit['tecnico_responsavel']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        <?php endif; ?>
    <?php elseif ($dbReady): ?><div class="alert alert-info">Selecione um candidato para abrir a ficha da visita.</div><?php endif; ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
