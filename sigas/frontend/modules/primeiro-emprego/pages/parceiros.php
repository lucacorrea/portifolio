<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/list-ui.php';

$pageDefinition = [
    'title' => 'Órgãos e instituições parceiras',
    'description' => 'Cadastro e acompanhamento da rede de parceiros do Programa Meu Primeiro Emprego.',
    'demo' => false,
    'show_states' => false,
    'actions' => [],
    'modal' => ['title' => 'Parceiro'],
];

$dbReady = pe_db_ready() && pe_program_schema_ready();
$message = null;
$rows = [];
$siglaReady = false;

if ($dbReady) {
    $pdo = pe_db();
    $siglaReady = pe_partner_has_sigla($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            pe_verify_csrf();
            $action = (string) ($_POST['pe_action'] ?? '');
            $id = (int) ($_POST['id'] ?? 0);

            if ($action === 'save_partner') {
                if ($id > 0) {
                    pe_update_partner($pdo, $id, $_POST);
                    $message = ['type' => 'success', 'text' => 'Instituição atualizada com sucesso.'];
                } else {
                    pe_save_partner($pdo, $_POST);
                    $message = ['type' => 'success', 'text' => 'Instituição parceira cadastrada com sucesso.'];
                }
            } elseif ($action === 'delete_partner') {
                pe_delete_partner($pdo, $id);
                $message = ['type' => 'success', 'text' => 'Instituição excluída com sucesso.'];
            }
        } catch (Throwable $e) {
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }

    $rows = pe_partners($pdo);
}

$metrics = [
    ['label' => 'Instituições', 'value' => count($rows), 'hint' => 'rede cadastrada', 'tone' => 'neutral'],
    ['label' => 'Ativas', 'value' => pe_count_rows($rows, fn($r) => ($r['status'] ?? '') === 'Ativa'), 'hint' => 'disponíveis', 'tone' => 'success', 'filter_key' => 'status', 'filter_value' => 'Ativa'],
    ['label' => 'Pendentes', 'value' => pe_count_rows($rows, fn($r) => ($r['status'] ?? '') === 'Pendente'), 'hint' => 'aguardando regularização', 'tone' => 'warning', 'filter_key' => 'status', 'filter_value' => 'Pendente'],
    ['label' => 'Suspensas', 'value' => pe_count_rows($rows, fn($r) => ($r['status'] ?? '') === 'Suspensa'), 'hint' => 'exigem atenção', 'tone' => 'danger', 'filter_key' => 'status', 'filter_value' => 'Suspensa'],
    ['label' => 'Com lotados', 'value' => pe_count_rows($rows, fn($r) => (int) ($r['lotados'] ?? 0) > 0), 'hint' => 'recebem participantes', 'tone' => 'info', 'filter_key' => 'vinculo', 'filter_value' => 'Com lotados'],
    ['label' => 'Encerradas', 'value' => pe_count_rows($rows, fn($r) => ($r['status'] ?? '') === 'Encerrada'), 'hint' => 'histórico', 'tone' => 'muted', 'filter_key' => 'status', 'filter_value' => 'Encerrada'],
];

ob_start();
?>
<section class="content-card pe-form-card pe-page pe-list-page">
    <?php if (!$dbReady): ?>
        <div class="alert alert-warning">Execute <code>database/primeiroEmprego/0003-primeiroEmprego-programa.sql</code>.</div>
    <?php endif; ?>
    <?php if ($dbReady && !$siglaReady): ?>
        <div class="alert alert-warning">Para usar siglas, execute <code>database/primeiroEmprego/0005-primeiroEmprego-padrao-operacional.sql</code>.</div>
    <?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <?php pe_list_header('Rede parceira', 'Instituições cadastradas', 'Consulte a rede parceira. Cadastro, visualização, edição e exclusão são feitos em modais.', 'Nova instituição', '#pePartnerForm', 'building-add'); ?>
    <?php pe_list_metrics($metrics, '#pePartnerFilters'); ?>
    <?php pe_list_filter_panel(
            'pePartnerTable',
            'Buscar nome, sigla, responsável, CNPJ ou contato...',
            [
                ['key' => 'status', 'label' => 'Status', 'options' => ['' => 'Todos os status', 'Ativa' => 'Ativa', 'Pendente' => 'Pendente', 'Suspensa' => 'Suspensa', 'Encerrada' => 'Encerrada']],
                ['key' => 'tipo', 'label' => 'Tipo', 'options' => pe_filter_options($rows, 'tipo', 'Todos os tipos')],
                ['key' => 'vinculo', 'label' => 'Participantes', 'options' => ['' => 'Todos', 'Com lotados' => 'Com lotados', 'Sem lotados' => 'Sem lotados']],
            ],
            count($rows),
            'instituição(ões)'
        ); ?>

    <div class="pe-table-wrap">
        <div class="table-responsive">
            <table id="pePartnerTable" class="table align-middle pe-data-table pe-list-table" data-pe-list-table>
                <thead><tr><th>Instituição</th><th>Sigla</th><th>Tipo</th><th>Responsável</th><th>Contato</th><th>Vagas</th><th>Lotados</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr class="pe-empty-row"><td colspan="8" class="text-center text-muted py-5">Nenhuma instituição cadastrada.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $record = $r;
                    $record['sigla'] = $r['sigla'] ?? null;
                    $record['__title'] = ($record['sigla'] ? $record['sigla'] . ' — ' : '') . $r['nome'];
                    $record['__subtitle'] = ($r['tipo'] ?: 'Tipo não informado') . ' · ' . ($r['status'] ?: 'Sem status');
                    $record['telefone_formatado'] = pe_format_phone($r['telefone'] ?: '');
                    $vinculo = (int) ($r['lotados'] ?? 0) > 0 ? 'Com lotados' : 'Sem lotados';
                    ?>
                    <tr
                        class="pe-list-row"
                        tabindex="0"
                        role="button"
                        data-pe-list-row
                        data-pe-actions-target="#pePartnerActions"
                        data-pe-record="<?= pe_record_attr($record) ?>"
                        data-pe-filter-status="<?= pe_h((string) $r['status']) ?>"
                        data-pe-filter-tipo="<?= pe_h((string) ($r['tipo'] ?? '')) ?>"
                        data-pe-filter-vinculo="<?= pe_h($vinculo) ?>"
                    >
                        <td data-label="Instituição"><strong><?= pe_h($r['nome']) ?></strong><small class="pe-inline-detail"><?= pe_h($r['termo_parceria'] ?: 'Sem termo informado') ?></small></td>
                        <td data-label="Sigla"><strong><?= pe_h((string) ($r['sigla'] ?? '—')) ?></strong></td>
                        <td data-label="Tipo"><?= pe_h($r['tipo'] ?: '—') ?></td>
                        <td data-label="Responsável"><?= pe_h($r['responsavel'] ?: '—') ?></td>
                        <td data-label="Contato"><?= pe_h(pe_format_phone($r['telefone'] ?: '')) ?><small class="pe-inline-detail"><?= pe_h($r['email'] ?: '') ?></small></td>
                        <td data-label="Vagas"><?= (int) $r['vagas'] ?></td>
                        <td data-label="Lotados"><?= (int) $r['lotados'] ?></td>
                        <td data-label="Status"><?= pe_status_label($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pe-filter-empty" data-pe-filter-empty hidden>Nenhuma instituição corresponde aos filtros selecionados.</div>
    </div>

    <?php pe_crud_actions_dialog('pePartnerActions', 'Instituição parceira', '#pePartnerView', '#pePartnerForm', '#pePartnerDelete'); ?>

    <dialog class="pe-modal pe-modal--form" id="pePartnerForm" data-pe-create-title="Nova instituição parceira" data-pe-edit-title="Editar instituição parceira">
        <div class="pe-modal__shell">
            <header class="pe-modal__header"><div><div class="card-kicker">Rede parceira</div><h2 data-pe-form-title>Nova instituição parceira</h2><p>Preencha os dados da instituição e salve.</p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body">
                <form method="post" class="pe-action-form" data-pe-record-form autocomplete="off">
                    <?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="save_partner"><input type="hidden" name="id" data-pe-field="id">
                    <div class="pe-action-form-grid pe-action-form-grid--3">
                        <div class="pe-field-span-2"><label class="form-label required">Instituição</label><input class="form-control" name="nome" data-pe-field="nome" required maxlength="180"></div>
                        <div><label class="form-label">Sigla</label><input class="form-control" name="sigla" data-pe-field="sigla" maxlength="30" placeholder="Ex.: SEMAS"></div>
                        <div><label class="form-label">Tipo</label><select class="form-select" name="tipo" data-pe-field="tipo"><?php foreach (['Órgão público','Empresa privada','Organização social','Instituição de ensino','Outro'] as $v): ?><option><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">CNPJ</label><input class="form-control" name="cnpj" data-pe-field="cnpj" inputmode="numeric" maxlength="18"></div>
                        <div><label class="form-label">Responsável</label><input class="form-control" name="responsavel" data-pe-field="responsavel" maxlength="160"></div>
                        <div><label class="form-label">Telefone</label><input class="form-control" name="telefone" data-pe-field="telefone" maxlength="20"></div>
                        <div class="pe-field-span-2"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" data-pe-field="email" maxlength="160"></div>
                        <div><label class="form-label">Termo/parceria</label><input class="form-control" name="termo_parceria" data-pe-field="termo_parceria" maxlength="120"></div>
                        <div><label class="form-label">Status</label><select class="form-select" name="status" data-pe-field="status"><?php foreach (['Ativa','Pendente','Suspensa','Encerrada'] as $v): ?><option><?= pe_h($v) ?></option><?php endforeach; ?></select></div>
                        <div class="pe-field-span-3"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" data-pe-field="observacao" rows="3"></textarea></div>
                    </div>
                    <footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Salvar instituição</button></footer>
                </form>
            </div>
        </div>
    </dialog>

    <dialog class="pe-modal pe-modal--view" id="pePartnerView">
        <div class="pe-modal__shell">
            <header class="pe-modal__header"><div><div class="card-kicker">Instituição parceira</div><h2 data-pe-current-title>Detalhes</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body">
                <dl class="pe-modal-details pe-modal-details--2"><div><dt>Sigla</dt><dd data-pe-text="sigla">—</dd></div><div><dt>Tipo</dt><dd data-pe-text="tipo">—</dd></div><div><dt>CNPJ</dt><dd data-pe-text="cnpj">—</dd></div><div><dt>Responsável</dt><dd data-pe-text="responsavel">—</dd></div><div><dt>Telefone</dt><dd data-pe-text="telefone_formatado">—</dd></div><div><dt>E-mail</dt><dd data-pe-text="email">—</dd></div><div><dt>Termo/parceria</dt><dd data-pe-text="termo_parceria">—</dd></div><div><dt>Status</dt><dd data-pe-text="status">—</dd></div><div><dt>Vagas / lotados</dt><dd><span data-pe-text="vagas">0</span> vaga(s) · <span data-pe-text="lotados">0</span> lotado(s)</dd></div></dl>
                <div class="pe-view-note"><strong>Observação</strong><p data-pe-text="observacao">—</p></div>
            </div>
        </div>
    </dialog>
    <?php pe_delete_dialog('pePartnerDelete', 'instituição', 'delete_partner'); ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
