<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Core\Response;
use App\Repositories\UserCompanyRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\CompanyContextService;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$user = Auth::user();

if (!$user) {
    Response::redirect('../../login.php');
}

try {
    if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Token de segurança inválido. Atualize a página e tente novamente.');
    }

    $empresaId = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);

    if (!$empresaId || $empresaId <= 0) {
        throw new RuntimeException('Empresa inválida.');
    }

    $memberships = new UserCompanyRepository();
    $membership = $memberships->findMembership((int)$user['id'], (int)$empresaId);

    if (
        !$membership
        || (int)$membership['usuario_ativo'] !== 1
        || (int)$membership['vinculo_ativo'] !== 1
        || (int)$membership['empresa_ativa'] !== 1
    ) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    (new CompanyContextService($memberships))->activate(
        (int)$user['id'],
        (int)$empresaId,
        !empty($user['company_selection_pending']) ? 'selecionar' : 'trocar'
    );

    Response::redirect('../../index.php');
} catch (Throwable $e) {
    log_app_exception($e);
    $_SESSION['company_selection_flash'] = $e->getMessage();
    Response::redirect('../../selecionar-loja.php');
}
