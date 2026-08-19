<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Visita social e parecer técnico',
    'description' => 'Ficha utilizada pela Assistência Social na visita, vinculada ao cadastro inicial do candidato.',
    'actions' => [['label' => 'Ver candidatos', 'icon' => 'people', 'href' => 'primeiro-emprego/candidatos.php']],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Visita social'],
];

$dbReady = pe_db_ready() && pe_schema_ready();
$message = null;
$candidates = [];
$selected = null;
$visits = [];
$programReady = false;
$followups = [];
if ($dbReady) {
    $pdo = pe_db();
    $programReady = pe_program_schema_ready();
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'save_followup' && $programReady) {
        try {
            pe_verify_csrf();
            pe_save_followup($pdo, $_POST, pe_current_user_label());
            $message = ['type' => 'success', 'text' => 'Acompanhamento operacional registrado com sucesso.'];
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
    if ($programReady) {
        $followups = pe_followup_rows($pdo);
        if ($selected) {
            $followups = array_values(array_filter($followups, static fn(array $row): bool => (int)$row['candidato_id'] === (int)$selected['id']));
        }
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

    <?php if ($selected && $programReady): ?>
        <hr class="my-4 pe-no-print">
        <div class="pe-form-header pe-no-print"><div><div class="card-kicker">Pós-encaminhamento</div><h2>Acompanhamento do participante</h2><p>Registre orientação, avaliação, contato mensal ou próxima ação.</p></div></div>
        <form method="post" class="row g-3 pe-no-print">
            <?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="save_followup"><input type="hidden" name="candidato_id" value="<?= (int)$selected['id'] ?>">
            <div class="col-md-3"><label class="form-label">Data</label><input class="form-control" type="date" name="data_acompanhamento" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option>Contato mensal</option><option>Orientação</option><option>Avaliação</option><option>Visita ao local</option><option>Outro</option></select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>Regular</option><option>Atenção</option><option>Pendente</option><option>Concluído</option></select></div>
            <div class="col-md-3"><label class="form-label">Próxima ação em</label><input class="form-control" type="date" name="data_proxima_acao"></div>
            <div class="col-12"><label class="form-label required">Resumo</label><textarea class="form-control" name="resumo" rows="3" required></textarea></div>
            <div class="col-lg-10"><label class="form-label">Próxima ação</label><input class="form-control" name="proxima_acao"></div><div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Salvar</button></div>
        </form>
        <?php if ($followups): ?><div class="table-responsive mt-4 pe-no-print"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Tipo</th><th>Resumo</th><th>Próxima ação</th><th>Status</th><th>Responsável</th></tr></thead><tbody><?php foreach($followups as $f):?><tr><td><?=pe_h(date('d/m/Y',strtotime((string)$f['data_acompanhamento'])))?></td><td><?=pe_h($f['tipo'])?></td><td><?=pe_h($f['resumo'])?></td><td><?=pe_h($f['proxima_acao']?:'—')?></td><td><span class="badge text-bg-light border"><?=pe_h($f['status'])?></span></td><td><?=pe_h($f['responsavel']?:'—')?></td></tr><?php endforeach;?></tbody></table></div><?php endif; ?>
    <?php endif; ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
