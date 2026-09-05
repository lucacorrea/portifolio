<?php

declare(strict_types=1);

$isAjaxRequest = isset($_GET['ajax']) && trim((string)$_GET['ajax']) !== '';

/*
 * Em chamadas AJAX, a conexao deve lancar excecao em vez de imprimir texto
 * e encerrar a pagina. Assim conseguimos devolver JSON consistente.
 */
if ($isAjaxRequest) {
    $GLOBALS['SEMAS_DB_THROW_ON_ERROR'] = true;
}

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

try {
    require_once __DIR__ . '/assets/conexao.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Conexão principal não disponível.');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once __DIR__ . '/pessoas/bootstrap.php';
    $csrf = pc_session_csrf();

    $pcPermissions = array(
        'canCreateRequest' => auth_can_create_solicitacao(),
        'canEditRequest' => auth_can_edit_solicitacao(),
        'canEditPerson' => auth_can_edit_solicitante(),
        'canAssignBenefit' => auth_can_assign_beneficio(),
        'canViewBenefitAssignment' => auth_can_view_beneficio_atribuicao(),
        'canPrintSocio' => auth_can_print_socioeconomico(),
        'isIntern' => auth_is_estagiario(),
    );

    if ($isAjaxRequest) {
        $ajaxAction = trim((string)$_GET['ajax']);

        if ($ajaxAction === 'detalhes') {
            require __DIR__ . '/pessoas/actions/detalhes.php';
            exit;
        }

        if ($ajaxAction === 'nova-solicitacao') {
            require __DIR__ . '/pessoas/actions/nova-solicitacao.php';
            exit;
        }

        if ($ajaxAction === 'editar-solicitacao') {
            require __DIR__ . '/pessoas/actions/editar-solicitacao.php';
            exit;
        }

        if ($ajaxAction === 'materializar-solicitacao-inicial') {
            require __DIR__ . '/pessoas/actions/materializar-solicitacao-inicial.php';
            exit;
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo pc_json(array(
            'ok' => false,
            'error' => 'Ação AJAX não encontrada.',
            'code' => 'AJAX_NOT_FOUND',
        ));
        exit;
    }
} catch (Throwable $e) {
    if ($isAjaxRequest) {
        @error_log(
            '[SEMAS][PESSOAS_AJAX] '
            . get_class($e)
            . ' | '
            . $e->getMessage()
        );

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $payload = array(
            'ok' => false,
            'error' => 'Não foi possível concluir a operação no servidor.',
            'code' => 'SERVER_ERROR',
        );

        if (function_exists('pc_json')) {
            echo pc_json($payload);
        } else {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    throw $e;
}

$filters = array(
    'q' => isset($_GET['q']) ? trim((string)$_GET['q']) : '',
    'bairro_id' => isset($_GET['bairro_id']) ? (string)$_GET['bairro_id'] : '',
    'programa' => isset($_GET['programa']) ? (string)$_GET['programa'] : '',
    'beneficio_situacao' => isset($_GET['beneficio_situacao']) ? (string)$_GET['beneficio_situacao'] : '',
    'beneficio_quantidade' => isset($_GET['beneficio_quantidade']) ? (string)$_GET['beneficio_quantidade'] : '',
    'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
    'per_page' => isset($_GET['per_page']) ? max(10, min(100, (int)$_GET['per_page'])) : 20,
);

$result = $pessoasService->listar($filters);
$bairros = $pessoasService->bairros();
$ajudasTipos = $pessoasService->ajudasTipos();
$indicadores = $pessoasService->indicadoresLocais();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Pessoas Cadastradas - ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bootstrap.css">
  <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/pages/pessoas-cadastradas.css?v=<?= @filemtime(__DIR__ . '/assets/css/pages/pessoas-cadastradas.css') ?: time() ?>">
  <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">
</head>
<body>
<div id="app">
  <?php require __DIR__ . '/pessoas/views/layout/sidebar.php'; ?>

  <div id="main">
    <header class="pc-mobile-header mb-2">
      <a href="#" class="burger-btn d-block d-xl-none" aria-label="Abrir menu"><i class="bi bi-justify fs-3"></i></a>
    </header>

    <div class="page-heading pc-page-shell">
      <section class="pc-page-header mb-3">
        <div class="pc-page-header-main">
          <div class="pc-page-heading-copy">
            <div class="pc-page-kicker"><i class="bi bi-people"></i> Gestão de pessoas</div>
            <h1>Pessoas cadastradas</h1>
            <p>Consulte a base do ANEXO, acompanhe os cadastros e visualize vínculos com programas do SIGAS pelo CPF.</p>
          </div>

          <div class="pc-page-header-actions">
            <div class="pc-connection-status <?= $result['sigas_disponivel'] ? 'is-online' : 'is-offline' ?>">
              <span class="pc-connection-dot"></span>
              <div>
                <strong>SIGAS</strong>
                <small><?= $result['sigas_disponivel'] ? 'Integração disponível' : 'Integração indisponível' ?></small>
              </div>
            </div>

            <a class="btn btn-outline-secondary pc-header-btn" href="pessoasCadastradas.php" title="Atualizar listagem">
              <i class="bi bi-arrow-clockwise"></i>
              <span>Atualizar</span>
            </a>
            <a class="btn btn-primary pc-header-btn" href="cadastrarSolicitante.php">
              <i class="bi bi-person-plus"></i>
              <span>Novo cadastro</span>
            </a>
          </div>
        </div>

        <div class="pc-page-header-bottom">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb pc-breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a></li>
              <li class="breadcrumb-item">Solicitantes</li>
              <li class="breadcrumb-item active" aria-current="page">Cadastrados</li>
            </ol>
          </nav>
          <span class="pc-header-meta"><i class="bi bi-database"></i> <?= (int)$indicadores['total'] ?> registros na base ANEXO</span>
        </div>
      </section>

      <?php require __DIR__ . '/pessoas/views/indicadores.php'; ?>
      <?php require __DIR__ . '/pessoas/views/filtros.php'; ?>
      <?php require __DIR__ . '/pessoas/views/tabela.php'; ?>
      <?php require __DIR__ . '/pessoas/views/paginacao.php'; ?>
    </div>

    <?php require __DIR__ . '/pessoas/views/layout/footer.php'; ?>
  </div>
</div>

<?php require __DIR__ . '/pessoas/views/modals/acoes.php'; ?>
<?php require __DIR__ . '/pessoas/views/modals/detalhes.php'; ?>
<?php require __DIR__ . '/pessoas/views/modals/nova-solicitacao.php'; ?>
<?php if (!empty($pcPermissions['canEditRequest'])) require __DIR__ . '/pessoas/views/modals/editar-solicitacao.php'; ?>

<script>
window.PC_CONFIG = <?= pc_json(array(
    'baseUrl' => 'pessoasCadastradas.php',
    'csrf' => $csrf,
    'sigasDisponivel' => $result['sigas_disponivel'],
    'permissions' => $pcPermissions
)) ?>;
</script>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/pages/pessoas-cadastradas.js?v=<?= @filemtime(__DIR__ . '/assets/js/pages/pessoas-cadastradas.js') ?: time() ?>"></script>
</body>
</html>
