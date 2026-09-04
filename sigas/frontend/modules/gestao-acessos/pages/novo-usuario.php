<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\CargoRepository;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernanceUsersService $governanceUsers */
$governanceUsers = require dirname(__DIR__) . '/users-bootstrap.php';
$options = $governanceUsers->page();
$sectors = is_array($options['sectors'] ?? null) ? $options['sectors'] : [];
$cargoRepository = new CargoRepository(Database::connection());
$cargoSchemaReady = $cargoRepository->schemaReady();
$cargos = $cargoSchemaReady ? $cargoRepository->activeOptions() : [];
$canCreate = $cargoSchemaReady && $cargos !== [];

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-user-create.js';

ob_start();
?>
<section class="content-card frontend-data-card">
    <div class="card-heading">
        <div>
            <div class="card-kicker">Governança de identidade</div>
            <h2>Criar conta pendente</h2>
            <p>Cadastre os dados iniciais. O usuário não terá acesso ao SIGAS até que setor e nível sejam aprovados.</p>
        </div>
        <span class="badge text-bg-warning">Pendente</span>
    </div>

    <form class="p-3 p-lg-4" data-governance-user-create novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-user-create')) ?>">

        <div class="alert d-none" role="alert" data-governance-user-create-alert></div>

        <div class="ga-governance-note mb-4">
            <i class="bi bi-shield-check"></i>
            <div>
                <strong>Conta sem acesso até aprovação</strong>
                <span>Este cadastro cria somente a identidade do usuário. O nível de acesso será definido posteriormente na tela de Governança.</span>
            </div>
        </div>

        <?php if (!$cargoSchemaReady): ?>
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                <i class="bi bi-database-exclamation mt-1"></i>
                <div>
                    <strong>Catálogo de cargos ainda não inicializado.</strong>
                    <div>Inicialize a gestão de cargos antes de cadastrar usuários. Não será permitido informar cargo manualmente.</div>
                </div>
            </div>
        <?php elseif ($cargos === []): ?>
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                <i class="bi bi-person-badge mt-1"></i>
                <div>
                    <strong>Cadastre pelo menos um cargo ativo primeiro.</strong>
                    <div><a class="alert-link" href="governanca-acessos/cargos.php?novo=1">Ir para Cargos e cadastrar agora</a>.</div>
                </div>
            </div>
        <?php endif; ?>

        <section class="ga-admin-section mb-3">
            <div class="ga-admin-section-heading">
                <div>
                    <span class="card-kicker">Identificação</span>
                    <h3>Dados do usuário</h3>
                </div>
                <i class="bi bi-person-vcard"></i>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <label class="form-label" for="newUserName">Nome completo *</label>
                    <input class="form-control" id="newUserName" name="nome" type="text" minlength="3" maxlength="160" required autocomplete="name">
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="newUserCpf">CPF *</label>
                    <input class="form-control" id="newUserCpf" name="cpf" type="text" inputmode="numeric" maxlength="14" required placeholder="000.000.000-00">
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="newUserEmail">E-mail *</label>
                    <input class="form-control" id="newUserEmail" name="email" type="email" maxlength="190" required autocomplete="email">
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="newUserPhone">Telefone</label>
                    <input class="form-control" id="newUserPhone" name="telefone" type="text" inputmode="tel" maxlength="16" placeholder="(97) 99999-9999">
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="newUserRegistration">Matrícula</label>
                    <input class="form-control" id="newUserRegistration" name="matricula" type="text" maxlength="60">
                </div>
                <div class="col-12 col-lg-6">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <label class="form-label" for="newUserJobTitle">Cargo / função *</label>
                        <a class="small fw-semibold text-decoration-none" href="governanca-acessos/cargos.php">Gerenciar cargos</a>
                    </div>
                    <select class="form-select" id="newUserJobTitle" name="cargo_id" required <?= !$canCreate ? 'disabled' : '' ?>>
                        <option value="">Selecione o cargo</option>
                        <?php foreach ($cargos as $cargo): ?>
                            <option value="<?= (int) ($cargo['id'] ?? 0) ?>">
                                <?= sigas_frontend_escape((string) ($cargo['nome'] ?? 'Cargo')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Somente cargos ativos cadastrados na Governança podem ser atribuídos.</div>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="newUserSector">Setor solicitado *</label>
                    <select class="form-select" id="newUserSector" name="setor_solicitado_id" required>
                        <option value="">Selecione o setor</option>
                        <?php foreach ($sectors as $sector): ?>
                            <option value="<?= (int) ($sector['id'] ?? 0) ?>">
                                <?= sigas_frontend_escape((string) ($sector['nome'] ?? 'Setor')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="ga-admin-section mb-3">
            <div class="ga-admin-section-heading">
                <div>
                    <span class="card-kicker">Credencial inicial</span>
                    <h3>Senha de acesso</h3>
                </div>
                <i class="bi bi-key"></i>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="newUserPassword">Senha inicial *</label>
                    <input class="form-control" id="newUserPassword" name="senha" type="password" minlength="8" maxlength="120" required autocomplete="new-password">
                    <div class="form-text">Mínimo de 8 caracteres com letra, número e símbolo.</div>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="newUserPasswordConfirmation">Confirmar senha *</label>
                    <input class="form-control" id="newUserPasswordConfirmation" name="senha_confirmacao" type="password" minlength="8" maxlength="120" required autocomplete="new-password">
                    <div class="form-text">A senha é armazenada somente como hash e não poderá ser consultada depois.</div>
                </div>
            </div>
        </section>

        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 pt-2">
            <a class="btn btn-light" href="governanca-acessos/usuarios.php">Cancelar</a>
            <button class="btn btn-primary" type="submit" <?= !$canCreate ? 'disabled' : '' ?>>
                <i class="bi bi-person-plus"></i> Criar usuário pendente
            </button>
        </div>
    </form>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Novo usuário',
    'description' => 'Crie uma conta pendente para posterior definição de nível e aprovação de acesso.',
    'actions' => [
        [
            'label' => 'Gerenciar cargos',
            'icon' => 'person-badge',
            'href' => 'governanca-acessos/cargos.php',
        ],
        [
            'label' => 'Voltar para usuários',
            'icon' => 'arrow-left',
            'href' => 'governanca-acessos/usuarios.php',
        ],
    ],
    'stats' => [],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
