<?php
declare(strict_types=1);

$id = max(0, (int) ($_GET['id'] ?? 0));
$supplierId = max(0, (int) ($_GET['fornecedor_so'] ?? 0));
$isNew = ($_GET['nova'] ?? '') === '1';
$company = $id > 0 ? $application->adminCompanies()->find($id) : null;
$supplier = null;
$matchingCompany = null;
$loadError = false;

if ($supplierId > 0) {
    try {
        $supplier = $application->soSuppliers()->findById($supplierId);
        if ($supplier !== null && in_array(strlen(preg_replace('/\D/', '', $supplier->document) ?? ''), [11, 14], true)) {
            $matchingCompany = $application->adminCompanies()->findByDocument($supplier->document);
        }
    } catch (Throwable $exception) {
        error_log('Could not load SO supplier for admin form: ' . get_class($exception));
        $loadError = true;
    }
}

$adminContent = static function () use ($company, $supplier, $supplierId, $matchingCompany, $isNew, $loadError, $csrf): void {
    $statuses = ['pendente' => 'Pendente', 'ativo' => 'Ativa', 'inativo' => 'Inativa', 'bloqueado' => 'Bloqueada'];
    $companyForm = static function (array $values, string $action, string $submit, ?int $companyId = null) use ($csrf, $statuses): void { ?>
        <form method="post" action="<?= admin_url($action) ?>" class="admin-form admin-company-form">
            <?= $csrf->field() ?>
            <?php if ($companyId !== null): ?><input type="hidden" name="id" value="<?= $companyId ?>"><?php endif; ?>
            <label>Razão social<input name="razao_social" maxlength="180" required value="<?= admin_h($values['razao_social'] ?? '') ?>"></label>
            <label>Nome fantasia<input name="nome_fantasia" maxlength="150" value="<?= admin_h($values['nome_fantasia'] ?? '') ?>"></label>
            <label>CPF ou CNPJ<input name="documento" inputmode="numeric" maxlength="18" required value="<?= admin_h($values['documento'] ?? '') ?>"></label>
            <label>Tipo de pessoa<select name="tipo_pessoa" required><option value="juridica" <?= ($values['tipo_pessoa'] ?? 'juridica') === 'juridica' ? 'selected' : '' ?>>Jurídica</option><option value="fisica" <?= ($values['tipo_pessoa'] ?? '') === 'fisica' ? 'selected' : '' ?>>Física</option></select></label>
            <label>Segmento<input name="segmento" maxlength="120" required value="<?= admin_h($values['segmento'] ?? '') ?>"></label>
            <label>Contato responsável<input name="contato_responsavel" maxlength="150" value="<?= admin_h($values['contato_responsavel'] ?? '') ?>"></label>
            <label>Telefone<input name="telefone" maxlength="30" value="<?= admin_h($values['telefone'] ?? '') ?>"></label>
            <label>E-mail<input name="email" type="email" maxlength="150" value="<?= admin_h($values['email'] ?? '') ?>"></label>
            <label>Status<select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= $key ?>" <?= ($values['status'] ?? 'pendente') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <div class="admin-form-actions"><button class="btn btn-primary" type="submit"><?= admin_h($submit) ?></button><a class="btn btn-outline-secondary" href="<?= admin_url('empresas.php') ?>">Cancelar</a></div>
        </form>
    <?php };

    if ($loadError) {
        admin_empty('SO indisponível', 'Não foi possível carregar esse fornecedor. Tente novamente mais tarde.');
        return;
    }
    if ($isNew) { ?>
        <section class="admin-panel"><h2>Cadastrar nova empresa</h2><p>Cadastre manualmente uma empresa na plataforma.</p><?php $companyForm([], 'actions/empresa-criar.php', 'Cadastrar empresa'); ?></section>
        <?php return;
    }
    if ($supplier !== null) {
        $supplierDocument = preg_replace('/\D/', '', $supplier->document) ?? ''; ?>
        <section class="admin-panel">
            <h2>Aprovar empresa do SO</h2>
            <p><strong>Fornecedor:</strong> <?= admin_h($supplier->name) ?> · <strong>Documento:</strong> <?= admin_h($supplier->document ?: 'não informado') ?></p>
            <?php if ($matchingCompany !== null): ?>
                <div class="alert alert-info" role="status">Já existe uma empresa com este documento. Vincule o fornecedor sem duplicar o cadastro.</div>
                <form method="post" action="<?= admin_url('actions/empresa-vincular-so.php') ?>" class="admin-inline-form">
                    <?= $csrf->field() ?><input type="hidden" name="fornecedor_so_id" value="<?= $supplierId ?>"><input type="hidden" name="empresa_id" value="<?= (int) $matchingCompany['id'] ?>">
                    <span><?= admin_h($matchingCompany['nome_fantasia'] ?? $matchingCompany['razao_social'] ?? 'Empresa') ?></span>
                    <button class="btn btn-primary" type="submit">Vincular empresa existente</button><a class="btn btn-outline-secondary" href="<?= admin_url('empresas-so.php') ?>">Cancelar</a>
                </form>
            <?php else: ?>
                <form method="post" action="<?= admin_url('actions/empresa-importar-so.php') ?>" class="admin-form admin-company-form">
                    <?= $csrf->field() ?><input type="hidden" name="fornecedor_so_id" value="<?= $supplierId ?>">
                    <label>Razão social<input name="razao_social" maxlength="180" required value="<?= admin_h($supplier->name) ?>"></label>
                    <label>Nome fantasia<input name="nome_fantasia" maxlength="150"></label>
                    <label>CPF ou CNPJ<input name="documento" inputmode="numeric" maxlength="18" required value="<?= admin_h($supplier->document) ?>"><small>Revise o documento recebido do SO antes de cadastrar.</small></label>
                    <label>Tipo de pessoa<select name="tipo_pessoa" required><option value="juridica" <?= strlen($supplierDocument) !== 11 ? 'selected' : '' ?>>Jurídica</option><option value="fisica" <?= strlen($supplierDocument) === 11 ? 'selected' : '' ?>>Física</option></select></label>
                    <label>Segmento<input name="segmento" maxlength="120" required></label>
                    <label>Contato responsável<input name="contato_responsavel" maxlength="150" value="<?= admin_h($supplier->contact) ?>"></label>
                    <label>Telefone<input name="telefone" maxlength="30" value="<?= admin_h($supplier->phone) ?>"></label>
                    <label>E-mail<input name="email" type="email" maxlength="150"></label>
                    <label>Status inicial<select name="status"><option value="pendente">Pendente</option><option value="ativo">Ativa</option></select></label>
                    <div class="admin-form-actions"><button class="btn btn-primary" type="submit">Aprovar e cadastrar</button><a class="btn btn-outline-secondary" href="<?= admin_url('empresas-so.php') ?>">Cancelar</a></div>
                </form>
            <?php endif; ?>
        </section>
        <?php return;
    }
    if ($company === null) {
        admin_empty('Empresa não encontrada', 'Verifique o identificador informado.');
        return;
    } ?>
    <section class="admin-panel">
        <div class="admin-detail-header"><div><h2><?= admin_h($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa') ?></h2><?= admin_badge((string) ($company['status'] ?? 'pendente')) ?></div><a class="btn btn-outline-secondary" href="<?= admin_url('empresas.php') ?>">Voltar</a></div>
        <h3>Dados cadastrais</h3>
        <?php $companyForm($company, 'actions/empresa-editar.php', 'Salvar alterações', (int) $company['id']); ?>
    </section>
    <section class="admin-panel admin-support-panel">
        <h2>Atendimento administrativo</h2>
        <p>Este modo registra o acompanhamento da empresa na área administrativa. Ele não altera o escopo dos dados do painel operacional.</p>
        <form method="post" action="<?= admin_url('actions/empresa-entrar.php') ?>" class="admin-form">
            <?= $csrf->field() ?><input type="hidden" name="id" value="<?= (int) $company['id'] ?>">
            <label class="admin-form-wide">Motivo ou chamado<textarea name="motivo" minlength="10" maxlength="255" required placeholder="Ex.: Chamado 1234 — conferir integração com o SO"></textarea></label>
            <div class="admin-form-actions"><button class="btn btn-primary" type="submit" <?= in_array((string) ($company['status'] ?? ''), ['ativo', 'pendente'], true) ? '' : 'disabled' ?>>Iniciar atendimento</button></div>
        </form>
    </section>
<?php };
