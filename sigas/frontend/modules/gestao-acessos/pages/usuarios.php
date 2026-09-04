<?php

declare(strict_types=1);

use App\Core\Csrf;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceUsersService $governanceUsers */
$governanceUsers = require dirname(__DIR__) . '/users-bootstrap.php';
$data = $governanceUsers->page();

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-users.js';

$selectedUserId = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$selectedUser = null;

if (is_int($selectedUserId) && $selectedUserId > 0) {
    foreach ($data['rows'] as $row) {
        if ((int) ($row['__user_id'] ?? 0) === $selectedUserId) {
            $selectedUser = $row;
            break;
        }
    }
}

if (is_array($selectedUser)) {
    $selectedStatus = (string) ($selectedUser['__status'] ?? '');
    $currentSectorId = (int) ($selectedUser['__sector_id'] ?? 0);
    $requestedSectorId = (int) ($selectedUser['__requested_sector_id'] ?? 0);
    $currentLevelId = (int) ($selectedUser['__level_id'] ?? 0);
    $activeSessions = (int) ($selectedUser['__active_sessions'] ?? 0);
    $statusClass = match ($selectedStatus) {
        'ativo' => 'success',
        'pendente' => 'warning',
        'bloqueado' => 'danger',
        'inativo', 'rejeitado' => 'secondary',
        default => 'secondary',
    };

    ob_start();
    ?>
    <div
        class="modal fade ga-user-admin-modal"
        id="governanceUserAdminModal"
        tabindex="-1"
        aria-labelledby="governanceUserAdminTitle"
        aria-hidden="true"
        data-auto-open="1"
    >
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="min-w-0">
                        <div class="eyebrow"><i class="bi bi-shield-lock"></i> Governança de usuário</div>
                        <h2 class="modal-title fs-5 text-truncate" id="governanceUserAdminTitle">
                            <?= sigas_frontend_escape((string) ($selectedUser['usuario'] ?? 'Usuário')) ?>
                        </h2>
                        <p class="ga-user-admin-subtitle mb-0">
                            <?= sigas_frontend_escape((string) ($selectedUser['CPF completo'] ?? '')) ?> ·
                            <?= sigas_frontend_escape((string) ($selectedUser['E-mail'] ?? '')) ?>
                        </p>
                    </div>
                    <span class="badge text-bg-<?= sigas_frontend_escape($statusClass) ?> ms-auto me-2">
                        <?= sigas_frontend_escape((string) ($selectedUser['situacao'] ?? 'Não definido')) ?>
                    </span>
                    <a class="btn-close" href="governanca-acessos/usuarios.php" aria-label="Fechar"></a>
                </div>

                <form class="modal-body" data-governance-user-form novalidate>
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-user-admin')) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">

                    <div class="alert d-none ga-user-admin-alert" role="alert" data-governance-user-alert></div>

                    <section class="ga-user-summary-grid" aria-label="Resumo da conta">
                        <article>
                            <small>Cargo</small>
                            <strong><?= sigas_frontend_escape((string) ($selectedUser['cargo'] ?? 'Não informado')) ?></strong>
                        </article>
                        <article>
                            <small>Setor atual</small>
                            <strong><?= sigas_frontend_escape((string) ($selectedUser['setor'] ?? 'Sem setor')) ?></strong>
                        </article>
                        <article>
                            <small>Nível atual</small>
                            <strong><?= sigas_frontend_escape((string) ($selectedUser['nivel'] ?? 'Sem nível')) ?></strong>
                        </article>
                        <article>
                            <small>Sessões ativas</small>
                            <strong><?= $activeSessions ?></strong>
                        </article>
                    </section>

                    <?php if ($selectedStatus === 'pendente'): ?>
                        <div class="ga-governance-note">
                            <i class="bi bi-info-circle"></i>
                            <div>
                                <strong>Solicitação aguardando aprovação</strong>
                                <span>Defina setor e nível antes de liberar o acesso operacional.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="ga-admin-section">
                        <div class="ga-admin-section-heading">
                            <div>
                                <span class="card-kicker">Acesso operacional</span>
                                <h3>Setor e nível de usuário</h3>
                            </div>
                            <i class="bi bi-person-gear"></i>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label class="form-label" for="governanceSector">Setor</label>
                                <select class="form-select" id="governanceSector" name="setor_id" required>
                                    <option value="">Selecione o setor</option>
                                    <?php foreach ($data['sectors'] as $sector): ?>
                                        <?php $sectorId = (int) ($sector['id'] ?? 0); ?>
                                        <option
                                            value="<?= $sectorId ?>"
                                            <?= $sectorId === ($currentSectorId > 0 ? $currentSectorId : $requestedSectorId) ? 'selected' : '' ?>
                                        ><?= sigas_frontend_escape((string) ($sector['nome'] ?? 'Setor')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-lg-6">
                                <label class="form-label" for="governanceLevel">Nível de acesso</label>
                                <select class="form-select" id="governanceLevel" name="nivel_id" required>
                                    <option value="">Selecione o nível</option>
                                    <?php foreach ($data['levels'] as $level): ?>
                                        <?php $levelId = (int) ($level['id'] ?? 0); ?>
                                        <option value="<?= $levelId ?>" <?= $levelId === $currentLevelId ? 'selected' : '' ?>>
                                            <?= sigas_frontend_escape((string) ($level['nome'] ?? 'Nível')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="governanceReason">Justificativa da ação</label>
                                <textarea
                                    class="form-control"
                                    id="governanceReason"
                                    name="motivo"
                                    rows="3"
                                    minlength="5"
                                    maxlength="500"
                                    required
                                    placeholder="Informe o motivo administrativo. A justificativa será registrada na auditoria."
                                ></textarea>
                                <div class="form-text">Obrigatória para qualquer alteração de acesso, status ou sessão.</div>
                            </div>
                        </div>
                    </section>

                    <section class="ga-admin-section ga-admin-section--actions">
                        <div class="ga-admin-section-heading">
                            <div>
                                <span class="card-kicker">Ações administrativas</span>
                                <h3>Controle da conta</h3>
                            </div>
                            <i class="bi bi-shield-check"></i>
                        </div>

                        <div class="ga-admin-actions">
                            <?php if ($selectedStatus === 'pendente'): ?>
                                <button class="btn btn-primary" type="submit" name="acao" value="approve">
                                    <i class="bi bi-person-check"></i> Aprovar e liberar acesso
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary" type="submit" name="acao" value="change_access">
                                    <i class="bi bi-arrow-repeat"></i> Salvar setor e nível
                                </button>
                            <?php endif; ?>

                            <?php if ($selectedStatus === 'ativo'): ?>
                                <button class="btn btn-outline-danger" type="submit" name="acao" value="block" data-confirm-action="Bloquear esta conta e encerrar suas sessões ativas?">
                                    <i class="bi bi-person-lock"></i> Bloquear usuário
                                </button>
                            <?php elseif ($selectedStatus === 'bloqueado'): ?>
                                <button class="btn btn-outline-success" type="submit" name="acao" value="unblock" data-confirm-action="Desbloquear esta conta e permitir novo acesso ao SIGAS?">
                                    <i class="bi bi-person-check"></i> Desbloquear usuário
                                </button>
                            <?php endif; ?>

                            <button
                                class="btn btn-outline-secondary"
                                type="submit"
                                name="acao"
                                value="revoke_sessions"
                                data-confirm-action="Encerrar todas as sessões ativas deste usuário?"
                                <?= $activeSessions <= 0 ? 'disabled' : '' ?>
                            >
                                <i class="bi bi-door-closed"></i>
                                Encerrar sessões<?= $activeSessions > 0 ? ' (' . $activeSessions . ')' : '' ?>
                            </button>
                        </div>
                    </section>

                    <section class="ga-admin-section ga-user-audit-summary">
                        <div class="ga-admin-section-heading">
                            <div>
                                <span class="card-kicker">Rastreabilidade</span>
                                <h3>Informações administrativas</h3>
                            </div>
                            <a class="btn btn-light btn-sm" href="governanca-acessos/auditoria.php?usuario=<?= (int) $selectedUserId ?>">
                                <i class="bi bi-journal-text"></i> Abrir auditoria
                            </a>
                        </div>
                        <dl>
                            <div><dt>Último acesso</dt><dd><?= sigas_frontend_escape((string) ($selectedUser['ultimo_acesso'] ?? 'Nunca')) ?></dd></div>
                            <div><dt>Último IP</dt><dd><?= sigas_frontend_escape((string) ($selectedUser['Último IP'] ?? 'Não registrado')) ?></dd></div>
                            <div><dt>Aprovado por</dt><dd><?= sigas_frontend_escape((string) ($selectedUser['Aprovado por'] ?? 'Não informado')) ?></dd></div>
                            <div><dt>Criado em</dt><dd><?= sigas_frontend_escape((string) ($selectedUser['Criado em'] ?? 'Não informado')) ?></dd></div>
                        </dl>
                    </section>
                </form>

                <div class="modal-footer">
                    <a class="btn btn-light" href="governanca-acessos/usuarios.php">Fechar</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    $pageCustomContent = (string) ob_get_clean();
}

return sigas_frontend_page([
    'title' => 'Usuários',
    'description' => 'Contas reais do SIGAS com dados administrativos, setor, nível, situação e histórico básico de acesso.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Matriz de acesso',
            'icon' => 'grid-3x3-gap',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => $data['stats'],
    'filters' => $data['filters'],
    'search_placeholder' => 'Pesquisar por nome, CPF, e-mail, cargo, setor ou nível',
    'blocks' => [
        [
            'type' => 'table',
            'kicker' => 'Governança',
            'title' => 'Usuários do SIGAS',
            'description' => 'Clique em uma linha para consultar os dados ou abrir o gerenciamento administrativo da conta.',
            'columns' => [
                ['key' => 'usuario', 'label' => 'Usuário'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'cargo', 'label' => 'Cargo'],
                ['key' => 'setor', 'label' => 'Setor'],
                ['key' => 'nivel', 'label' => 'Nível'],
                ['key' => 'ultimo_acesso', 'label' => 'Último acesso'],
                ['key' => 'situacao', 'label' => 'Situação'],
            ],
            'rows' => $data['rows'],
            'primary' => 'usuario',
        ],
    ],
    'demo' => false,
    'show_states' => false,
]);
