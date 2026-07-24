<?php

declare(strict_types=1);

use App\Workforce\Entity\Employee;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/funcionario-action-common.php';

$employeeService = $application->employeeManagement();
$search = trim((string) ($_GET['search'] ?? ''));
if (strlen($search) > 150 || str_contains($search, "\0")) $search = '';
$employees = $employeeService->listEmployees($search);
$totalEmployees = $search === '' ? count($employees) : count($employeeService->listEmployees());

$canCreate = $authorization->can('funcionario.criar');
$canEdit = $authorization->can('funcionario.editar');
$canViewSalary = $authorization->can('funcionario.visualizar_salario');
$canEditSalary = $authorization->can('funcionario.editar_salario');
$canViewDocuments = $authorization->can('funcionario.visualizar_documentos');
$canEditDocuments = $authorization->can('funcionario.editar_documentos');
$canViewBank = $authorization->can('funcionario.visualizar_dados_bancarios');
$canEditBank = $authorization->can('funcionario.editar_dados_bancarios');
$employeeFormRecovery = employee_consume_form_recovery();

function employee_day(?string $value): string { try { return $value === null || trim($value) === '' ? '-' : (new DateTimeImmutable($value))->format('d/m/Y'); } catch (Throwable) { return '-'; } }
function employee_initials(string $name): string { $parts = preg_split('/\s+/u', trim($name)) ?: []; $initials = ''; foreach (array_slice($parts, 0, 2) as $part) $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1); return function_exists('mb_strtoupper') ? mb_strtoupper($initials ?: 'F') : strtoupper($initials ?: 'F'); }
function employee_recovery(?array $recovery, string $modal): array { return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_array($recovery['data'] ?? null) ? $recovery['data'] : []; }
function employee_recovery_error(?array $recovery, string $modal): ?string { return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_string($recovery['error'] ?? null) ? $recovery['error'] : null; }
function employee_value(array $data, string $key, string $default = ''): string { $value = $data[$key] ?? $default; return is_scalar($value) ? (string) $value : $default; }

/** @param array<string,string> $options */
function employee_field(string $prefix, array $data, string $name, string $label, string $type = 'text', array $options = [], string $attributes = ''): void {
    $id = $prefix . '-' . str_replace('_', '-', $name); $value = employee_value($data, $name); ?>
    <div class="form-group"><label class="form-label" for="<?= h($id) ?>"><?= h($label) ?></label>
    <?php if ($type === 'select'): ?><select class="form-control-os" id="<?= h($id) ?>" name="<?= h($name) ?>" <?= $attributes ?>>
        <option value="">Selecione</option><?php foreach ($options as $optionValue => $optionLabel): ?><option value="<?= h($optionValue) ?>" <?= $value === $optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option><?php endforeach; ?>
    </select><?php else: ?><input class="form-control-os" id="<?= h($id) ?>" type="<?= h($type) ?>" name="<?= h($name) ?>" value="<?= h($value) ?>" <?= $attributes ?>><?php endif; ?></div>
<?php }

function employee_form_fields(string $prefix, array $data, bool $canSalary, bool $canDocuments, bool $canBank, bool $editing): void {
    $civil = ['Solteiro'=>'Solteiro','Casado'=>'Casado','Divorciado'=>'Divorciado','Viuvo'=>'Viúvo','Uniao estavel'=>'União estável','Outro'=>'Outro'];
    $sex = ['Masculino'=>'Masculino','Feminino'=>'Feminino']; ?>
    <section class="form-section"><h3 class="form-section-title">Foto e dados profissionais</h3>
        <div class="employee-photo-editor"><div class="employee-photo-preview" data-employee-photo-preview><img alt="Prévia da foto"><span><i class="bi bi-person"></i></span></div><div class="form-group mb-0"><label class="form-label" for="<?= h($prefix) ?>-photo">Foto</label><input class="form-control-os js-employee-photo-input" id="<?= h($prefix) ?>-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPEG, PNG ou WebP, até 5 MB.</small><?php if ($editing): ?><label class="form-check mt-2"><input class="form-check-input js-remove-employee-photo" type="checkbox" name="remove_photo" value="1"> <span class="form-check-label">Remover foto atual</span></label><?php endif; ?></div></div>
        <div class="form-row mt-3"><?php employee_field($prefix,$data,'name','Nome completo','text',[],'maxlength="150" required'); employee_field($prefix,$data,'funcao','Função','text',[],'maxlength="100"'); if ($canSalary) employee_field($prefix,$data,'salario','Salário','text',[],'inputmode="decimal" placeholder="0,00"'); ?></div>
        <div class="form-row"><?php employee_field($prefix,$data,'data_cadastro','Data de cadastro','date'); employee_field($prefix,$data,'data_admissao','Data de admissão','date'); ?></div>
    </section>
    <section class="form-section"><h3 class="form-section-title">Dados pessoais e contato</h3>
        <div class="form-row"><?php employee_field($prefix,$data,'endereco','Endereço','text',[],'maxlength="255"'); employee_field($prefix,$data,'telefone_celular','Telefone celular','tel',[],'maxlength="30"'); ?></div>
        <div class="form-row"><?php employee_field($prefix,$data,'data_nascimento','Data de nascimento','date'); employee_field($prefix,$data,'estado_civil','Estado civil','select',$civil); employee_field($prefix,$data,'sexo','Sexo','select',$sex); ?></div>
    </section>
    <?php if ($canBank): ?><section class="form-section"><h3 class="form-section-title">Dados bancários</h3>
        <div class="form-row"><?php employee_field($prefix,$data,'banco','Banco','text',[],'maxlength="100"'); employee_field($prefix,$data,'agencia','Agência','text',[],'maxlength="30"'); employee_field($prefix,$data,'conta','Conta','text',[],'maxlength="40"'); ?></div>
        <div class="form-row"><?php employee_field($prefix,$data,'tipo_conta','Tipo de conta','text',[],'maxlength="30"'); employee_field($prefix,$data,'pix','Chave Pix','text',[],'maxlength="150"'); ?></div>
    </section><?php endif; ?>
    <?php if ($canDocuments): ?><section class="form-section"><h3 class="form-section-title">Documentação</h3>
        <h4 class="employee-form-subtitle">RG e CPF</h4><div class="form-row"><?php employee_field($prefix,$data,'rg_numero','RG','text',[],'maxlength="40"'); employee_field($prefix,$data,'rg_uf','UF','text',[],'maxlength="2"'); employee_field($prefix,$data,'rg_orgao_emissor','Órgão emissor','text',[],'maxlength="30"'); employee_field($prefix,$data,'rg_data_emissao','Emissão','date'); employee_field($prefix,$data,'cpf_numero','CPF','text',[],'maxlength="20" inputmode="numeric"'); ?></div>
        <h4 class="employee-form-subtitle">Título de eleitor</h4><div class="form-row"><?php employee_field($prefix,$data,'titulo_eleitor_numero','Número','text',[],'maxlength="40"'); employee_field($prefix,$data,'titulo_eleitor_uf','UF','text',[],'maxlength="2"'); employee_field($prefix,$data,'titulo_eleitor_secao','Seção','text',[],'maxlength="20"'); employee_field($prefix,$data,'titulo_eleitor_zona','Zona','text',[],'maxlength="20"'); ?></div>
        <h4 class="employee-form-subtitle">Certificado de reservista</h4><div class="form-row"><?php employee_field($prefix,$data,'reservista_numero','Série','text',[],'maxlength="60"'); employee_field($prefix,$data,'reservista_data_emissao','Emissão','date'); ?></div>
        <h4 class="employee-form-subtitle">Certidão de nascimento</h4><div class="form-row"><?php employee_field($prefix,$data,'certidao_nascimento_numero','Número','text',[],'maxlength="80"'); employee_field($prefix,$data,'certidao_nascimento_cidade','Cidade','text',[],'maxlength="100"'); employee_field($prefix,$data,'certidao_nascimento_livro','Livro','text',[],'maxlength="30"'); employee_field($prefix,$data,'certidao_nascimento_folha','Folha','text',[],'maxlength="30"'); employee_field($prefix,$data,'certidao_nascimento_data_emissao','Emissão','date'); ?></div>
        <h4 class="employee-form-subtitle">Carteira de trabalho</h4><div class="form-row"><?php employee_field($prefix,$data,'carteira_trabalho_numero','Número','text',[],'maxlength="40"'); employee_field($prefix,$data,'carteira_trabalho_serie','Série','text',[],'maxlength="30"'); employee_field($prefix,$data,'carteira_trabalho_uf','UF','text',[],'maxlength="2"'); employee_field($prefix,$data,'pis_pasep_numero','PIS/PASEP','text',[],'maxlength="40"'); ?></div>
        <h4 class="employee-form-subtitle">Habilitação</h4><div class="form-row"><?php employee_field($prefix,$data,'cnh_numero_registro','Nº do registro','text',[],'maxlength="40"'); employee_field($prefix,$data,'cnh_categoria','Categoria','text',[],'maxlength="20"'); employee_field($prefix,$data,'cnh_data_vencimento','Vencimento','date'); ?></div>
    </section><?php endif; ?>
    <section class="form-section"><h3 class="form-section-title">Manequim</h3><div class="form-row"><?php employee_field($prefix,$data,'manequim_camisa','Camisa','text',[],'maxlength="30" placeholder="Ex.: M ou 42"'); employee_field($prefix,$data,'manequim_calca','Calça','text',[],'maxlength="30" placeholder="Ex.: 40"'); employee_field($prefix,$data,'manequim_calcado','Calçado','text',[],'maxlength="30" placeholder="Ex.: 39"'); ?></div></section>
<?php }

$createData = employee_recovery($employeeFormRecovery, 'create'); $editData = employee_recovery($employeeFormRecovery, 'edit');
if (!isset($createData['data_cadastro'])) $createData['data_cadastro'] = date('Y-m-d');
$createError = employee_recovery_error($employeeFormRecovery, 'create'); $editError = employee_recovery_error($employeeFormRecovery, 'edit');
?>

<div class="page-body employees-page">
<?php metric_grid([['Total de funcionários',(string)$totalEmployees,'bi-person-badge','#2563EB','cadastrados']]); ?>
<form class="filter-bar" method="get" action="funcionarios.php" data-live-filter="employees" data-live-regions="metrics results"><div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($search) ?>" placeholder="Buscar por código, nome ou função" maxlength="150"></div><button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn-filter btn-filter-ghost" href="funcionarios.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar filtros</a></form>
<section class="panel" data-live-region="results"><div class="panel-header"><div class="panel-title"><i class="bi bi-person-badge"></i>Funcionários cadastrados</div><?php if($canCreate):?><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-funcionario"><i class="bi bi-person-plus"></i><span>Novo funcionário</span></button><?php endif;?></div>
<?php if($employees===[]):?><?php empty_state('Nenhum funcionário encontrado','Cadastre o primeiro funcionário ou ajuste a pesquisa.');?><?php else:?><div class="table-panel-wrap"><table class="os-table employees-table"><thead><tr><th>Código</th><th>Funcionário</th><th>Função</th><th>Telefone</th><th>Admissão</th><th>Ações</th></tr></thead><tbody>
<?php foreach($employees as $employee): /** @var Employee $employee */ ?><tr><td><strong><?=h($employee->displayCode())?></strong></td><td><div class="d-flex align-items-center gap-2"><?php if($employee->photo()):?><img class="user-avatar employee-table-avatar" src="funcionario-foto.php?id=<?=h((string)$employee->id())?>&amp;v=<?=h(rawurlencode(basename($employee->photo())))?>" alt="Foto de <?=h($employee->name())?>"><?php else:?><span class="user-avatar small"><?=h(employee_initials($employee->name()))?></span><?php endif;?><strong><?=h($employee->name())?></strong></div></td><td><?=h($employee->functionName() ?: '-')?></td><td><?=h($employee->mobilePhone() ?: '-')?></td><td><?=h(employee_day($employee->value('data_admissao')))?></td><td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do funcionário <?=h($employee->name())?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item js-employee-view" type="button" data-bs-toggle="modal" data-bs-target="#modal-funcionario-view" data-employee-id="<?=h((string)$employee->id())?>"><i class="bi bi-eye"></i> Visualizar</button></li><?php if($canEdit):?><li><button class="dropdown-item js-employee-edit" type="button" data-bs-toggle="modal" data-bs-target="#modal-funcionario-edit" data-employee-id="<?=h((string)$employee->id())?>"><i class="bi bi-pencil"></i> Editar</button></li><?php endif;?></ul></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></div>

<?php if($canCreate):?><div class="modal fade" id="modal-funcionario" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal js-employee-form" method="post" action="actions/funcionario-salvar.php" enctype="multipart/form-data" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo funcionário</h2><p class="text-muted small mb-0">O código será gerado automaticamente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?=$csrf->field()?><?php return_to_field();?><input type="hidden" name="operation" value="create"><input type="hidden" name="MAX_FILE_SIZE" value="5242880"><div class="alert alert-danger <?=$createError===null?'d-none':''?>" role="alert"><?=h($createError??'')?></div><?php employee_form_fields('create-employee',$createData,$canEditSalary,$canEditDocuments,$canEditBank,false);?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Cadastrar funcionário</button></div></form></div></div><?php endif;?>

<div class="modal fade" id="modal-funcionario-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do funcionário</h2><p class="text-muted small mb-0" id="view-employee-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><div class="employee-detail-loading" id="employee-view-loading">Carregando dados…</div><div id="employee-view-content"></div><div class="alert alert-danger d-none" id="employee-view-error" role="alert"></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if($canEdit):?><div class="modal fade" id="modal-funcionario-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal js-employee-form" method="post" action="actions/funcionario-salvar.php" enctype="multipart/form-data" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar funcionário</h2><p class="text-muted small mb-0" id="edit-employee-subtitle"><?=h(employee_value($editData,'code'))?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?=$csrf->field()?><?php return_to_field();?><input type="hidden" name="operation" value="update"><input type="hidden" name="MAX_FILE_SIZE" value="5242880"><input type="hidden" name="id" id="edit-employee-id" value="<?=h(employee_value($editData,'id'))?>"><input type="hidden" name="code" id="edit-employee-code-hidden" value="<?=h(employee_value($editData,'code'))?>"><div class="alert alert-danger <?=$editError===null?'d-none':''?>" id="edit-employee-form-error" role="alert"><?=h($editError??'')?></div><?php employee_form_fields('edit-employee',$editData,$canEditSalary,$canEditDocuments,$canEditBank,true);?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar alterações</button></div></form></div></div><?php endif;?>

<script type="application/json" id="employee-page-data"><?=json_encode(['recoveryModal'=>$employeeFormRecovery['modal']??null,'permissions'=>['viewSalary'=>$canViewSalary,'viewDocuments'=>$canViewDocuments,'viewBank'=>$canViewBank]],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?></script>
