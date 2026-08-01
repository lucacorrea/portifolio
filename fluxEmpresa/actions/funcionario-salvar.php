<?php

declare(strict_types=1);

use App\Workforce\DTO\EmployeeFormData;
use App\Workforce\Service\EmployeePhotoStorage;

require __DIR__ . '/funcionario-action-common.php';

employee_require_post_request();
$operation = trim((string) ($_POST['operation'] ?? ''));
if (!in_array($operation, ['create', 'update'], true)) { http_response_code(400); exit; }
$rawEmployeeId = trim((string) ($_POST['id'] ?? ''));
$isEditing = $operation === 'update';
$requiredPermission = $isEditing ? 'funcionario.editar' : 'funcionario.criar';
[$application, $session] = employee_action_context($requiredPermission);

$generalFields = [
    'name','funcao','endereco','telefone_celular','data_nascimento','estado_civil','sexo',
    'data_cadastro','data_admissao','manequim_camisa','manequim_calca','manequim_calcado',
];
$salaryFields = ['salario'];
$bankFields = ['banco','agencia','conta','tipo_conta','pix'];
$documentFields = [
    'rg_numero','rg_uf','rg_orgao_emissor','rg_data_emissao','cpf_numero',
    'titulo_eleitor_numero','titulo_eleitor_uf','titulo_eleitor_secao','titulo_eleitor_zona',
    'reservista_numero','reservista_data_emissao','certidao_nascimento_numero',
    'certidao_nascimento_cidade','certidao_nascimento_livro','certidao_nascimento_folha',
    'certidao_nascimento_data_emissao','carteira_trabalho_numero','carteira_trabalho_serie',
    'carteira_trabalho_uf','pis_pasep_numero','cnh_numero_registro','cnh_categoria','cnh_data_vencimento',
];

try {
    $authorization = $application->authorization();
    $canEditSalary = $authorization->can('funcionario.editar_salario');
    $canEditDocuments = $authorization->can('funcionario.editar_documentos');
    $canEditBank = $authorization->can('funcionario.editar_dados_bancarios');
    $employeeId = $isEditing ? employee_posted_positive_int('id') : null;

    $allowedFields = $generalFields;
    if ($canEditSalary) $allowedFields = array_merge($allowedFields, $salaryFields);
    if ($canEditDocuments) $allowedFields = array_merge($allowedFields, $documentFields);
    if ($canEditBank) $allowedFields = array_merge($allowedFields, $bankFields);
    $input = [];
    foreach ($allowedFields as $field) $input[$field] = $_POST[$field] ?? '';

    $data = EmployeeFormData::fromArray($input);
    $photoUpload = isset($_FILES['photo']) && is_array($_FILES['photo']) ? $_FILES['photo'] : null;
    $photoStorage = new EmployeePhotoStorage(dirname(__DIR__) . '/storage');
    $hasNewPhoto = $photoStorage->validate($photoUpload);
    $service = $application->employeeManagement();
    $oldPhoto = null;

    if ($employeeId === null) {
        $employee = $service->createEmployee($data);
        $employeeId = $employee->id();
        $message = 'Funcionário cadastrado com o código ' . $employee->displayCode() . '.';
    } else {
        $oldPhoto = $service->getEmployee($employeeId)->photo();
        $service->updateEmployee($employeeId, $data, $canEditSalary, $canEditDocuments, $canEditBank);
        $message = 'Funcionário atualizado com sucesso.';
    }

    try {
        if ($hasNewPhoto) {
            $newPhoto = $photoStorage->store($photoUpload, $employeeId);
            if ($newPhoto !== null) {
                try { $service->updateEmployeePhoto($employeeId, $newPhoto); }
                catch (Throwable $exception) { $photoStorage->delete($newPhoto); throw $exception; }
                $photoStorage->delete($oldPhoto);
            }
        } elseif ($isEditing && ($_POST['remove_photo'] ?? '') === '1' && $oldPhoto !== null) {
            $service->updateEmployeePhoto($employeeId, null);
            $photoStorage->delete($oldPhoto);
        }
    } catch (Throwable $photoException) {
        error_log('Employee photo save failed: ' . $photoException->getMessage());
        $session->flash('warning', 'Os dados foram salvos, mas não foi possível atualizar a foto.');
    }

    $session->flash('success', $message);
} catch (InvalidArgumentException $exception) {
    $recovery = ['id' => $rawEmployeeId, 'code' => $_POST['code'] ?? ''];
    foreach ($generalFields as $field) $recovery[$field] = $_POST[$field] ?? '';
    employee_store_form_recovery($isEditing ? 'edit' : 'create', $recovery, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Employee save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o funcionário.');
}

employee_redirect($application, 'funcionarios.php');
