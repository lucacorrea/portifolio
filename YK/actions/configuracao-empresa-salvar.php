<?php

declare(strict_types=1);

use App\Company\Service\CompanyLogoStorage;

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('configuracao.editar');

$logoStorage = new CompanyLogoStorage(dirname(__DIR__) . '/storage');
$newLogo = null;

try {
    $user = $application->authorization()->requireLogin();
    $companySettings = $application->companySettings();
    $company = $companySettings->get();
    $currentLogo = trim((string) ($company['logo'] ?? ''));
    $upload = isset($_FILES['logo_file']) && is_array($_FILES['logo_file'])
        ? $_FILES['logo_file']
        : null;
    $newLogo = $logoStorage->store($upload);

    $payload = $_POST;
    $payload['logo'] = $newLogo
        ?? ((string) ($_POST['remove_logo'] ?? '') === '1' ? null : $currentLogo);
    $companySettings->save($payload, $user->id());
    // Versões anteriores permanecem imutáveis porque recibos guardam esta referência como snapshot.
    $session->flash('success', 'Dados da empresa atualizados.');
} catch (InvalidArgumentException $exception) {
    $logoStorage->delete($newLogo);
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    $logoStorage->delete($newLogo);
    error_log('Company settings failed: ' . $exception->getMessage());
    $session->flash('danger', 'Nao foi possivel salvar os dados da empresa.');
}

os_redirect_back($application, 'configuracoes.php');
