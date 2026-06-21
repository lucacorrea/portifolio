<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

require_once __DIR__ . '/../backend/Repositories/UserRepository.php';
require_once __DIR__ . '/../backend/Repositories/SettingsRepository.php';
require_once __DIR__ . '/../backend/Services/UserService.php';
require_once __DIR__ . '/../backend/Services/SettingsService.php';

use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Security\Auth;
use App\Services\CompanyBrandService;
use App\Services\PwaManifestService;
use App\Services\SettingsService;
use App\Services\UserService;

Auth::requireLogin();

$user = Auth::user();

if (!$user) {
    header('Location: ../login.php');
    exit;
}

$empresaId = (int)$user['empresa_id'];
$currentUserId = (int)$user['id'];
$currentNivel = (string)$user['nivel'];

$CONFIG_PERMISSIONS = [
    'usuarios' => ['admin'],
    'empresa' => ['admin', 'gerente'],
    'aplicativo' => ['admin', 'gerente'],
    'comprovantes' => ['admin', 'gerente', 'operador'],
    'vencimentos' => ['admin', 'gerente', 'estoquista'],
    'estoque' => ['admin', 'gerente', 'estoquista'],
    'pagamentos' => ['admin', 'gerente'],
    'caixa' => ['admin', 'gerente'],
    'seguranca' => ['admin'],
];

$ACTION_PERMISSIONS = [
    'salvar_empresa' => 'empresa',
    'salvar_aplicativo' => 'aplicativo',
    'salvar_comprovante' => 'comprovantes',
    'salvar_vencimento' => 'vencimentos',
    'salvar_estoque' => 'estoque',
    'salvar_pagamento' => 'pagamentos',
    'salvar_caixa' => 'caixa',
    'salvar_seguranca' => 'seguranca',
    'criar_usuario' => 'usuarios',
    'editar_usuario' => 'usuarios',
    'ativar_usuario' => 'usuarios',
    'inativar_usuario' => 'usuarios',
];

$pageId = 'configuracoes';
$pageTitle = 'Configurações';
$activeMenu = 'mais';

$settingsService = new SettingsService(new SettingsRepository());
$brandService = new CompanyBrandService();
$pwaManifestService = new PwaManifestService($brandService);
$userRepository = new UserRepository();
$userService = new UserService($userRepository);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_configuracoes'])) {
    $_SESSION['csrf_configuracoes'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checked(mixed $value): string
{
    return ((int)$value === 1) ? 'checked' : '';
}

function selected(mixed $actual, mixed $expected): string
{
    return ((string)$actual === (string)$expected) ? 'selected' : '';
}

function redirectConfig(string $type, string $message): void
{
    $_SESSION['config_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: configuracoes.php');
    exit;
}

function canAccessConfig(array $permissions, string $module, string $nivel): bool
{
    return in_array($nivel, $permissions[$module] ?? [], true);
}

function requireConfigPermission(array $permissions, string $module, string $nivel): void
{
    if (!canAccessConfig($permissions, $module, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function moduleForAction(array $actions, string $acao): string
{
    if (!isset($actions[$acao])) {
        throw new RuntimeException('Ação inválida.');
    }

    return $actions[$acao];
}


function saveCompanyConfiguration(
    SettingsService $settingsService,
    int $empresaId,
    array $post,
    array $files
): void {
    $settingsService->saveEmpresa($empresaId, $post);

    $logoFile = $files['company_logo'] ?? null;
    $uploadError = is_array($logoFile)
        ? (int)($logoFile['error'] ?? UPLOAD_ERR_NO_FILE)
        : UPLOAD_ERR_NO_FILE;

    /*
     * Se uma nova imagem foi enviada, ela tem prioridade.
     * Caso contrário, aplica a remoção solicitada pelo usuário.
     */
    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        $settingsService->saveCompanyLogo($empresaId, $logoFile);
        return;
    }

    if (isset($post['remove_company_logo'])) {
        $settingsService->removeCompanyLogo($empresaId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = (string)($_POST['csrf_token'] ?? '');

        if (!hash_equals((string)$_SESSION['csrf_configuracoes'], $token)) {
            throw new RuntimeException('Token de segurança inválido. Recarregue a página e tente novamente.');
        }

        $acao = (string)($_POST['acao'] ?? '');
        $module = moduleForAction($ACTION_PERMISSIONS, $acao);
        requireConfigPermission($CONFIG_PERMISSIONS, $module, $currentNivel);

        match ($acao) {
            'salvar_empresa' => saveCompanyConfiguration(
                $settingsService,
                $empresaId,
                $_POST,
                $_FILES
            ),
            'salvar_aplicativo' => $settingsService->saveAppSettings($empresaId, $_POST),
            'salvar_comprovante' => $settingsService->saveReceiptRules($empresaId, $_POST),
            'salvar_vencimento' => $settingsService->saveDueRules($empresaId, $_POST),
            'salvar_estoque' => $settingsService->saveStockRules($empresaId, $_POST),
            'salvar_pagamento' => $settingsService->savePaymentRules($empresaId, $_POST),
            'salvar_caixa' => $settingsService->saveCashRules($empresaId, $_POST),
            'salvar_seguranca' => $settingsService->saveSecurityRules($empresaId, $_POST),
            'criar_usuario' => $userService->create($empresaId, $_POST),
            'editar_usuario' => $userService->update((int)($_POST['usuario_id'] ?? 0), $empresaId, $_POST),
            'ativar_usuario' => $userService->activate((int)($_POST['usuario_id'] ?? 0), $empresaId),
            'inativar_usuario' => $userService->deactivate((int)($_POST['usuario_id'] ?? 0), $empresaId, $currentUserId),
            default => throw new RuntimeException('Ação inválida.'),
        };

        redirectConfig('success', 'Configuração salva com sucesso.');
    } catch (Throwable $e) {
        redirectConfig('danger', $e->getMessage());
    }
}

try {
    $empresa = $settingsService->getEmpresa($empresaId);
    $config = $settingsService->getConfiguracoes($empresaId);
    $usuarios = $userRepository->findByCompany($empresaId);
} catch (Throwable $e) {
    $empresa = [];
    $config = [];
    $usuarios = [];
    $_SESSION['config_flash'] = [
        'type' => 'danger',
        'message' => $e->getMessage(),
    ];
}

$flash = $_SESSION['config_flash'] ?? null;
unset($_SESSION['config_flash']);

$companyBrand = $brandService->getForCompany($empresaId, '../');
$companyLogoPreviewUrl = (string)$companyBrand['logo_url'];
$hasCompanyLogo = (bool)$companyBrand['has_logo'];
$companyInitials = (string)$companyBrand['initials'];
$appSettings = $pwaManifestService->appSettingsForCompany($empresaId);
$gdAvailable = extension_loaded('gd');

$csrfToken = (string)$_SESSION['csrf_configuracoes'];
$visibleConfigModules = array_filter(
    array_keys($CONFIG_PERMISSIONS),
    fn (string $module): bool => canAccessConfig($CONFIG_PERMISSIONS, $module, $currentNivel)
);

require_once __DIR__ . '/layout/header.php';
?>

<style>
    :root {
        --cfg-bg: #f6f8fb;
        --cfg-card: #ffffff;
        --cfg-border: rgba(15, 23, 42, .08);
        --cfg-text: #111827;
        --cfg-muted: #64748b;
        --cfg-soft: #f1f5f9;
        --cfg-primary: #111827;
        --cfg-danger: #dc2626;
        --cfg-success: #16a34a;
        --cfg-shadow: 0 14px 35px rgba(15, 23, 42, .08);
    }

    .plain-header,
    .content-pad {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding-left: clamp(12px, 3vw, 24px);
        padding-right: clamp(12px, 3vw, 24px);
        box-sizing: border-box;
    }

    .content-pad {
        padding-bottom: 96px;
    }

    .page-title-row {
        gap: 14px;
        align-items: center;
    }

    .config-alert {
        border-radius: 16px;
        padding: 13px 15px;
        margin-bottom: 16px;
        font-weight: 800;
        line-height: 1.4;
    }

    .config-alert.success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .config-alert.danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .readonly-note {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        padding: 12px;
        border-radius: 16px;
        font-size: .9rem;
        line-height: 1.45;
        margin-bottom: 16px;
    }

    .settings-home {
        display: grid;
        gap: 18px;
    }

    .settings-intro {
        background: linear-gradient(135deg, #111827, #334155);
        color: #fff;
        border-radius: 26px;
        padding: clamp(18px, 4vw, 28px);
        box-shadow: var(--cfg-shadow);
        overflow: hidden;
        position: relative;
    }

    .settings-intro::after {
        content: '';
        position: absolute;
        width: 180px;
        height: 180px;
        right: -60px;
        top: -60px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .10);
    }

    .settings-intro p {
        margin: 0 0 8px;
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        opacity: .75;
        font-weight: 900;
    }

    .settings-intro h2 {
        margin: 0;
        font-size: clamp(1.25rem, 4vw, 2rem);
        line-height: 1.15;
        max-width: 680px;
    }

    .settings-launch-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .settings-launch-card {
        min-height: 168px;
        border: 1px solid var(--cfg-border);
        background: rgba(255, 255, 255, .92);
        border-radius: 26px;
        padding: 18px;
        box-shadow: var(--cfg-shadow);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: flex-start;
        cursor: pointer;
        text-align: left;
        width: 100%;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .settings-launch-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 44px rgba(15, 23, 42, .12);
        border-color: rgba(17, 24, 39, .18);
    }

    .settings-launch-card:active {
        transform: scale(.98);
    }

    .settings-launch-icon {
        width: 48px;
        height: 48px;
        border-radius: 17px;
        background: radial-gradient(circle at 35% 30%, #ffffff, #eef2ff 45%, #dbeafe 100%);
        box-shadow: inset 0 -8px 16px rgba(15, 23, 42, .08), 0 8px 18px rgba(15, 23, 42, .08);
        display: grid;
        place-items: center;
        color: #111827;
        font-size: 1.35rem;
        margin-bottom: 16px;
    }

    .settings-launch-card h3 {
        margin: 0 0 5px;
        color: var(--cfg-text);
        font-size: 1rem;
        line-height: 1.2;
        font-weight: 950;
    }

    .settings-launch-card p {
        margin: 0;
        color: var(--cfg-muted);
        font-size: .82rem;
        line-height: 1.35;
    }

    .settings-launch-arrow {
        margin-top: 14px;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        background: #f8fafc;
        color: #111827;
        display: grid;
        place-items: center;
        font-size: 1.2rem;
        font-weight: 900;
    }

    .settings-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(7px);
    }

    .settings-modal.is-open {
        display: flex;
    }

    .settings-modal-panel {
        width: min(820px, 100%);
        max-height: min(90vh, 860px);
        overflow-y: auto;
        background: #fff;
        border-radius: 28px;
        box-shadow: 0 30px 90px rgba(15, 23, 42, .34);
        border: 1px solid rgba(255, 255, 255, .22);
    }

    .settings-modal-panel.large {
        width: min(1080px, 100%);
    }

    .settings-modal-header {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fff;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        padding: 22px 22px 14px;
        border-bottom: 1px solid #eef2f7;
        border-radius: 28px 28px 0 0;
    }

    .settings-modal-header h3 {
        margin: 0 0 5px;
        color: #111827;
        font-size: 1.18rem;
        line-height: 1.2;
    }

    .settings-modal-header p {
        margin: 0;
        color: #64748b;
        font-size: .88rem;
        line-height: 1.4;
    }

    .settings-modal-close {
        width: 40px;
        min-width: 40px;
        height: 40px;
        border-radius: 999px;
        border: 0;
        background: #f1f5f9;
        color: #111827;
        font-size: 1.45rem;
        line-height: 1;
        cursor: pointer;
        font-weight: 900;
    }

    .settings-modal-body {
        padding: 20px 22px 22px;
    }

    body.modal-open {
        overflow: hidden;
    }

    .settings-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        width: 100%;
        min-width: 0;
    }

    .settings-form-grid.three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .settings-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .settings-field.full {
        grid-column: 1 / -1;
    }

    .settings-field label {
        font-size: .82rem;
        font-weight: 850;
        color: #334155;
        line-height: 1.3;
    }

    .settings-field input,
    .settings-field select {
        width: 100%;
        max-width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        min-height: 46px;
        padding: 10px 12px;
        outline: none;
        background: #fff;
        color: var(--cfg-text);
        box-sizing: border-box;
        font-size: 16px;
    }

    .settings-field input:focus,
    .settings-field select:focus {
        border-color: var(--cfg-primary);
        box-shadow: 0 0 0 3px rgba(17, 24, 39, .08);
    }

    .company-logo-panel {
        display: grid;
        grid-template-columns: 124px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 14px;
        border: 1px solid #dbe3ef;
        border-radius: 18px;
        background: #f8fafc;
    }

    .company-logo-preview {
        width: 124px;
        height: 124px;
        display: grid;
        place-items: center;
        overflow: hidden;
        border: 1px solid #dbe3ef;
        border-radius: 20px;
        background: #fff;
    }

    .company-logo-preview img {
        display: block;
        width: 100%;
        height: 100%;
        padding: 10px;
        object-fit: contain;
    }

    .company-logo-initials {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        color: #111827;
        background: #eef2ff;
        font-size: 2rem;
        font-weight: 950;
        letter-spacing: .04em;
    }

    .pwa-preview-grid {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
        padding: 14px;
        border: 1px solid #dbe3ef;
        border-radius: 18px;
        background: #f8fafc;
    }

    .pwa-icon-preview {
        width: 96px;
        height: 96px;
        display: grid;
        place-items: center;
        overflow: hidden;
        border-radius: 22px;
        background: #fff;
        border: 1px solid #dbe3ef;
    }

    .pwa-icon-preview img {
        width: 100%;
        height: 100%;
        padding: 9px;
        object-fit: contain;
    }

    .pwa-status {
        margin: 0;
        color: #475569;
        font-size: .86rem;
        line-height: 1.45;
        font-weight: 750;
    }

    .pwa-status.error {
        color: var(--cfg-danger);
    }

    .pwa-status.success {
        color: var(--cfg-success);
    }

    .pwa-install-box {
        display: grid;
        gap: 10px;
        margin-top: 14px;
        padding: 14px;
        border-radius: 18px;
        border: 1px solid #dbe3ef;
        background: #fff;
    }

    .company-logo-controls {
        display: grid;
        gap: 10px;
        min-width: 0;
    }

    .company-logo-controls strong {
        color: var(--cfg-text);
        font-size: .95rem;
    }

    .company-logo-help,
    .company-logo-status {
        margin: 0;
        color: var(--cfg-muted);
        font-size: .8rem;
        line-height: 1.45;
    }

    .company-logo-status.error {
        color: var(--cfg-danger);
        font-weight: 850;
    }

    .company-logo-status.success {
        color: var(--cfg-success);
        font-weight: 850;
    }

    .settings-field input[type="file"] {
        min-height: 48px;
        padding: 6px;
        background: #fff;
    }

    .settings-field input[type="file"]::file-selector-button {
        min-height: 34px;
        margin-right: 10px;
        padding: 7px 12px;
        border: 0;
        border-radius: 10px;
        background: var(--cfg-primary);
        color: #fff;
        cursor: pointer;
        font-weight: 850;
    }

    .company-logo-remove {
        display: flex;
        align-items: center;
        gap: 9px;
        color: #475569;
        font-size: .84rem;
        font-weight: 800;
    }

    .company-logo-remove input {
        width: 18px;
        min-width: 18px;
        height: 18px;
    }

    .settings-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .settings-btn {
        border: 0;
        border-radius: 14px;
        min-height: 44px;
        padding: 10px 16px;
        font-weight: 950;
        cursor: pointer;
        background: var(--cfg-primary);
        color: #fff;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        transition: transform .15s ease, opacity .15s ease;
    }

    .settings-btn:hover {
        opacity: .94;
    }

    .settings-btn:active {
        transform: scale(.98);
    }

    .settings-btn.secondary {
        background: #e5e7eb;
        color: var(--cfg-text);
    }

    .settings-btn.ghost {
        background: #f8fafc;
        color: #111827;
        border: 1px solid #e2e8f0;
    }

    .settings-btn.danger {
        background: var(--cfg-danger);
        color: #fff;
    }

    .settings-btn.success {
        background: var(--cfg-success);
        color: #fff;
    }

    .settings-switches {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        width: 100%;
        min-width: 0;
    }

    .settings-check {
        border: 1px solid #e5e7eb;
        border-radius: 15px;
        padding: 12px;
        display: flex;
        gap: 10px;
        align-items: center;
        color: #334155;
        font-weight: 850;
        font-size: .88rem;
        line-height: 1.35;
        min-width: 0;
        background: #fff;
    }

    .settings-check input {
        width: 18px;
        min-width: 18px;
        height: 18px;
    }

    .settings-users-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .settings-users-toolbar strong {
        display: block;
        color: #111827;
        font-size: 1rem;
    }

    .settings-users-toolbar span {
        display: block;
        color: #64748b;
        font-size: .84rem;
        margin-top: 2px;
    }

    .settings-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
    }

    .settings-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 860px;
        background: #fff;
    }

    .settings-table th,
    .settings-table td {
        padding: 13px 12px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: middle;
        font-size: .9rem;
    }

    .settings-table tr:last-child td {
        border-bottom: 0;
    }

    .settings-table th {
        color: #475569;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        white-space: nowrap;
        background: #f8fafc;
    }

    .user-main {
        font-weight: 950;
        color: #111827;
    }

    .user-email {
        color: #64748b;
        font-size: .84rem;
        margin-top: 2px;
        word-break: break-word;
    }

    .status-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .75rem;
        font-weight: 950;
        white-space: nowrap;
    }

    .status-badge.on {
        background: #dcfce7;
        color: #166534;
    }

    .status-badge.off {
        background: #fee2e2;
        color: #991b1b;
    }

    .table-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .table-actions form {
        margin: 0;
    }

    @media (max-width: 1100px) {
        .settings-launch-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 860px) {
        .settings-launch-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .settings-form-grid.three {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .plain-header,
        .content-pad {
            padding-left: 12px;
            padding-right: 12px;
        }

        .plain-header h1 {
            font-size: 1.45rem;
            line-height: 1.15;
        }

        .settings-intro {
            border-radius: 22px;
        }

        .settings-launch-grid {
            gap: 12px;
        }

        .settings-launch-card {
            min-height: 154px;
            border-radius: 22px;
            padding: 14px;
        }

        .settings-launch-icon {
            width: 44px;
            height: 44px;
            border-radius: 15px;
            margin-bottom: 12px;
        }

        .settings-launch-card h3 {
            font-size: .94rem;
        }

        .settings-launch-card p {
            font-size: .78rem;
        }

        .settings-form-grid,
        .settings-form-grid.three,
        .settings-switches {
            grid-template-columns: 1fr;
            gap: 12px;
        }


        .company-logo-panel {
            grid-template-columns: 1fr;
            justify-items: stretch;
        }

        .company-logo-preview {
            width: 112px;
            height: 112px;
            justify-self: center;
        }

        .settings-actions {
            justify-content: stretch;
        }

        .settings-btn {
            width: 100%;
            min-height: 46px;
        }

        .settings-modal {
            align-items: flex-end;
            padding: 0;
        }

        .settings-modal-panel,
        .settings-modal-panel.large {
            width: 100%;
            max-height: 92vh;
            border-radius: 26px 26px 0 0;
        }

        .settings-modal-header {
            border-radius: 26px 26px 0 0;
            padding: 16px 14px 12px;
        }

        .settings-modal-body {
            padding: 14px;
        }

        .settings-check {
            align-items: flex-start;
        }

        .settings-check input {
            margin-top: 1px;
        }

        .settings-users-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .settings-table-wrap {
            overflow: visible;
            border: 0;
        }

        .settings-table {
            min-width: 0;
            display: block;
            width: 100%;
            background: transparent;
        }

        .settings-table thead {
            display: none;
        }

        .settings-table tbody,
        .settings-table tr,
        .settings-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .settings-table tr {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            margin-bottom: 12px;
            padding: 12px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .04);
        }

        .settings-table td {
            border-bottom: 0;
            padding: 7px 0;
            text-align: left;
            font-size: .9rem;
            word-break: break-word;
        }

        .settings-table td::before {
            content: attr(data-label);
            display: block;
            font-weight: 950;
            color: #475569;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 3px;
        }

        .table-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }

        .table-actions form,
        .table-actions button {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .plain-header,
        .content-pad {
            padding-left: 10px;
            padding-right: 10px;
        }

        .settings-launch-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .settings-launch-card {
            min-height: 145px;
            border-radius: 20px;
            padding: 12px;
        }

        .settings-launch-icon {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            font-size: 1.12rem;
        }

        .settings-launch-card h3 {
            font-size: .88rem;
        }

        .settings-launch-card p {
            font-size: .73rem;
        }

        .settings-launch-arrow {
            width: 30px;
            height: 30px;
        }

        .settings-field input,
        .settings-field select {
            min-height: 46px;
            border-radius: 12px;
        }
    }
</style>

<header class="plain-header">
    <div class="page-title-row">
        <div>
            <p class="micro-label dark-text">Administração</p>
            <h1>Configurações</h1>
        </div>
        <a class="icon-btn light" href="../index.php">×</a>
    </div>
</header>

<section class="content-pad">
    <?php if ($flash): ?>
        <div class="config-alert <?= h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($currentNivel !== 'admin'): ?>
        <div class="readonly-note">
            Você está acessando como <strong><?= h($currentNivel) ?></strong>. Apenas as configurações permitidas para seu nível serão exibidas.
        </div>
    <?php endif; ?>

    <div class="settings-home">
        <div class="settings-intro">
            <p>Gestão comercial premium</p>
            <h2>Configure empresa, usuários, vendas, estoque, pagamentos e segurança.</h2>
        </div>

        <div class="settings-launch-grid">
            <?php if (!$visibleConfigModules): ?>
                <div class="readonly-note" style="grid-column: 1 / -1;">
                    Seu nível de acesso não possui permissões para alterar configurações.
                </div>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'usuarios', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalUsuarios">
                <span class="settings-launch-icon">👥</span>
                <span>
                    <h3>Usuários</h3>
                    <p>Permissões, acessos e operadores.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'empresa', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalEmpresa">
                <span class="settings-launch-icon">🏪</span>
                <span>
                    <h3>Empresa</h3>
                    <p>Dados principais do negócio.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'aplicativo', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalAplicativo">
                <span class="settings-launch-icon">📱</span>
                <span>
                    <h3>Aplicativo</h3>
                    <p>Nome, instalação e ícones do PWA.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'comprovantes', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalComprovantes">
                <span class="settings-launch-icon">🧾</span>
                <span>
                    <h3>Comprovantes</h3>
                    <p>Modelo e emissão de recibos.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'vencimentos', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalVencimentos">
                <span class="settings-launch-icon">📅</span>
                <span>
                    <h3>Vencimentos</h3>
                    <p>Alertas e prazos do sistema.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'estoque', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalEstoque">
                <span class="settings-launch-icon">📦</span>
                <span>
                    <h3>Estoque</h3>
                    <p>Bloqueios e estoque mínimo.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'pagamentos', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalPagamentos">
                <span class="settings-launch-icon">💳</span>
                <span>
                    <h3>Pagamentos</h3>
                    <p>Pix, dinheiro, cartão e fiado.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'caixa', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalCaixa">
                <span class="settings-launch-icon">💰</span>
                <span>
                    <h3>Vendas</h3>
                    <p>Descontos, caixa e regras.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>

            <?php if (canAccessConfig($CONFIG_PERMISSIONS, 'seguranca', $currentNivel)): ?>
            <button class="settings-launch-card" type="button" data-open-modal="modalSeguranca">
                <span class="settings-launch-icon">🔐</span>
                <span>
                    <h3>Segurança</h3>
                    <p>Auditoria, PIN e notificações.</p>
                </span>
                <span class="settings-launch-arrow">→</span>
            </button>
            <?php endif; ?>
        </div>

        <a class="danger-btn section-gap" href="../logout.php">Sair do sistema</a>
    </div>
</section>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'usuarios', $currentNivel)): ?>
<div class="settings-modal" id="modalUsuarios" aria-hidden="true">
    <div class="settings-modal-panel large" role="dialog" aria-modal="true" aria-labelledby="modalUsuariosTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalUsuariosTitle">Usuários e permissões</h3>
                <p>Listagem completa dos usuários vinculados à empresa atual.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <div class="settings-users-toolbar">
                <div>
                    <strong>Usuários cadastrados</strong>
                    <span><?= count($usuarios) ?> usuário(s) encontrado(s).</span>
                </div>

                <button class="settings-btn" type="button" data-open-modal="modalCreateUser">Novo usuário</button>
            </div>

            <div class="settings-table-wrap">
                <table class="settings-table">
                    <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Telefone</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Último login</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$usuarios): ?>
                        <tr>
                            <td data-label="Usuários" colspan="6">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td data-label="Usuário">
                                <div class="user-main"><?= h($usuario['nome']) ?></div>
                                <div class="user-email"><?= h($usuario['email']) ?></div>
                            </td>

                            <td data-label="Telefone"><?= h($usuario['telefone'] ?? '-') ?></td>
                            <td data-label="Nível"><?= h($usuario['nivel']) ?></td>

                            <td data-label="Status">
                                <?php if ((int)$usuario['ativo'] === 1): ?>
                                    <span class="status-badge on">Ativo</span>
                                <?php else: ?>
                                    <span class="status-badge off">Inativo</span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Último login"><?= h($usuario['ultimo_login_em'] ?? '-') ?></td>

                            <td data-label="Ações">
                                <div class="table-actions">
                                    <button
                                        class="settings-btn secondary"
                                        type="button"
                                        data-open-edit-user
                                        data-id="<?= (int)$usuario['id'] ?>"
                                        data-nome="<?= h($usuario['nome']) ?>"
                                        data-email="<?= h($usuario['email']) ?>"
                                        data-telefone="<?= h($usuario['telefone'] ?? '') ?>"
                                        data-nivel="<?= h($usuario['nivel']) ?>"
                                        data-ativo="<?= (int)$usuario['ativo'] ?>"
                                    >
                                        Editar
                                    </button>

                                    <?php if ((int)$usuario['ativo'] === 1): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="acao" value="inativar_usuario">
                                            <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                            <button class="settings-btn danger" type="submit" onclick="return confirm('Inativar este usuário?')">Inativar</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="acao" value="ativar_usuario">
                                            <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                            <button class="settings-btn success" type="submit">Ativar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'empresa', $currentNivel)): ?>
<div class="settings-modal" id="modalEmpresa" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalEmpresaTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalEmpresaTitle">Dados da empresa</h3>
                <p>Informações principais usadas no sistema e nos comprovantes.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_empresa">

                <div class="settings-form-grid">
                    <div class="settings-field full">
                        <label for="company_logo">Logo da empresa</label>

                        <div class="company-logo-panel">
                            <div class="company-logo-preview">
                                <?php if ($hasCompanyLogo): ?>
                                    <img
                                        id="companyLogoPreview"
                                        src="<?= h($companyLogoPreviewUrl) ?>"
                                        alt="Prévia da logo da empresa"
                                        data-current-logo="<?= h($companyLogoPreviewUrl) ?>"
                                    >
                                <?php else: ?>
                                    <span
                                        id="companyLogoPreview"
                                        class="company-logo-initials"
                                        data-current-logo=""
                                    ><?= h($companyInitials) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="company-logo-controls">
                                <strong><?= $hasCompanyLogo ? 'Logo atual cadastrada' : 'Nenhuma logo personalizada cadastrada' ?></strong>

                                <input
                                    type="file"
                                    id="company_logo"
                                    name="company_logo"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <p class="company-logo-help">
                                    Envie uma imagem JPG, PNG ou WEBP com até 2 MB. A logo será usada na interface do sistema, mas não será inserida na nota fiscal.
                                </p>

                                <p class="company-logo-status" id="companyLogoStatus" aria-live="polite"></p>

                                <?php if ($hasCompanyLogo): ?>
                                    <label class="company-logo-remove" for="remove_company_logo">
                                        <input
                                            type="checkbox"
                                            id="remove_company_logo"
                                            name="remove_company_logo"
                                            value="1"
                                        >
                                        Remover a logo atual ao salvar
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="settings-field">
                        <label for="nome">Razão social / Nome</label>
                        <input type="text" id="nome" name="nome" maxlength="180" required value="<?= h($empresa['nome'] ?? '') ?>">
                    </div>

                    <div class="settings-field">
                        <label for="nome_fantasia">Nome fantasia</label>
                        <input type="text" id="nome_fantasia" name="nome_fantasia" maxlength="180" value="<?= h($empresa['nome_fantasia'] ?? '') ?>">
                    </div>

                    <div class="settings-field">
                        <label for="cpf_cnpj">CPF/CNPJ</label>
                        <input type="text" id="cpf_cnpj" name="cpf_cnpj" maxlength="20" value="<?= h($empresa['cpf_cnpj'] ?? '') ?>">
                    </div>

                    <div class="settings-field">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" maxlength="30" value="<?= h($empresa['telefone'] ?? '') ?>">
                    </div>

                    <div class="settings-field full">
                        <label for="endereco">Endereço</label>
                        <input type="text" id="endereco" name="endereco" maxlength="255" value="<?= h($empresa['endereco'] ?? '') ?>">
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'aplicativo', $currentNivel)): ?>
<div class="settings-modal" id="modalAplicativo" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalAplicativoTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalAplicativoTitle">Aplicativo no celular</h3>
                <p>Defina o nome instalado e verifique se a empresa possui logo válida para gerar os ícones.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_aplicativo">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="app_name">Nome do aplicativo</label>
                        <input type="text" id="app_name" name="app_name" maxlength="180" required value="<?= h($appSettings['app_name'] ?? '') ?>">
                    </div>

                    <div class="settings-field">
                        <label for="app_short_name">Nome curto</label>
                        <input type="text" id="app_short_name" name="app_short_name" maxlength="40" required value="<?= h($appSettings['app_short_name'] ?? '') ?>">
                    </div>

                    <div class="settings-field full">
                        <label>Prévia da logo</label>
                        <div class="pwa-preview-grid">
                            <div class="pwa-icon-preview">
                                <?php if ($hasCompanyLogo): ?>
                                    <img src="<?= h($companyLogoPreviewUrl) ?>" alt="Logo usada para gerar o ícone do aplicativo">
                                <?php else: ?>
                                    <span class="company-logo-initials"><?= h($companyInitials) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if (!$hasCompanyLogo): ?>
                                    <p class="pwa-status error">Instalação bloqueada: cadastre uma logo da empresa em JPG, PNG ou WEBP.</p>
                                <?php elseif (!$gdAvailable): ?>
                                    <p class="pwa-status error">Instalação bloqueada: a extensão GD do PHP não está disponível para gerar os ícones.</p>
                                <?php else: ?>
                                    <p class="pwa-status success">Logo válida. Os ícones 192, 512, maskable e Apple são gerados a partir do arquivo da empresa.</p>
                                <?php endif; ?>
                                <p class="pwa-status">Nome e ícone são definidos pelo manifesto no momento da instalação. Alguns navegadores não atualizam imediatamente apps já instalados; após mudar nome ou logo, pode ser necessário remover e instalar novamente.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pwa-install-box"
                     data-pwa-install
                     data-has-logo="<?= $hasCompanyLogo ? '1' : '0' ?>"
                     data-has-app-name="<?= trim((string)($appSettings['app_name'] ?? '')) !== '' ? '1' : '0' ?>">
                    <p class="pwa-status" data-pwa-status>Status de instalação: verificando disponibilidade.</p>
                    <button class="settings-btn success" type="button" data-pwa-install-button disabled>Instalar aplicativo</button>
                    <p class="pwa-status">Android/Chrome ou Edge: toque em instalar quando o botão estiver disponível.</p>
                    <p class="pwa-status">iPhone/iPad: abra no Safari, toque em Compartilhar e escolha Adicionar à Tela de Início. O iOS não expõe prompt programático de instalação.</p>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar aplicativo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'comprovantes', $currentNivel)): ?>
<div class="settings-modal" id="modalComprovantes" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalComprovantesTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalComprovantesTitle">Comprovantes</h3>
                <p>Defina emissão automática e modelo do comprovante.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_comprovante">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="receipt_mode">Emissão</label>
                        <select id="receipt_mode" name="receipt_mode">
                            <option value="perguntar" <?= selected($config['receipt_mode'] ?? 'perguntar', 'perguntar') ?>>Perguntar ao finalizar</option>
                            <option value="sempre" <?= selected($config['receipt_mode'] ?? 'perguntar', 'sempre') ?>>Sempre emitir</option>
                            <option value="nunca" <?= selected($config['receipt_mode'] ?? 'perguntar', 'nunca') ?>>Nunca emitir automaticamente</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="receipt_template">Modelo</label>
                        <select id="receipt_template" name="receipt_template">
                            <option value="detalhado" <?= selected($config['receipt_template'] ?? 'detalhado', 'detalhado') ?>>Detalhado</option>
                            <option value="simples" <?= selected($config['receipt_template'] ?? 'detalhado', 'simples') ?>>Simples</option>
                        </select>
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar comprovantes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'vencimentos', $currentNivel)): ?>
<div class="settings-modal" id="modalVencimentos" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalVencimentosTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalVencimentosTitle">Vencimentos</h3>
                <p>Regras para alertas de produtos vencendo e contas pendentes.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_vencimento">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="expiration_alert_days">Dias para alerta de vencimento</label>
                        <input type="number" id="expiration_alert_days" name="expiration_alert_days" min="0" max="365" value="<?= h($config['expiration_alert_days'] ?? 7) ?>">
                    </div>

                    <div class="settings-field">
                        <label for="debt_due_days">Dias para vencimento de dívida</label>
                        <input type="number" id="debt_due_days" name="debt_due_days" min="0" max="365" value="<?= h($config['debt_due_days'] ?? 30) ?>">
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar vencimentos</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'estoque', $currentNivel)): ?>
<div class="settings-modal" id="modalEstoque" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalEstoqueTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalEstoqueTitle">Produtos e estoque</h3>
                <p>Regras de bloqueio, alerta e estoque mínimo.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_estoque">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="default_min_stock">Estoque mínimo padrão</label>
                        <input type="number" id="default_min_stock" name="default_min_stock" min="0" max="999999" value="<?= h($config['default_min_stock'] ?? 0) ?>">
                    </div>

                    <div class="settings-field">
                        <label>Regras ativas</label>
                        <div class="settings-switches">
                            <label class="settings-check">
                                <input type="checkbox" name="block_expired_products" <?= checked($config['block_expired_products'] ?? 1) ?>>
                                Bloquear produto vencido
                            </label>

                            <label class="settings-check">
                                <input type="checkbox" name="block_negative_stock" <?= checked($config['block_negative_stock'] ?? 1) ?>>
                                Bloquear estoque negativo
                            </label>

                            <label class="settings-check">
                                <input type="checkbox" name="low_stock_alert" <?= checked($config['low_stock_alert'] ?? 1) ?>>
                                Alertar estoque baixo
                            </label>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar estoque</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'pagamentos', $currentNivel)): ?>
<div class="settings-modal" id="modalPagamentos" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalPagamentosTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalPagamentosTitle">Formas de pagamento</h3>
                <p>Controle quais formas de pagamento ficam disponíveis nas vendas.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_pagamento">

                <div class="settings-switches">
                    <label class="settings-check"><input type="checkbox" name="payment_pix" <?= checked($config['payment_pix'] ?? 1) ?>> Pix</label>
                    <label class="settings-check"><input type="checkbox" name="payment_cash" <?= checked($config['payment_cash'] ?? 1) ?>> Dinheiro</label>
                    <label class="settings-check"><input type="checkbox" name="payment_credit" <?= checked($config['payment_credit'] ?? 1) ?>> Cartão de crédito</label>
                    <label class="settings-check"><input type="checkbox" name="payment_debit" <?= checked($config['payment_debit'] ?? 1) ?>> Cartão de débito</label>
                    <label class="settings-check"><input type="checkbox" name="payment_account" <?= checked($config['payment_account'] ?? 1) ?>> Conta/fiado</label>
                    <label class="settings-check"><input type="checkbox" name="payment_mixed" <?= checked($config['payment_mixed'] ?? 1) ?>> Pagamento misto</label>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar pagamentos</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'caixa', $currentNivel)): ?>
<div class="settings-modal" id="modalCaixa" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalCaixaTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalCaixaTitle">Vendas e caixa</h3>
                <p>Regras comerciais aplicadas no fechamento das vendas.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_caixa">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="discount_limit_percent">Limite de desconto (%)</label>
                        <input type="number" step="0.01" min="0" max="100" id="discount_limit_percent" name="discount_limit_percent" value="<?= h($config['discount_limit_percent'] ?? 0) ?>">
                    </div>

                    <div class="settings-field">
                        <label>Regras</label>
                        <div class="settings-switches">
                            <label class="settings-check"><input type="checkbox" name="allow_discount" <?= checked($config['allow_discount'] ?? 1) ?>> Permitir desconto</label>
                            <label class="settings-check"><input type="checkbox" name="require_customer_for_account" <?= checked($config['require_customer_for_account'] ?? 1) ?>> Exigir cliente no fiado</label>
                            <label class="settings-check"><input type="checkbox" name="require_cancellation_reason" <?= checked($config['require_cancellation_reason'] ?? 1) ?>> Exigir motivo no cancelamento</label>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar vendas e caixa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'seguranca', $currentNivel)): ?>
<div class="settings-modal" id="modalSeguranca" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalSegurancaTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalSegurancaTitle">Segurança e notificações</h3>
                <p>Auditoria, confirmação de exclusões, PIN de operador e avisos internos.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_seguranca">

                <div class="settings-switches">
                    <label class="settings-check"><input type="checkbox" name="audit_log_enabled" <?= checked($config['audit_log_enabled'] ?? 1) ?>> Auditoria ativa</label>
                    <label class="settings-check"><input type="checkbox" name="confirm_deletes" <?= checked($config['confirm_deletes'] ?? 1) ?>> Confirmar exclusões</label>
                    <label class="settings-check"><input type="checkbox" name="operator_pin_enabled" <?= checked($config['operator_pin_enabled'] ?? 0) ?>> Exigir PIN do operador</label>
                    <label class="settings-check"><input type="checkbox" name="notifications_enabled" <?= checked($config['notifications_enabled'] ?? 1) ?>> Notificações ativas</label>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar segurança</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'usuarios', $currentNivel)): ?>
<div class="settings-modal" id="modalCreateUser" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalCreateUserTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalCreateUserTitle">Cadastrar usuário</h3>
                <p>Crie um novo acesso vinculado à empresa atual.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="criar_usuario">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="usuario_nome">Nome</label>
                        <input type="text" id="usuario_nome" name="nome" maxlength="140" required>
                    </div>

                    <div class="settings-field">
                        <label for="usuario_email">E-mail</label>
                        <input type="email" id="usuario_email" name="email" maxlength="180" required>
                    </div>

                    <div class="settings-field">
                        <label for="usuario_telefone">Telefone</label>
                        <input type="text" id="usuario_telefone" name="telefone" maxlength="30">
                    </div>

                    <div class="settings-field">
                        <label for="usuario_senha">Senha</label>
                        <input type="password" id="usuario_senha" name="senha" minlength="6" maxlength="72" required>
                    </div>

                    <div class="settings-field">
                        <label for="usuario_nivel">Nível</label>
                        <select id="usuario_nivel" name="nivel" required>
                            <option value="operador">Operador</option>
                            <option value="gerente">Gerente</option>
                            <option value="estoquista">Estoquista</option>
                            <option value="leitor">Leitor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="usuario_ativo">Status</label>
                        <select id="usuario_ativo" name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Criar usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canAccessConfig($CONFIG_PERMISSIONS, 'usuarios', $currentNivel)): ?>
<div class="settings-modal" id="modalEditUser" aria-hidden="true">
    <div class="settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalEditUserTitle">
        <div class="settings-modal-header">
            <div>
                <h3 id="modalEditUserTitle">Editar usuário</h3>
                <p>Atualize dados, nível de acesso, status ou senha do usuário.</p>
            </div>
            <button class="settings-modal-close" type="button" data-close-modal aria-label="Fechar">×</button>
        </div>

        <div class="settings-modal-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="editar_usuario">
                <input type="hidden" id="edit_usuario_id" name="usuario_id" value="">

                <div class="settings-form-grid">
                    <div class="settings-field">
                        <label for="edit_nome">Nome</label>
                        <input type="text" id="edit_nome" name="nome" maxlength="140" required>
                    </div>

                    <div class="settings-field">
                        <label for="edit_email">E-mail</label>
                        <input type="email" id="edit_email" name="email" maxlength="180" required>
                    </div>

                    <div class="settings-field">
                        <label for="edit_telefone">Telefone</label>
                        <input type="text" id="edit_telefone" name="telefone" maxlength="30">
                    </div>

                    <div class="settings-field">
                        <label for="edit_nivel">Nível</label>
                        <select id="edit_nivel" name="nivel" required>
                            <option value="admin">Admin</option>
                            <option value="gerente">Gerente</option>
                            <option value="operador">Operador</option>
                            <option value="estoquista">Estoquista</option>
                            <option value="leitor">Leitor</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="edit_ativo">Status</label>
                        <select id="edit_ativo" name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>

                    <div class="settings-field">
                        <label for="edit_senha">Nova senha</label>
                        <input type="password" id="edit_senha" name="senha" minlength="6" maxlength="72" placeholder="Deixe vazio para manter">
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn ghost" type="button" data-close-modal>Cancelar</button>
                    <button class="settings-btn" type="submit">Salvar usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    (() => {
        const body = document.body;

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            document.querySelectorAll('.settings-modal.is-open').forEach((opened) => {
                if (opened.id !== id) {
                    opened.classList.remove('is-open');
                    opened.setAttribute('aria-hidden', 'true');
                }
            });

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            body.classList.add('modal-open');

            const firstInput = modal.querySelector('input:not([type="hidden"]), select, button');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 80);
            }
        }

        function closeModal(modal) {
            if (!modal) return;

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');

            if (!document.querySelector('.settings-modal.is-open')) {
                body.classList.remove('modal-open');
            }
        }

        document.querySelectorAll('[data-open-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                openModal(button.getAttribute('data-open-modal'));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.settings-modal'));
            });
        });

        document.querySelectorAll('.settings-modal').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.settings-modal.is-open').forEach(closeModal);
            }
        });

        const companyLogoInput = document.getElementById('company_logo');
        const companyLogoPreview = document.getElementById('companyLogoPreview');
        const companyLogoStatus = document.getElementById('companyLogoStatus');
        const removeCompanyLogo = document.getElementById('remove_company_logo');
        let companyLogoObjectUrl = '';

        function clearCompanyLogoObjectUrl() {
            if (companyLogoObjectUrl) {
                URL.revokeObjectURL(companyLogoObjectUrl);
                companyLogoObjectUrl = '';
            }
        }

        function setCompanyLogoPreview(source) {
            const preview = document.getElementById('companyLogoPreview');
            if (!preview) return;

            if (source) {
                if (preview.tagName.toLowerCase() === 'img') {
                    preview.src = source;
                    return;
                }

                const image = document.createElement('img');
                image.id = 'companyLogoPreview';
                image.alt = 'Prévia da logo da empresa';
                image.dataset.currentLogo = preview.dataset.currentLogo || '';
                image.src = source;
                preview.replaceWith(image);
                return;
            }

            if (preview.tagName.toLowerCase() === 'img' && preview.dataset.currentLogo) {
                preview.src = preview.dataset.currentLogo;
            }
        }

        function setCompanyLogoStatus(message, type = '') {
            if (!companyLogoStatus) return;

            companyLogoStatus.textContent = message;
            companyLogoStatus.classList.remove('error', 'success');

            if (type) {
                companyLogoStatus.classList.add(type);
            }
        }

        if (companyLogoInput && companyLogoPreview) {
            companyLogoInput.addEventListener('change', () => {
                clearCompanyLogoObjectUrl();
                setCompanyLogoStatus('');

                const file = companyLogoInput.files && companyLogoInput.files[0]
                    ? companyLogoInput.files[0]
                    : null;

                if (!file) {
                    setCompanyLogoPreview(companyLogoPreview.dataset.currentLogo || '');
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!allowedTypes.includes(file.type)) {
                    companyLogoInput.value = '';
                    setCompanyLogoPreview(companyLogoPreview.dataset.currentLogo || '');
                    setCompanyLogoStatus('Formato inválido. Use JPG, PNG ou WEBP.', 'error');
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    companyLogoInput.value = '';
                    setCompanyLogoPreview(companyLogoPreview.dataset.currentLogo || '');
                    setCompanyLogoStatus('A imagem deve possuir no máximo 2 MB.', 'error');
                    return;
                }

                if (removeCompanyLogo) {
                    removeCompanyLogo.checked = false;
                }

                companyLogoObjectUrl = URL.createObjectURL(file);
                setCompanyLogoPreview(companyLogoObjectUrl);
                setCompanyLogoStatus('Nova logo selecionada. Clique em Salvar empresa para confirmar.', 'success');
            });
        }

        if (removeCompanyLogo && companyLogoPreview) {
            removeCompanyLogo.addEventListener('change', () => {
                if (!removeCompanyLogo.checked) {
                    setCompanyLogoPreview(companyLogoPreview.dataset.currentLogo || '');
                    setCompanyLogoStatus('');
                    return;
                }

                clearCompanyLogoObjectUrl();

                if (companyLogoInput) {
                    companyLogoInput.value = '';
                }

                setCompanyLogoPreview('');
                setCompanyLogoStatus('A logo atual será removida ao salvar.', 'error');
            });
        }

        window.addEventListener('beforeunload', clearCompanyLogoObjectUrl);

        document.querySelectorAll('[data-open-edit-user]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('edit_usuario_id').value = button.dataset.id || '';
                document.getElementById('edit_nome').value = button.dataset.nome || '';
                document.getElementById('edit_email').value = button.dataset.email || '';
                document.getElementById('edit_telefone').value = button.dataset.telefone || '';
                document.getElementById('edit_nivel').value = button.dataset.nivel || 'operador';
                document.getElementById('edit_ativo').value = button.dataset.ativo || '1';
                document.getElementById('edit_senha').value = '';

                openModal('modalEditUser');
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
