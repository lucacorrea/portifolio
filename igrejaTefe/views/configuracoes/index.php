<?php

$igreja = is_array($igreja ?? null) ? $igreja : [];
$usuario = is_array($usuario ?? null) ? $usuario : [];
$value = static fn (array $data, string $key): string => (string) ($data[$key] ?? '');
?>

<section class="page-section module-page">
    <div class="module-hero settings-hero">
        <div>
            <p class="eyebrow">Configurações</p>
            <h1>Dados da conta</h1>
            <p>Revise os dados principais da igreja, usuário logado e preferências operacionais do sistema.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button secondary" href="<?= \App\Core\View::e(url('/dashboard')) ?>">
                <i data-lucide="layout-dashboard"></i>
                Dashboard
            </a>
        </div>
    </div>

    <div class="settings-grid">
        <article class="form-card">
            <div class="form-card-header">
                <div>
                    <span class="section-kicker">Igreja</span>
                    <h2>Cadastro institucional</h2>
                </div>
                <span class="badge badge-success"><?= \App\Core\View::e(ucfirst($value($igreja, 'status') ?: 'Ativa')) ?></span>
            </div>

            <div class="settings-readonly-grid">
                <label>
                    Nome da igreja
                    <input type="text" value="<?= \App\Core\View::e($value($igreja, 'nome')) ?>" readonly>
                </label>
                <label>
                    CNPJ
                    <input type="text" value="<?= \App\Core\View::e($value($igreja, 'cnpj')) ?>" placeholder="Não informado" readonly>
                </label>
                <label>
                    Email
                    <input type="email" value="<?= \App\Core\View::e($value($igreja, 'email')) ?>" placeholder="Não informado" readonly>
                </label>
                <label>
                    Telefone
                    <input type="text" value="<?= \App\Core\View::e($value($igreja, 'telefone')) ?>" placeholder="Não informado" readonly>
                </label>
            </div>
        </article>

        <article class="form-card">
            <div class="form-card-header">
                <div>
                    <span class="section-kicker">Usuário</span>
                    <h2>Perfil de acesso</h2>
                </div>
                <span class="badge badge-muted"><?= \App\Core\View::e(ucfirst($value($usuario, 'papel') ?: 'Sessão')) ?></span>
            </div>

            <div class="settings-readonly-grid">
                <label>
                    Nome
                    <input type="text" value="<?= \App\Core\View::e($value($usuario, 'nome')) ?>" readonly>
                </label>
                <label>
                    Email
                    <input type="email" value="<?= \App\Core\View::e($value($usuario, 'email')) ?>" readonly>
                </label>
            </div>
        </article>

        <article class="quick-actions-card settings-card-wide">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Operação</span>
                    <h2>Preferências do sistema</h2>
                </div>
            </div>
            <div class="settings-preference-list">
                <div>
                    <span><i data-lucide="shield-check"></i></span>
                    <strong>Autenticação protegida</strong>
                    <small>Sessão, CSRF e escopo por igreja ativos.</small>
                </div>
                <div>
                    <span><i data-lucide="database"></i></span>
                    <strong>Dados isolados por igreja</strong>
                    <small>Consultas principais filtradas pelo identificador da igreja logada.</small>
                </div>
                <div>
                    <span><i data-lucide="palette"></i></span>
                    <strong>Identidade visual</strong>
                    <small>Nome e marca da igreja centralizados no layout do sistema.</small>
                </div>
            </div>
        </article>
    </div>
</section>
