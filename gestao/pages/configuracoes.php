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

$pageId = 'configuracoes';
$pageTitle = 'Configurações';
$activeMenu = 'mais';

$settingsService = new SettingsService(new SettingsRepository());
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

function requireAdminForConfig(string $nivel): void
{
    if ($nivel !== 'admin') {
        throw new RuntimeException('Apenas administradores podem alterar configurações.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireAdminForConfig($currentNivel);

        $token = (string)($_POST['csrf_token'] ?? '');

        if (!hash_equals((string)$_SESSION['csrf_configuracoes'], $token)) {
            throw new RuntimeException('Token de segurança inválido. Recarregue a página e tente novamente.');
        }

        $acao = (string)($_POST['acao'] ?? '');

        match ($acao) {
            'salvar_empresa' => $settingsService->saveEmpresa($empresaId, $_POST),
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

$csrfToken = (string)$_SESSION['csrf_configuracoes'];

require_once __DIR__ . '/layout/header.php';
?>
<style>
    :root {
        --settings-bg: #f8fafc;
        --settings-card: #ffffff;
        --settings-border: rgba(15, 23, 42, .08);
        --settings-text: #111827;
        --settings-muted: #64748b;
        --settings-soft: #f1f5f9;
        --settings-primary: #111827;
        --settings-danger: #dc2626;
        --settings-success: #16a34a;
    }

    .content-pad {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding-left: clamp(12px, 3vw, 24px);
        padding-right: clamp(12px, 3vw, 24px);
        padding-bottom: 90px;
        box-sizing: border-box;
    }

    .plain-header {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding-left: clamp(12px, 3vw, 24px);
        padding-right: clamp(12px, 3vw, 24px);
        box-sizing: border-box;
    }

    .page-title-row {
        gap: 14px;
        align-items: center;
    }

    .settings-wrapper {
        display: grid;
        gap: 18px;
        width: 100%;
        min-width: 0;
    }

    .settings-card {
        background: var(--settings-card);
        border: 1px solid var(--settings-border);
        border-radius: 20px;
        padding: clamp(14px, 2.5vw, 20px);
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow: hidden;
    }

    .settings-card h2 {
        font-size: clamp(1rem, 2.2vw, 1.08rem);
        margin: 0 0 4px;
        color: var(--settings-text);
        line-height: 1.25;
    }

    .settings-card p {
        margin: 0 0 16px;
        color: var(--settings-muted);
        font-size: .9rem;
        line-height: 1.45;
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
        font-weight: 700;
        color: #334155;
        line-height: 1.3;
    }

    .settings-field input,
    .settings-field select {
        width: 100%;
        max-width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 12px;
        min-height: 44px;
        padding: 10px 12px;
        outline: none;
        background: #fff;
        color: var(--settings-text);
        box-sizing: border-box;
        font-size: 16px;
    }

    .settings-field input:focus,
    .settings-field select:focus {
        border-color: var(--settings-primary);
        box-shadow: 0 0 0 3px rgba(17, 24, 39, .08);
    }

    .settings-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
        flex-wrap: wrap;
    }

    .settings-btn {
        border: 0;
        border-radius: 12px;
        min-height: 42px;
        padding: 10px 16px;
        font-weight: 800;
        cursor: pointer;
        background: var(--settings-primary);
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
        color: var(--settings-text);
    }

    .settings-btn.danger {
        background: var(--settings-danger);
        color: #fff;
    }

    .settings-btn.success {
        background: var(--settings-success);
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
        border-radius: 14px;
        padding: 12px;
        display: flex;
        gap: 10px;
        align-items: center;
        color: #334155;
        font-weight: 700;
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

    .settings-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 16px;
    }

    .settings-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    .settings-table th,
    .settings-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: middle;
        font-size: .9rem;
    }

    .settings-table th {
        color: #475569;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
    }

    .settings-table td {
        color: #1f2937;
    }

    .settings-table td form {
        margin: 0;
    }

    .status-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .75rem;
        font-weight: 800;
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

    .config-alert {
        border-radius: 14px;
        padding: 13px 15px;
        margin-bottom: 16px;
        font-weight: 700;
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
        border-radius: 14px;
        font-size: .9rem;
        line-height: 1.45;
        margin-bottom: 16px;
    }

    @media (max-width: 1024px) {
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

        .page-title-row {
            align-items: flex-start;
        }

        .plain-header h1 {
            font-size: 1.45rem;
            line-height: 1.15;
        }

        .settings-wrapper {
            gap: 14px;
        }

        .settings-card {
            border-radius: 18px;
            padding: 14px;
        }

        .settings-form-grid,
        .settings-form-grid.three,
        .settings-switches {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .settings-actions {
            justify-content: stretch;
        }

        .settings-btn {
            width: 100%;
            min-height: 46px;
        }

        .settings-check {
            align-items: flex-start;
            padding: 12px;
        }

        .settings-check input {
            margin-top: 1px;
        }

        .settings-table-wrap {
            overflow: visible;
        }

        .settings-table {
            min-width: 0;
            display: block;
            width: 100%;
        }

        .settings-table thead {
            display: none;
        }

        .settings-table tbody,
        .settings-table tr,
        .settings-table td {
            display: block;
            width: 100%;
        }

        .settings-table tr {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 12px;
            padding: 10px;
            box-sizing: border-box;
        }

        .settings-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .settings-table td {
            border-bottom: 0;
            padding: 8px 4px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            text-align: right;
            font-size: .88rem;
            word-break: break-word;
        }

        .settings-table td::before {
            content: '';
            font-weight: 800;
            color: #475569;
            text-align: left;
            min-width: 105px;
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(1)::before {
            content: 'Usuário';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(2)::before {
            content: 'E-mail';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(3)::before {
            content: 'Telefone';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(4)::before {
            content: 'Nível';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(5)::before {
            content: 'Status';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(6)::before {
            content: 'Último login';
        }

        .settings-table tr:not(:has(td[colspan])) td:nth-child(7)::before {
            content: 'Ações';
        }

        .settings-table td[colspan] {
            display: block;
            text-align: left;
            padding: 4px;
        }

        .settings-table td[colspan]::before {
            content: none;
        }

        .settings-table td form[style*="display:inline"] {
            width: 100%;
            display: block !important;
        }

        .settings-table td .settings-btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .plain-header,
        .content-pad {
            padding-left: 10px;
            padding-right: 10px;
        }

        .settings-card {
            border-radius: 16px;
            padding: 12px;
        }

        .settings-card h2 {
            font-size: 1rem;
        }

        .settings-card p {
            font-size: .84rem;
        }

        .settings-field label {
            font-size: .8rem;
        }

        .settings-field input,
        .settings-field select {
            min-height: 46px;
            border-radius: 11px;
        }

        .settings-table tr {
            padding: 8px;
            border-radius: 14px;
        }

        .settings-table td {
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            gap: 4px;
        }

        .settings-table td::before {
            min-width: 0;
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
            Você está acessando como <strong><?= h($currentNivel) ?></strong>. Apenas administradores podem alterar configurações.
        </div>
    <?php endif; ?>

    <div class="settings-wrapper">

        <div class="settings-card">
            <h2>Dados da empresa</h2>
            <p>Informações principais usadas no sistema e nos comprovantes.</p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_empresa">

                <div class="settings-form-grid">
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
                    <button class="settings-btn" type="submit">Salvar empresa</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Usuários e permissões</h2>
            <p>Cadastro de operadores, gerentes, estoquistas, leitores e administradores.</p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="criar_usuario">

                <div class="settings-form-grid three">
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
                    <button class="settings-btn" type="submit">Criar usuário</button>
                </div>
            </form>

            <div class="settings-table-wrap" style="margin-top: 18px;">
                <table class="settings-table">
                    <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
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
                            <td colspan="7">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= h($usuario['nome']) ?></td>
                            <td><?= h($usuario['email']) ?></td>
                            <td><?= h($usuario['telefone'] ?? '-') ?></td>
                            <td><?= h($usuario['nivel']) ?></td>
                            <td>
                                <?php if ((int)$usuario['ativo'] === 1): ?>
                                    <span class="status-badge on">Ativo</span>
                                <?php else: ?>
                                    <span class="status-badge off">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($usuario['ultimo_login_em'] ?? '-') ?></td>
                            <td>
                                <?php if ((int)$usuario['ativo'] === 1): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="acao" value="inativar_usuario">
                                        <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                        <button class="settings-btn danger" type="submit" onclick="return confirm('Inativar este usuário?')">Inativar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="acao" value="ativar_usuario">
                                        <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                        <button class="settings-btn success" type="submit">Ativar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="7">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="acao" value="editar_usuario">
                                    <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">

                                    <div class="settings-form-grid three">
                                        <div class="settings-field">
                                            <label>Nome</label>
                                            <input type="text" name="nome" maxlength="140" required value="<?= h($usuario['nome']) ?>">
                                        </div>

                                        <div class="settings-field">
                                            <label>E-mail</label>
                                            <input type="email" name="email" maxlength="180" required value="<?= h($usuario['email']) ?>">
                                        </div>

                                        <div class="settings-field">
                                            <label>Telefone</label>
                                            <input type="text" name="telefone" maxlength="30" value="<?= h($usuario['telefone'] ?? '') ?>">
                                        </div>

                                        <div class="settings-field">
                                            <label>Nível</label>
                                            <select name="nivel">
                                                <option value="admin" <?= selected($usuario['nivel'], 'admin') ?>>Admin</option>
                                                <option value="gerente" <?= selected($usuario['nivel'], 'gerente') ?>>Gerente</option>
                                                <option value="operador" <?= selected($usuario['nivel'], 'operador') ?>>Operador</option>
                                                <option value="estoquista" <?= selected($usuario['nivel'], 'estoquista') ?>>Estoquista</option>
                                                <option value="leitor" <?= selected($usuario['nivel'], 'leitor') ?>>Leitor</option>
                                            </select>
                                        </div>

                                        <div class="settings-field">
                                            <label>Status</label>
                                            <select name="ativo">
                                                <option value="1" <?= selected($usuario['ativo'], 1) ?>>Ativo</option>
                                                <option value="0" <?= selected($usuario['ativo'], 0) ?>>Inativo</option>
                                            </select>
                                        </div>

                                        <div class="settings-field">
                                            <label>Nova senha</label>
                                            <input type="password" name="senha" minlength="6" maxlength="72" placeholder="Deixe vazio para manter">
                                        </div>
                                    </div>

                                    <div class="settings-actions">
                                        <button class="settings-btn secondary" type="submit">Salvar usuário</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="settings-card">
            <h2>Comprovantes</h2>
            <p>Define quando emitir comprovante e qual modelo usar.</p>

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
                    <button class="settings-btn" type="submit">Salvar comprovantes</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Vencimentos</h2>
            <p>Regras para alertas de produtos vencendo e contas pendentes.</p>

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
                    <button class="settings-btn" type="submit">Salvar vencimentos</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Produtos e estoque</h2>
            <p>Regras de bloqueio, alerta e estoque mínimo.</p>

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
                    <button class="settings-btn" type="submit">Salvar estoque</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Formas de pagamento</h2>
            <p>Controle quais formas de pagamento ficam disponíveis nas vendas.</p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_pagamento">

                <div class="settings-switches">
                    <label class="settings-check">
                        <input type="checkbox" name="payment_pix" <?= checked($config['payment_pix'] ?? 1) ?>>
                        Pix
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="payment_cash" <?= checked($config['payment_cash'] ?? 1) ?>>
                        Dinheiro
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="payment_credit" <?= checked($config['payment_credit'] ?? 1) ?>>
                        Cartão de crédito
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="payment_debit" <?= checked($config['payment_debit'] ?? 1) ?>>
                        Cartão de débito
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="payment_account" <?= checked($config['payment_account'] ?? 1) ?>>
                        Conta/fiado
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="payment_mixed" <?= checked($config['payment_mixed'] ?? 1) ?>>
                        Pagamento misto
                    </label>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn" type="submit">Salvar pagamentos</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Vendas e caixa</h2>
            <p>Regras comerciais aplicadas no fechamento das vendas.</p>

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
                            <label class="settings-check">
                                <input type="checkbox" name="allow_discount" <?= checked($config['allow_discount'] ?? 1) ?>>
                                Permitir desconto
                            </label>

                            <label class="settings-check">
                                <input type="checkbox" name="require_customer_for_account" <?= checked($config['require_customer_for_account'] ?? 1) ?>>
                                Exigir cliente no fiado
                            </label>

                            <label class="settings-check">
                                <input type="checkbox" name="require_cancellation_reason" <?= checked($config['require_cancellation_reason'] ?? 1) ?>>
                                Exigir motivo no cancelamento
                            </label>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn" type="submit">Salvar vendas e caixa</button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <h2>Segurança e notificações</h2>
            <p>Auditoria, confirmação de exclusões, PIN de operador e avisos internos.</p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar_seguranca">

                <div class="settings-switches">
                    <label class="settings-check">
                        <input type="checkbox" name="audit_log_enabled" <?= checked($config['audit_log_enabled'] ?? 1) ?>>
                        Auditoria ativa
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="confirm_deletes" <?= checked($config['confirm_deletes'] ?? 1) ?>>
                        Confirmar exclusões
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="operator_pin_enabled" <?= checked($config['operator_pin_enabled'] ?? 0) ?>>
                        Exigir PIN do operador
                    </label>

                    <label class="settings-check">
                        <input type="checkbox" name="notifications_enabled" <?= checked($config['notifications_enabled'] ?? 1) ?>>
                        Notificações ativas
                    </label>
                </div>

                <div class="settings-actions">
                    <button class="settings-btn" type="submit">Salvar segurança</button>
                </div>
            </form>
        </div>

        <a class="danger-btn section-gap" href="../logout.php">Sair do sistema</a>
    </div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>