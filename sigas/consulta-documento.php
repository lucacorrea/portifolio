<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\PermissionService;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::connection();
$userRepository = new UserRepository($pdo);
$sessionRepository = new UserSessionRepository($pdo);
$accessLevelRepository = new AccessLevelRepository($pdo);
$auditService = new AuditService(new AuditLogRepository($pdo));
$authService = new AuthService($userRepository, $sessionRepository, $accessLevelRepository, $auditService);
$user = $authService->requireUser();
$authorization = new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $accessLevelRepository);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function sigas_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_substr($part, 0, 1);
        }
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return mb_strtoupper($letters !== '' ? $letters : 'U');
}

if (!$authorization->can($user, 'comida_mesa.consultar_cpf')) {
    http_response_code(403);
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGAS Coari - Acesso negado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/consulta-documento-ocr.css?v=<?= e((string) filemtime(__DIR__ . '/assets/css/consulta-documento-ocr.css')) ?>" rel="stylesheet">
</head>
<body data-page="consulta">
    <main class="container py-5">
        <section class="content-card mx-auto" style="max-width: 560px;">
            <div class="state-panel show">
                <i class="bi bi-shield-lock"></i>
                <h1 class="fs-4">Acesso negado</h1>
                <p>Seu usuário não possui permissão para consultar CPF no Comida na Mesa.</p>
                <a class="btn btn-primary mt-3" href="dashboard.php"><i class="bi bi-arrow-left"></i>Voltar ao dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}

$level = $user->nivelId === null ? null : $accessLevelRepository->findById($user->nivelId);
$sector = $user->setorId === null ? null : (new SectorRepository($pdo))->findById($user->setorId);
$frontendContext = [
    'user' => [
        'name' => $user->nome,
        'initials' => sigas_initials($user->nome),
        'jobTitle' => $user->cargo ?: ($level?->nome ?? 'Usuário'),
        'sector' => $sector?->nome ?: 'Sem setor',
    ],
    'urls' => [
        'dashboard' => 'dashboard.php',
        'logout' => 'sair.php',
    ],
    'csrf' => [
        'logout' => Csrf::token('logout'),
        'consultarCpf' => Csrf::token('comida_mesa_consultar_cpf'),
        'registrarEntrega' => Csrf::token('comida_mesa_registrar_entrega'),
        'cancelarEntrega' => Csrf::token('comida_mesa_cancelar_entrega'),
    ],
    'consultaDocumento' => [
        'permissions' => [
            'create' => $authorization->can($user, 'comida_mesa.cadastrar'),
            'edit' => $authorization->can($user, 'comida_mesa.editar'),
            'deliver' => $authorization->can($user, 'comida_mesa.entregar'),
            'cancelDelivery' => $authorization->can($user, 'comida_mesa.cancelar_entrega'),
            'manageCompetences' => $authorization->can($user, 'comida_mesa.competencias_gerenciar'),
            'viewDocuments' => $authorization->can($user, 'comida_mesa.documentos_visualizar'),
            'viewHistory' => $authorization->can($user, 'comida_mesa.historico_visualizar'),
        ],
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Consulta real de CPF e registro de entrega mensal no SIGAS Coari.">
    <title>SIGAS Coari - Consulta por CPF</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="consulta">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content document-scan-page" data-consulta-documento>
                <header class="page-header scan-page-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-person-bounding-box"></i>Atendimento</div>
                        <h1>Consulta por CPF</h1>
                        <p>Consulte a pessoa no banco do SIGAS e registre a entrega mensal da cesta quando a família estiver apta.</p>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-light" href="modulo.php"><i class="bi bi-basket2"></i>Comida na Mesa</a>
                        <button class="btn btn-light" type="button" data-reset-consulta><i class="bi bi-arrow-counterclockwise"></i>Nova consulta</button>
                    </div>
                </header>

                <div class="scan-notice" role="note">
                    <i class="bi bi-shield-lock"></i>
                    <div><strong>Leitura automática e segura</strong><span>Posicione o lado do documento onde aparece o CPF. A imagem será processada somente neste dispositivo e não será enviada nem armazenada.</span></div>
                </div>

                <section class="scanner-layout" aria-label="Consulta por CPF">
                    <article class="content-card scanner-capture-card">
                        <div class="scanner-card-heading">
                            <div><div class="card-kicker">Documento</div><h2>Escanear CPF do documento</h2><p>Fotografe o lado da identidade, CIN, RG ou CNH onde o número do CPF esteja impresso.</p></div>
                            <span class="scanner-security"><i class="bi bi-cpu"></i>Processamento local</span>
                        </div>
                        <div class="camera-frame" id="cameraFrame">
                            <video id="scannerVideo" playsinline muted hidden aria-label="Visualização da câmera"></video>
                            <img id="scannerPreview" alt="Pré-visualização do documento capturado" hidden>
                            <span class="ocr-scanning-line" id="ocrScanningLine" hidden></span>
                            <div class="camera-placeholder" id="cameraPlaceholder">
                                <span><i class="bi bi-camera"></i></span>
                                <strong>Posicione todo o documento dentro da moldura, mantenha boa iluminação e evite reflexos.</strong>
                                <small>Caso o CPF não apareça neste lado do documento, fotografe o verso ou digite o número manualmente.</small>
                            </div>
                            <div class="document-guide" aria-hidden="true"><i class="guide-corner top-left"></i><i class="guide-corner top-right"></i><i class="guide-corner bottom-left"></i><i class="guide-corner bottom-right"></i></div>
                        </div>
                        <div class="ocr-status" id="ocrStatus" aria-live="polite" hidden>
                            <div class="ocr-status-heading">
                                <span data-ocr-title>Preparando leitura</span>
                                <strong data-ocr-progress>0%</strong>
                            </div>
                            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="progress-bar" data-ocr-progress-bar></div>
                            </div>
                            <p data-ocr-message></p>
                        </div>
                        <div class="ocr-candidate-list" id="ocrCandidates" aria-live="polite" hidden></div>
                        <div class="camera-actions">
                            <button class="btn btn-primary btn-lg" id="openCameraButton" type="button"><i class="bi bi-camera"></i>Abrir câmera</button>
                            <button class="btn btn-primary btn-lg" id="captureDocumentButton" type="button" hidden><i class="bi bi-upc-scan"></i>Ler CPF</button>
                            <button class="btn btn-light btn-lg" id="retryOcrButton" type="button" hidden><i class="bi bi-arrow-repeat"></i>Fotografar novamente</button>
                            <button class="btn btn-light btn-lg" id="chooseImageButton" type="button"><i class="bi bi-image"></i>Escolher imagem</button>
                            <button class="btn btn-outline-danger btn-lg" id="cancelOcrButton" type="button" hidden><i class="bi bi-x-lg"></i>Cancelar leitura</button>
                            <input class="visually-hidden" id="documentImageInput" type="file" accept="image/*" capture="environment" aria-label="Selecionar ou fotografar documento">
                        </div>
                        <form class="manual-cpf-form mt-4" id="manualCpfForm" action="api/comida-mesa/consultar-cpf.php" method="post" novalidate>
                            <input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['consultarCpf']) ?>">
                            <label class="form-label" for="manualCpf">CPF da pessoa</label>
                            <div class="manual-cpf-row">
                                <div class="input-icon-field"><i class="bi bi-person-vcard"></i><input class="form-control form-control-lg" id="manualCpf" name="cpf" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" maxlength="14"></div>
                                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i><span>Consultar</span></button>
                            </div>
                            <div class="invalid-feedback" id="manualCpfFeedback">Informe um CPF válido.</div>
                            <p class="manual-cpf-help">Você também pode digitar o CPF manualmente.</p>
                        </form>
                    </article>

                    <aside class="scan-result-column" aria-live="polite">
                        <article class="content-card scan-result-empty" id="consultaResult">
                            <span class="result-empty-icon"><i class="bi bi-person-lines-fill"></i></span>
                            <h2>Resultado da consulta</h2>
                            <p>Informe o CPF para visualizar pessoa, família, inscrição, competência e situação da entrega.</p>
                        </article>
                    </aside>
                </section>
            </main>
            <footer class="app-footer"><span>Consulta real no banco do SIGAS.</span><span>SIGAS Coari - SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>

    <div class="modal fade" id="consultaDeliveryModal" tabindex="-1" aria-labelledby="consultaDeliveryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form id="consultaDeliveryForm" action="api/comida-mesa/registrar-entrega.php" method="post" novalidate>
            <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-basket2"></i>Entrega mensal</div><h2 class="modal-title fs-5" id="consultaDeliveryTitle">Registrar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['registrarEntrega']) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id"><div data-form-alert></div><dl class="small mb-3"><dt>Responsável</dt><dd data-delivery-name></dd><dt>Família</dt><dd data-delivery-family></dd><dt>Competência</dt><dd data-delivery-competence></dd><dt>Polo</dt><dd data-delivery-pole></dd></dl><div class="mb-3"><label class="form-label">Nome do recebedor</label><input class="form-control" name="recebedor_nome" required></div><div class="mb-3"><label class="form-label">CPF do recebedor</label><input class="form-control" name="recebedor_cpf" inputmode="numeric"></div><div class="mb-3"><label class="form-label">Parentesco</label><input class="form-control" name="recebedor_parentesco"></div><div><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="3"></textarea></div></div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i>Confirmar</button></div>
        </form></div></div>
    </div>

    <div class="modal fade" id="consultaCancelModal" tabindex="-1" aria-labelledby="consultaCancelTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="consultaCancelForm" action="api/comida-mesa/cancelar-entrega.php" method="post" novalidate>
            <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-x-circle"></i>Cancelamento</div><h2 class="modal-title fs-5" id="consultaCancelTitle">Cancelar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['cancelarEntrega']) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="competencia_id"><div data-form-alert></div><label class="form-label">Motivo</label><textarea class="form-control" name="motivo" rows="4" minlength="10" maxlength="255" required></textarea></div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger" type="submit"><i class="bi bi-x-lg"></i>Cancelar entrega</button></div>
        </form></div></div>
    </div>

    <div class="modal fade" id="consultaDetailModal" tabindex="-1" aria-labelledby="consultaDetailTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-eye"></i>Detalhes</div><h2 class="modal-title fs-5" id="consultaDetailTitle">Detalhes da família</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" data-detail-content></div></div></div></div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.SIGAS_CONTEXT = <?= json_encode($frontendContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@7.0.0/dist/tesseract.min.js"></script>
    <script src="assets/js/cpf-ocr.js?v=<?= e((string) filemtime(__DIR__ . '/assets/js/cpf-ocr.js')) ?>"></script>
    <script src="assets/js/consulta-documento.js?v=<?= e((string) filemtime(__DIR__ . '/assets/js/consulta-documento.js')) ?>"></script>
</body>
</html>
