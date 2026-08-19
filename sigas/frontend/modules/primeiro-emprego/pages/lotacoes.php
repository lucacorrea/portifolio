<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Ficha cadastral e local de atuação',
    'description' => 'Ficha do contemplado com dados trazidos da triagem, dados escolares e local de atuação.',
    'actions' => [['label' => 'Ver candidatos', 'icon' => 'people', 'href' => 'primeiro-emprego/candidatos.php']],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Ficha cadastral'],
];

$dbReady = pe_db_ready() && pe_schema_ready();
$message = null;
$candidates = [];
$selected = null;
$profile = null;
if ($dbReady) {
    $pdo = pe_db();
    $candidates = pe_recent_candidates($pdo, 1000);
    $candidateId = (int) ($_GET['candidato_id'] ?? $_POST['candidato_id'] ?? 0);
    if ($candidateId > 0) {
        $selected = pe_candidate_by_id($pdo, $candidateId);
        $stmt = $pdo->prepare('SELECT * FROM pe_fichas_cadastrais WHERE candidato_id=:id LIMIT 1');
        $stmt->execute(['id' => $candidateId]);
        $profile = $stmt->fetch() ?: null;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'save_profile') {
        try {
            pe_verify_csrf();
            pe_save_profile($pdo, $_POST, $_FILES);
            $message = ['type' => 'success', 'text' => 'Ficha cadastral atualizada com sucesso.'];
            $selected = pe_candidate_by_id($pdo, (int) $_POST['candidato_id']);
            $stmt = $pdo->prepare('SELECT * FROM pe_fichas_cadastrais WHERE candidato_id=:id LIMIT 1');
            $stmt->execute(['id' => $selected['id']]);
            $profile = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }
}

function pe_profile_value(?array $profile, array $candidate, string $profileKey, string $candidateKey = ''): string
{
    if ($profile && !empty($profile[$profileKey])) return (string) $profile[$profileKey];
    if ($candidateKey !== '' && !empty($candidate[$candidateKey])) return (string) $candidate[$candidateKey];
    return '';
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>
    <div class="pe-form-header"><div><div class="card-kicker">Meu Primeiro Emprego</div><h2>Ficha cadastral</h2><p>Dados pessoais são reaproveitados automaticamente da primeira ficha.</p></div><button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div>
    <?php if ($dbReady): ?><form method="get" class="row g-2 align-items-end pe-no-print mb-4"><div class="col-md-9"><label class="form-label">Candidato</label><select class="form-select" name="candidato_id"><option value="">Selecione...</option><?php foreach ($candidates as $candidate): ?><option value="<?= (int)$candidate['id'] ?>"<?= $selected && (int)$selected['id']===(int)$candidate['id']?' selected':'' ?>><?= pe_h($candidate['nome']) ?><?= $candidate['cpf'] ? ' — '.pe_h($candidate['cpf']) : '' ?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Carregar ficha</button></div></form><?php endif; ?>

    <?php if ($selected): ?>
    <form method="post" enctype="multipart/form-data" class="pe-real-form">
        <?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="save_profile"><input type="hidden" name="candidato_id" value="<?= (int)$selected['id'] ?>">
        <div class="pe-profile-top">
            <div class="pe-photo-box"><?php if ($profile && $profile['foto_path']): ?><span><i class="bi bi-person-bounding-box"></i> Foto cadastrada</span><?php else: ?><span><i class="bi bi-person"></i> FOTO</span><?php endif; ?></div>
            <div class="pe-profile-identification">
                <div><span>Nome</span><strong><?= pe_h($selected['nome']) ?></strong></div>
                <div class="pe-profile-two"><div><span>CPF</span><strong><?= pe_h($selected['cpf'] ?: $selected['cpf_informado']) ?></strong></div><div><span>Telefone</span><strong><?= pe_h($selected['telefone']) ?></strong></div></div>
                <div><span>Endereço</span><strong><?= pe_h($selected['endereco'] ?: $selected['rua']) ?><?= $selected['bairro'] ? ' — '.pe_h($selected['bairro']) : '' ?></strong></div>
                <div><span>NIS</span><strong><?= pe_h($selected['nis']) ?></strong></div>
            </div>
        </div>
        <div class="row g-3 pe-no-print mb-4"><div class="col-md-6"><label class="form-label">Foto do candidato (opcional)</label><input class="form-control" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG ou WEBP, até 3 MB.</small></div></div>

        <div class="pe-section-title"><span><i class="bi bi-mortarboard"></i></span><div><strong>Dados escolares</strong><small>Escolaridade, instituição, série/período e turno.</small></div></div>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Nível de escolaridade</label><select class="form-select" name="nivel_escolaridade"><option value="">Selecione</option><?php $current=pe_profile_value($profile,$selected,'nivel_escolaridade','escolaridade'); foreach(['Ensino Fundamental','Ensino Médio','Ensino Superior (Faculdade)'] as $v): ?><option<?= $current===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Situação escolar</label><select class="form-select" name="situacao_escolar"><option value="">Selecione</option><?php $current=pe_profile_value($profile,$selected,'situacao_escolar','situacao_escolar'); foreach(['Cursando','Concluído'] as $v): ?><option<?= $current===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Turno de estudo</label><select class="form-select" name="turno_estudo"><option value="">Selecione</option><?php $current=pe_profile_value($profile,$selected,'turno_estudo','turno_estudo'); foreach(['Matutino','Vespertino','Noturno','Integral'] as $v): ?><option<?= $current===$v?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-8"><label class="form-label">Nome da instituição de ensino</label><input class="form-control" name="instituicao_ensino" maxlength="180" value="<?= pe_h(pe_profile_value($profile,$selected,'instituicao_ensino','instituicao_ensino')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Série/Ano ou Período</label><input class="form-control" name="serie_periodo" maxlength="80" value="<?= pe_h($profile['serie_periodo'] ?? '') ?>"></div>
        </div>

        <div class="pe-section-title"><span><i class="bi bi-building"></i></span><div><strong>Local de atuação</strong><small>Órgão/setor e turno do participante.</small></div></div>
        <div class="row g-3"><div class="col-md-8"><label class="form-label">Local</label><input class="form-control" name="local_atuacao" maxlength="180" value="<?= pe_h($profile['local_atuacao'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Turno</label><select class="form-select" name="turno_atuacao"><option value="">Selecione</option><?php foreach(['Matutino','Vespertino','Noturno'] as $v): ?><option<?= (($profile['turno_atuacao'] ?? '')===$v)?' selected':'' ?>><?= pe_h($v) ?></option><?php endforeach; ?></select></div></div>
        <div class="d-flex justify-content-end mt-4 pe-no-print"><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Salvar ficha</button></div>
    </form>
    <?php elseif ($dbReady): ?><div class="alert alert-info">Selecione um candidato para preencher a ficha cadastral.</div><?php endif; ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
