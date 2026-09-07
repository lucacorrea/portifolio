<?php

declare(strict_types=1);

use App\Config\AccessModuleCatalog;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ModuleAccessRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonJourneyRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ModuleAccessService;
use App\Services\PermissionService;
use App\Services\PersonJourneyService;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::connection();
$levels = new AccessLevelRepository($pdo);
$audit = new AuditService(new AuditLogRepository($pdo));
$auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
$user = $auth->requireUser();
$permissionRepository = new PermissionRepository($pdo);
$authorization = new AuthorizationService(new PermissionService($permissionRepository), $levels);
$moduleAccess = new ModuleAccessService(new ModuleAccessRepository($pdo), $authorization);
$journeyRepository = new PersonJourneyRepository($pdo);
$journey = new PersonJourneyService($journeyRepository);
$sectorRepository = new SectorRepository($pdo);
$catalog = AccessModuleCatalog::operational();
$isGlobal = $authorization->isAdministrator($user) || $authorization->isSupport($user);

/** @var array<string,bool> $accessibleModules */
$accessibleModules = [];
foreach ($catalog as $moduleKey => $_definition) {
    $accessibleModules[$moduleKey] = $isGlobal || $moduleAccess->canAccess($user, $moduleKey);
}

if (!$isGlobal && !in_array(true, $accessibleModules, true)) {
    http_response_code(403);
    exit('Acesso negado.');
}

function journey_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function journey_module_label(string $module, array $catalog, array $accessibleModules, bool $isGlobal): string
{
    if ($module === '') {
        return 'Não informado';
    }
    if (!$isGlobal && empty($accessibleModules[$module])) {
        return 'Módulo restrito';
    }
    return (string) ($catalog[$module]['label'] ?? str_replace(['-', '_'], ' ', $module));
}

function journey_sector_label(?string $sector, string $module, array $accessibleModules, bool $isGlobal): string
{
    if (!$isGlobal && $module !== '' && empty($accessibleModules[$module])) {
        return 'Setor restrito';
    }
    $sector = trim((string) ($sector ?? ''));
    return $sector !== '' ? $sector : 'Não informado';
}

function journey_status_label(string $status): string
{
    return match ($status) {
        'aberto' => 'Aberto',
        'encaminhado' => 'Encaminhado',
        'em_atendimento' => 'Em atendimento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Não definido',
    };
}

function journey_movement_label(string $type): string
{
    return match ($type) {
        'entrada' => 'Entrada',
        'encaminhamento' => 'Encaminhamento',
        'recebimento' => 'Recebimento',
        'conclusao' => 'Conclusão',
        default => $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : 'Movimentação',
    };
}

$cpf = Validator::onlyDigits((string) ($_GET['cpf'] ?? $_POST['cpf'] ?? ''));
$error = null;
$success = isset($_GET['ok']) ? trim((string) $_GET['ok']) : null;
$person = null;
$attendances = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!Csrf::validateAndRotate(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null, 'person-journey')) {
            throw new RuntimeException('A sessão do formulário expirou. Atualize a página e tente novamente.');
        }
        if (!Validator::cpf($cpf)) {
            throw new InvalidArgumentException('CPF inválido para movimentar o atendimento.');
        }

        $attendanceId = filter_var($_POST['atendimento_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($attendanceId === false) {
            throw new InvalidArgumentException('Atendimento inválido.');
        }
        $attendance = $journeyRepository->findAttendance((int) $attendanceId);
        if (!is_array($attendance)) {
            throw new InvalidArgumentException('Atendimento não localizado.');
        }

        $personCheck = $pdo->prepare('SELECT id FROM pessoas WHERE cpf = :cpf LIMIT 1');
        $personCheck->execute(['cpf' => $cpf]);
        $personIdForCpf = $personCheck->fetchColumn();
        if ($personIdForCpf === false || (int) $personIdForCpf !== (int) ($attendance['pessoa_id'] ?? 0)) {
            throw new RuntimeException('O atendimento informado não pertence à pessoa consultada.');
        }

        $currentModule = trim((string) ($attendance['modulo_atual'] ?? ''));
        $currentSectorId = $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'];
        if (!isset($catalog[$currentModule]) || !$moduleAccess->canAccess($user, $currentModule)) {
            throw new RuntimeException('Seu perfil não possui acesso ao módulo atual deste atendimento.');
        }
        if ($currentSectorId === null || !$authorization->canAccessOperationalSector($user, $currentSectorId)) {
            throw new RuntimeException('Somente o setor responsável atual pode movimentar este atendimento.');
        }

        $action = trim((string) ($_POST['acao'] ?? ''));
        $observation = trim((string) ($_POST['observacao'] ?? ''));

        if ($action === 'encaminhar') {
            $destinationSectorId = filter_var($_POST['setor_destino_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $destinationModule = trim((string) ($_POST['modulo_destino'] ?? ''));
            if ($destinationSectorId === false || !$sectorRepository->existsActive((int) $destinationSectorId)) {
                throw new InvalidArgumentException('Selecione um setor de destino válido.');
            }
            if (!isset($catalog[$destinationModule])) {
                throw new InvalidArgumentException('Selecione um módulo de destino válido.');
            }
            if ((int) $destinationSectorId === $currentSectorId && $destinationModule === $currentModule) {
                throw new InvalidArgumentException('O destino precisa ser diferente do setor/módulo atual.');
            }

            if ($permissionRepository->sectorHasModuleConfiguration((int) $destinationSectorId)
                && !$permissionRepository->sectorAllowsModule((int) $destinationSectorId, $destinationModule)) {
                throw new InvalidArgumentException('O setor de destino não está habilitado para o módulo selecionado.');
            }

            $journey->move(
                (int) $attendanceId,
                (int) $destinationSectorId,
                $destinationModule,
                $user->id,
                $observation,
            );
            $message = 'Atendimento encaminhado com sucesso.';
        } elseif ($action === 'receber') {
            $journey->receive((int) $attendanceId, $user->id, $observation);
            $message = 'Atendimento recebido pelo setor.';
        } elseif ($action === 'concluir') {
            if (mb_strlen($observation) < 3) {
                throw new InvalidArgumentException('Informe uma observação para concluir o atendimento.');
            }
            $journey->complete((int) $attendanceId, $user->id, $observation);
            $message = 'Atendimento concluído.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }

        header('Location: historico-pessoa.php?' . http_build_query([
            'cpf' => $cpf,
            'ok' => $message,
        ]));
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($cpf !== '') {
    if (!Validator::cpf($cpf)) {
        $error ??= 'Informe um CPF válido para consultar a trajetória.';
    } else {
        // A consulta transversal aplica minimização de dados: NIS e telefone
        // só são carregados para perfis globais. Usuários operacionais devem
        // consultar esses dados dentro do módulo que realmente necessita deles.
        $personColumns = $isGlobal
            ? 'id, nome, cpf, nis, telefone, status'
            : 'id, nome, cpf, NULL AS nis, NULL AS telefone, status';
        $stmt = $pdo->prepare(
            'SELECT ' . $personColumns . '
             FROM pessoas
             WHERE cpf = :cpf
             LIMIT 1'
        );
        $stmt->execute(['cpf' => $cpf]);
        $personRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $person = is_array($personRow) ? $personRow : null;

        if ($person === null) {
            $error ??= 'Nenhuma pessoa foi localizada no cadastro central do SIGAS para este CPF.';
        } else {
            $grouped = [];
            foreach ($journey->historyByPerson((int) $person['id']) as $row) {
                $attendanceId = (int) ($row['atendimento_id'] ?? 0);
                if ($attendanceId <= 0) {
                    continue;
                }

                $originModule = trim((string) ($row['modulo_origem'] ?? ''));
                $currentModule = trim((string) ($row['modulo_atual'] ?? ''));
                $movementOrigin = trim((string) ($row['movimentacao_modulo_origem'] ?? ''));
                $movementDestination = trim((string) ($row['movimentacao_modulo_destino'] ?? ''));
                $visible = $isGlobal
                    || !empty($accessibleModules[$originModule])
                    || !empty($accessibleModules[$currentModule])
                    || !empty($accessibleModules[$movementOrigin])
                    || !empty($accessibleModules[$movementDestination]);

                if (!$visible) {
                    continue;
                }

                if (!isset($grouped[$attendanceId])) {
                    $grouped[$attendanceId] = [
                        'id' => $attendanceId,
                        'protocolo' => (string) ($row['protocolo'] ?? ''),
                        'finalidade' => (string) ($row['finalidade'] ?? ''),
                        'beneficio_modulo' => (string) ($row['beneficio_modulo'] ?? ''),
                        'status' => (string) ($row['atendimento_status'] ?? ''),
                        'aberto_em' => (string) ($row['aberto_em'] ?? ''),
                        'concluido_em' => (string) ($row['concluido_em'] ?? ''),
                        'setor_origem_id' => $row['setor_origem_id'] === null ? null : (int) $row['setor_origem_id'],
                        'setor_atual_id' => $row['setor_atual_id'] === null ? null : (int) $row['setor_atual_id'],
                        'setor_origem' => (string) ($row['setor_origem'] ?? ''),
                        'setor_atual' => (string) ($row['setor_atual'] ?? ''),
                        'modulo_origem' => $originModule,
                        'modulo_atual' => $currentModule,
                        'movements' => [],
                    ];
                }

                if (!empty($row['movimentacao_id'])) {
                    $movementVisible = $isGlobal
                        || !empty($accessibleModules[$movementOrigin])
                        || !empty($accessibleModules[$movementDestination]);
                    if ($movementVisible) {
                        $grouped[$attendanceId]['movements'][] = [
                            'id' => (int) $row['movimentacao_id'],
                            'tipo' => (string) ($row['movimentacao_tipo'] ?? ''),
                            'modulo_origem' => $movementOrigin,
                            'modulo_destino' => $movementDestination,
                            'setor_origem' => (string) ($row['movimentacao_setor_origem'] ?? ''),
                            'setor_destino' => (string) ($row['movimentacao_setor_destino'] ?? ''),
                            'usuario' => (string) ($row['movimentacao_usuario'] ?? ''),
                            'observacao' => (string) ($row['movimentacao_observacao'] ?? ''),
                            'criado_em' => (string) ($row['movimentacao_em'] ?? ''),
                        ];
                    }
                }
            }
            $attendances = array_values($grouped);
        }
    }
}

$sectors = $sectorRepository->allActive();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Trajetória de atendimentos da pessoa no SIGAS Coari.">
    <title>SIGAS Coari — Trajetória da pessoa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body{background:#f4f7f5;font-family:Inter,sans-serif}.journey-shell{max-width:1180px;margin:0 auto;padding:24px}.journey-top{display:flex;gap:16px;align-items:center;justify-content:space-between;margin-bottom:24px}.journey-card{background:#fff;border:1px solid #e3e9e5;border-radius:18px;padding:20px;box-shadow:0 10px 30px rgba(26,60,40,.05)}.journey-search{display:grid;grid-template-columns:1fr auto;gap:10px}.journey-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.journey-summary>div{background:#f8faf9;border:1px solid #e8eeea;border-radius:14px;padding:14px}.journey-summary small{display:block;color:#66756b}.journey-summary strong{display:block;margin-top:4px}.journey-item{border-left:3px solid #198754;padding-left:18px;margin-left:8px;position:relative}.journey-item:before{content:"";position:absolute;width:11px;height:11px;border-radius:50%;background:#198754;left:-7px;top:7px}.journey-movement{background:#f8faf9;border:1px solid #e7ece8;border-radius:12px;padding:12px}.journey-actions{border-top:1px solid #edf1ee;margin-top:16px;padding-top:16px}@media(max-width:800px){.journey-shell{padding:14px}.journey-top{align-items:flex-start;flex-direction:column}.journey-search{grid-template-columns:1fr}.journey-summary{grid-template-columns:1fr 1fr}}
    </style>
</head>
<body>
<main class="journey-shell">
    <header class="journey-top">
        <div>
            <div class="text-success fw-semibold small text-uppercase"><i class="bi bi-signpost-split"></i> Atendimento integrado</div>
            <h1 class="h3 mb-1">Trajetória da pessoa</h1>
            <p class="text-secondary mb-0">Consulte onde o atendimento começou, os encaminhamentos realizados e onde ele está atualmente.</p>
        </div>
        <a class="btn btn-light" href="portal.php"><i class="bi bi-grid"></i> Voltar aos módulos</a>
    </header>

    <section class="journey-card mb-4">
        <form method="get" class="journey-search">
            <div>
                <label class="form-label" for="journeyCpf">CPF da pessoa</label>
                <input class="form-control" id="journeyCpf" name="cpf" inputmode="numeric" value="<?= journey_h($cpf) ?>" placeholder="000.000.000-00" required>
            </div>
            <button class="btn btn-primary align-self-end" type="submit"><i class="bi bi-search"></i> Consultar trajetória</button>
        </form>
    </section>

    <?php if ($success): ?><div class="alert alert-success"><?= journey_h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= journey_h($error) ?></div><?php endif; ?>

    <?php if (is_array($person)): ?>
        <section class="journey-card mb-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div><div class="small text-secondary">Cadastro central</div><h2 class="h4 mb-0"><?= journey_h($person['nome']) ?></h2></div>
                <span class="badge text-bg-light border text-dark">CPF <?= journey_h(substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2)) ?></span>
            </div>
            <div class="journey-summary">
                <div><small>Atendimentos visíveis</small><strong><?= count($attendances) ?></strong></div>
                <div><small>NIS</small><strong><?= journey_h($isGlobal ? ($person['nis'] ?: 'Não informado') : 'Restrito') ?></strong></div>
                <div><small>Telefone</small><strong><?= journey_h($isGlobal ? ($person['telefone'] ?: 'Não informado') : 'Restrito') ?></strong></div>
                <div><small>Situação cadastral</small><strong><?= journey_h($person['status'] ?: 'Não definida') ?></strong></div>
            </div>
        </section>

        <?php if ($attendances === []): ?>
            <div class="journey-card text-center py-5"><i class="bi bi-clock-history fs-2 text-secondary"></i><h2 class="h5 mt-2">Sem trajetória visível</h2><p class="text-secondary mb-0">Ainda não há atendimento rastreado nos módulos que seu perfil pode consultar.</p></div>
        <?php endif; ?>

        <?php foreach ($attendances as $attendance): ?>
            <?php
            $currentModule = (string) $attendance['modulo_atual'];
            $currentSectorId = $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'];
            $canOperate = $attendance['status'] !== 'concluido'
                && isset($catalog[$currentModule])
                && $moduleAccess->canAccess($user, $currentModule)
                && $currentSectorId !== null
                && $authorization->canAccessOperationalSector($user, $currentSectorId);
            ?>
            <section class="journey-card mb-4">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div>
                        <div class="small text-secondary"><?= journey_h($attendance['protocolo']) ?></div>
                        <h2 class="h5 mb-1"><?= journey_h($attendance['finalidade']) ?></h2>
                        <div class="small text-secondary">Aberto em <?= journey_h($attendance['aberto_em']) ?></div>
                    </div>
                    <div class="text-end">
                        <span class="badge text-bg-<?= $attendance['status'] === 'concluido' ? 'success' : ($attendance['status'] === 'encaminhado' ? 'warning' : 'primary') ?>"><?= journey_h(journey_status_label((string) $attendance['status'])) ?></span>
                        <div class="small mt-2">Atual: <strong><?= journey_h(journey_module_label($currentModule, $catalog, $accessibleModules, $isGlobal)) ?></strong></div>
                        <div class="small text-secondary"><?= journey_h(journey_sector_label((string) $attendance['setor_atual'], $currentModule, $accessibleModules, $isGlobal)) ?></div>
                    </div>
                </div>

                <div class="vstack gap-3">
                    <?php foreach ($attendance['movements'] as $movement): ?>
                        <div class="journey-item">
                            <div class="journey-movement">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <strong><?= journey_h(journey_movement_label((string) $movement['tipo'])) ?></strong>
                                    <small class="text-secondary"><?= journey_h($movement['criado_em']) ?></small>
                                </div>
                                <div class="small mt-1">
                                    <?= journey_h(journey_module_label((string) $movement['modulo_origem'], $catalog, $accessibleModules, $isGlobal)) ?>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <?= journey_h(journey_module_label((string) $movement['modulo_destino'], $catalog, $accessibleModules, $isGlobal)) ?>
                                </div>
                                <div class="small text-secondary">
                                    <?= journey_h(journey_sector_label((string) $movement['setor_origem'], (string) $movement['modulo_origem'], $accessibleModules, $isGlobal)) ?>
                                    →
                                    <?= journey_h(journey_sector_label((string) $movement['setor_destino'], (string) $movement['modulo_destino'], $accessibleModules, $isGlobal)) ?>
                                </div>
                                <?php if (trim((string) $movement['observacao']) !== ''): ?><p class="small mb-0 mt-2"><?= journey_h($movement['observacao']) ?></p><?php endif; ?>
                                <?php if ($isGlobal && trim((string) $movement['usuario']) !== ''): ?><div class="small text-secondary mt-1">Responsável: <?= journey_h($movement['usuario']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($canOperate): ?>
                    <div class="journey-actions">
                        <?php if ($attendance['status'] === 'encaminhado'): ?>
                            <form method="post" class="d-inline-block me-2 mb-2">
                                <?= Csrf::input('person-journey') ?>
                                <input type="hidden" name="cpf" value="<?= journey_h($cpf) ?>">
                                <input type="hidden" name="atendimento_id" value="<?= (int) $attendance['id'] ?>">
                                <input type="hidden" name="acao" value="receber">
                                <input type="hidden" name="observacao" value="Atendimento recebido no setor de destino.">
                                <button class="btn btn-success" type="submit"><i class="bi bi-inbox"></i> Receber atendimento</button>
                            </form>
                        <?php endif; ?>

                        <details class="mt-2">
                            <summary class="btn btn-outline-primary">Encaminhar para outro setor/módulo</summary>
                            <form method="post" class="row g-3 mt-1">
                                <?= Csrf::input('person-journey') ?>
                                <input type="hidden" name="cpf" value="<?= journey_h($cpf) ?>">
                                <input type="hidden" name="atendimento_id" value="<?= (int) $attendance['id'] ?>">
                                <input type="hidden" name="acao" value="encaminhar">
                                <div class="col-md-5"><label class="form-label">Setor de destino</label><select class="form-select" name="setor_destino_id" required><option value="">Selecione</option><?php foreach ($sectors as $sector): ?><option value="<?= $sector->id ?>"><?= journey_h($sector->nome) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-5"><label class="form-label">Módulo/benefício</label><select class="form-select" name="modulo_destino" required><option value="">Selecione</option><?php foreach ($catalog as $moduleKey => $definition): ?><option value="<?= journey_h($moduleKey) ?>"><?= journey_h($definition['label']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-10"><label class="form-label">Motivo do encaminhamento</label><textarea class="form-control" name="observacao" minlength="3" maxlength="500" required></textarea></div>
                                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Encaminhar</button></div>
                            </form>
                        </details>

                        <details class="mt-3">
                            <summary class="btn btn-outline-success">Concluir atendimento</summary>
                            <form method="post" class="row g-3 mt-1">
                                <?= Csrf::input('person-journey') ?>
                                <input type="hidden" name="cpf" value="<?= journey_h($cpf) ?>">
                                <input type="hidden" name="atendimento_id" value="<?= (int) $attendance['id'] ?>">
                                <input type="hidden" name="acao" value="concluir">
                                <div class="col-md-10"><label class="form-label">Observação de conclusão</label><textarea class="form-control" name="observacao" minlength="3" maxlength="500" required></textarea></div>
                                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100" type="submit">Concluir</button></div>
                            </form>
                        </details>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
