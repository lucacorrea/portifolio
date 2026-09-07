<?php

declare(strict_types=1);

if (!isset($accessProfile) || !is_array($accessProfile)) {
    return;
}

$accessModules = is_array($accessProfile['modules'] ?? null) ? $accessProfile['modules'] : [];
$individualAccessLocked = !empty($isSelf) || !empty($accessProfile['protected']);
?>
<section class="ga-admin-section">
    <div class="ga-admin-section-heading">
        <div>
            <span class="card-kicker">Exceções individuais</span>
            <h3>Módulos e permissões desta pessoa</h3>
            <p class="mb-0 text-secondary">O nível e o setor continuam sendo o padrão. Configure somente as diferenças necessárias para este usuário.</p>
        </div>
        <i class="bi bi-person-lock"></i>
    </div>

    <?php if (!empty($accessProfile['protected'])): ?>
        <div class="ga-governance-note mb-3">
            <i class="bi bi-shield-lock"></i>
            <div>
                <strong>Conta administrativa global</strong>
                <span>Administrador e Suporte não recebem exceções individuais nesta tela para evitar perda acidental do acesso de governança.</span>
            </div>
        </div>
    <?php elseif (!empty($isSelf)): ?>
        <div class="ga-governance-note mb-3">
            <i class="bi bi-lock"></i>
            <div>
                <strong>Alteração da própria conta bloqueada</strong>
                <span>Outro administrador autorizado deve configurar suas exceções individuais.</span>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-light border d-flex gap-2 align-items-start" role="note">
            <i class="bi bi-info-circle mt-1"></i>
            <div>
                <strong>Como funciona</strong>
                <div class="small text-secondary">Herdar mantém a regra normal. Liberar cria uma exceção positiva apenas para esta pessoa. Bloquear cria uma exceção negativa apenas para esta pessoa.</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="vstack gap-3">
        <?php foreach ($accessModules as $module): ?>
            <?php
            $moduleKey = (string) ($module['key'] ?? '');
            $effective = !empty($module['effective_access']);
            $moduleState = (string) ($module['module_override_state'] ?? 'inherit');
            $permissions = is_array($module['permissions'] ?? null) ? $module['permissions'] : [];
            ?>
            <details class="border rounded-3 bg-white" <?= $effective ? '' : 'open' ?>>
                <summary class="p-3 d-flex flex-wrap align-items-center gap-2" style="cursor:pointer;list-style:none;">
                    <span class="fw-semibold flex-grow-1">
                        <i class="bi bi-grid me-1"></i>
                        <?= sigas_frontend_escape((string) ($module['label'] ?? $moduleKey)) ?>
                    </span>
                    <span class="badge <?= $effective ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= $effective ? 'Acesso efetivo' : 'Sem acesso efetivo' ?>
                    </span>
                    <span class="badge text-bg-light border text-dark">
                        Setor: <?= !empty($module['base_module_allowed']) ? 'liberado' : 'bloqueado' ?>
                    </span>
                </summary>

                <div class="border-top p-3">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-lg-7">
                            <label class="form-label fw-semibold" for="moduleOverride-<?= sigas_frontend_escape($moduleKey) ?>">Acesso ao módulo</label>
                            <div class="form-text mt-0">Esta decisão é aplicada além da permissão <code>*.visualizar</code>.</div>
                        </div>
                        <div class="col-12 col-lg-5">
                            <select
                                class="form-select"
                                id="moduleOverride-<?= sigas_frontend_escape($moduleKey) ?>"
                                name="module_override[<?= sigas_frontend_escape($moduleKey) ?>]"
                                <?= $individualAccessLocked ? 'disabled' : '' ?>
                            >
                                <option value="inherit" <?= $moduleState === 'inherit' ? 'selected' : '' ?>>Herdar do setor</option>
                                <option value="allow" <?= $moduleState === 'allow' ? 'selected' : '' ?>>Liberar para esta pessoa</option>
                                <option value="deny" <?= $moduleState === 'deny' ? 'selected' : '' ?>>Bloquear para esta pessoa</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($permissions !== []): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Ação</th>
                                        <th>Padrão do nível</th>
                                        <th>Exceção individual</th>
                                        <th>Resultado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permissions as $permission): ?>
                                        <?php
                                        $slug = (string) ($permission['slug'] ?? '');
                                        $state = (string) ($permission['override_state'] ?? 'inherit');
                                        $baseAllowed = !empty($permission['base_allowed']);
                                        $effectiveAllowed = !empty($permission['effective_allowed']);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= sigas_frontend_escape((string) ($permission['label'] ?? $slug)) ?></strong>
                                                <div class="small text-secondary"><code><?= sigas_frontend_escape($slug) ?></code></div>
                                                <?php if (trim((string) ($permission['description'] ?? '')) !== ''): ?>
                                                    <div class="small text-secondary mt-1"><?= sigas_frontend_escape((string) $permission['description']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $baseAllowed ? 'text-bg-success' : 'text-bg-light border text-dark' ?>">
                                                    <?= $baseAllowed ? 'Liberado' : 'Não possui' ?>
                                                </span>
                                            </td>
                                            <td style="min-width:220px;">
                                                <select
                                                    class="form-select form-select-sm"
                                                    name="permission_override[<?= sigas_frontend_escape($slug) ?>]"
                                                    <?= $individualAccessLocked ? 'disabled' : '' ?>
                                                >
                                                    <option value="inherit" <?= $state === 'inherit' ? 'selected' : '' ?>>Herdar do nível</option>
                                                    <option value="allow" <?= $state === 'allow' ? 'selected' : '' ?>>Liberar</option>
                                                    <option value="deny" <?= $state === 'deny' ? 'selected' : '' ?>>Bloquear</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="badge <?= $effectiveAllowed ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                    <?= $effectiveAllowed ? 'Permitido' : 'Negado' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <?php if (!$individualAccessLocked && $accessModules !== []): ?>
        <div class="d-flex justify-content-end mt-3">
            <button
                class="btn btn-primary"
                type="submit"
                name="acao"
                value="save_overrides"
                data-confirm-action="Salvar as exceções individuais e encerrar as sessões ativas desta pessoa?"
            >
                <i class="bi bi-shield-check"></i>
                Salvar exceções individuais
            </button>
        </div>
    <?php endif; ?>
</section>
