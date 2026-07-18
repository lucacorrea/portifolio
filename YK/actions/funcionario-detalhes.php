<?php

declare(strict_types=1);

require __DIR__ . '/funcionario-action-common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    [$application] = employee_action_context('funcionario.visualizar', false);
    $rawId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($rawId)) throw new InvalidArgumentException('Funcionário inválido.');

    $editing = ($_GET['mode'] ?? 'view') === 'edit';
    $authorization = $application->authorization();
    if ($editing) $authorization->requirePermission('funcionario.editar');

    $employee = $application->employeeManagement()->getEmployee($rawId);
    $payload = $employee->toArray();
    $payload['id'] = $employee->id();
    $payload['code'] = $employee->displayCode();
    $payload['name'] = $employee->name();
    $payload['photo_url'] = $employee->photo() === null ? null : 'funcionario-foto.php?id=' . $employee->id() . '&v=' . rawurlencode(basename($employee->photo()));
    unset($payload['codigo'], $payload['nome'], $payload['foto']);

    $canSalary = $authorization->can($editing ? 'funcionario.editar_salario' : 'funcionario.visualizar_salario');
    $canDocuments = $authorization->can($editing ? 'funcionario.editar_documentos' : 'funcionario.visualizar_documentos');
    $canBank = $authorization->can($editing ? 'funcionario.editar_dados_bancarios' : 'funcionario.visualizar_dados_bancarios');
    if (!$canSalary) unset($payload['salario']);
    if (!$canBank) foreach (['banco','agencia','conta','tipo_conta','pix'] as $field) unset($payload[$field]);
    if (!$canDocuments) foreach ([
        'rg_numero','rg_uf','rg_orgao_emissor','rg_data_emissao','cpf_numero',
        'titulo_eleitor_numero','titulo_eleitor_uf','titulo_eleitor_secao','titulo_eleitor_zona',
        'reservista_numero','reservista_data_emissao','certidao_nascimento_numero',
        'certidao_nascimento_cidade','certidao_nascimento_livro','certidao_nascimento_folha',
        'certidao_nascimento_data_emissao','carteira_trabalho_numero','carteira_trabalho_serie',
        'carteira_trabalho_uf','pis_pasep_numero','cnh_numero_registro','cnh_categoria','cnh_data_vencimento',
    ] as $field) unset($payload[$field]);

    session_write_close();
    echo json_encode(['employee' => $payload], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (App\Access\Exception\AuthorizationException) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
} catch (Throwable $exception) {
    error_log('Employee details failed: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Não foi possível carregar o funcionário.']);
}
