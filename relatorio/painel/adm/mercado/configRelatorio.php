<?php

declare(strict_types=1);
session_start();

/* ======================
   SEGURANÇA
====================== */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ======================
   FLASH
====================== */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* ======================
   CONEXÃO
====================== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* ======================
   FEIRA ID
====================== */
$feiraId = 3; // Feira Alternativa

/* ======================
   DIRETÓRIO DE UPLOADS
====================== */
$uploadDir = '../../../uploads/relatorios/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

/* ======================
   CARREGAR CONFIGURAÇÕES
====================== */
$config = [
  'titulo_feira' => 'Feira Alternativa',
  'subtitulo_feira' => 'Francisco Lopes da Silva – "Folha"',
  'municipio' => 'Coari',
  'estado' => 'AM',
  'secretaria' => 'Secretaria de Desenvolvimento Rural e Econômico',
  'logotipo_prefeitura' => '',
  'logotipo_feira' => '',
  'incluir_introducao' => 1,
  'texto_introducao' => 'A Feira Alternativa "{titulo_feira}" é um espaço de valorização da agricultura familiar e de comercialização de alimentos cultivados no município de {municipio}-{estado}.',
  'incluir_produtos_comercializados' => 1,
  'incluir_conclusao' => 1,
  'texto_conclusao' => 'O levantamento demonstra a relevância da {titulo_feira} para a economia agrícola do município, garantindo escoamento da produção, geração de renda e acesso da população a alimentos saudáveis.',
  'assinatura_nome' => '',
  'assinatura_cargo' => '',
  'mostrar_graficos' => 1,
  'mostrar_por_categoria' => 1,
  'mostrar_por_feirante' => 1,
  'produtos_detalhados' => 1,
];

// Tentar carregar do banco se existir tabela de configurações
try {
  $st = $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'config_relatorio'
  ");

  if ((int)$st->fetchColumn() > 0) {
    $st = $pdo->prepare("SELECT * FROM config_relatorio WHERE feira_id = :feira_id");
    $st->execute([':feira_id' => $feiraId]);
    $savedConfig = $st->fetch(PDO::FETCH_ASSOC);

    if ($savedConfig) {
      foreach ($config as $key => $defaultValue) {
        if (isset($savedConfig[$key])) {
          $config[$key] = $savedConfig[$key];
        }
      }
    }
  }
} catch (Exception $e) {
  // Tabela não existe ainda
}

/* ======================
   UPLOAD DE ARQUIVOS
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_logo'])) {
  $tipoLogo = $_POST['tipo_logo'] ?? 'prefeitura';
  $file = $_FILES['upload_logo'];
  
  if ($file['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
      echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.']);
      exit;
    }
    
    if ($file['size'] > $maxSize) {
      echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo: 5MB']);
      exit;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $tipoLogo . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
      $url = '../../../uploads/relatorios/' . $filename;
      echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo']);
    }
  } else {
    echo json_encode(['success' => false, 'error' => 'Erro no upload']);
  }
  exit;
}

/* ======================
   SALVAR CONFIGURAÇÕES
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
  try {
    // Criar tabela se não existir
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS relatorio_config (
        id INT PRIMARY KEY DEFAULT 1,
        titulo_feira VARCHAR(255),
        subtitulo_feira VARCHAR(255),
        municipio VARCHAR(100),
        estado VARCHAR(2),
        secretaria VARCHAR(255),
        logotipo_prefeitura VARCHAR(255),
        logotipo_feira VARCHAR(255),
        incluir_introducao TINYINT(1) DEFAULT 1,
        texto_introducao TEXT,
        incluir_produtos_comercializados TINYINT(1) DEFAULT 1,
        incluir_conclusao TINYINT(1) DEFAULT 1,
        texto_conclusao TEXT,
        assinatura_nome VARCHAR(255),
        assinatura_cargo VARCHAR(255),
        mostrar_graficos TINYINT(1) DEFAULT 1,
        mostrar_por_categoria TINYINT(1) DEFAULT 1,
        mostrar_por_feirante TINYINT(1) DEFAULT 1,
        produtos_detalhados TINYINT(1) DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )
    ");

    // Preparar dados
    $dados = [
      ':titulo_feira' => $_POST['titulo_feira'] ?? '',
      ':subtitulo_feira' => $_POST['subtitulo_feira'] ?? '',
      ':municipio' => $_POST['municipio'] ?? '',
      ':estado' => $_POST['estado'] ?? 'AM',
      ':secretaria' => $_POST['secretaria'] ?? '',
      ':logotipo_prefeitura' => $_POST['logotipo_prefeitura'] ?? '',
      ':logotipo_feira' => $_POST['logotipo_feira'] ?? '',
      ':incluir_introducao' => isset($_POST['incluir_introducao']) ? 1 : 0,
      ':texto_introducao' => $_POST['texto_introducao'] ?? '',
      ':incluir_produtos_comercializados' => isset($_POST['incluir_produtos_comercializados']) ? 1 : 0,
      ':incluir_conclusao' => isset($_POST['incluir_conclusao']) ? 1 : 0,
      ':texto_conclusao' => $_POST['texto_conclusao'] ?? '',
      ':assinatura_nome' => $_POST['assinatura_nome'] ?? '',
      ':assinatura_cargo' => $_POST['assinatura_cargo'] ?? '',
      ':mostrar_graficos' => isset($_POST['mostrar_graficos']) ? 1 : 0,
      ':mostrar_por_categoria' => isset($_POST['mostrar_por_categoria']) ? 1 : 0,
      ':mostrar_por_feirante' => isset($_POST['mostrar_por_feirante']) ? 1 : 0,
      ':produtos_detalhados' => isset($_POST['produtos_detalhados']) ? 1 : 0,
    ];

    // Verificar se já existe configuração
    $st = $pdo->prepare("SELECT COUNT(*) FROM config_relatorio WHERE feira_id = :feira_id");
    $st->execute([':feira_id' => $feiraId]);
    $existe = (int)$st->fetchColumn() > 0;

    // Adicionar feira_id aos dados
    $dados['feira_id'] = $feiraId;

    if ($existe) {
      // UPDATE
      $sql = "
        UPDATE config_relatorio SET
          titulo_feira = :titulo_feira,
          subtitulo_feira = :subtitulo_feira,
          municipio = :municipio,
          estado = :estado,
          secretaria = :secretaria,
          logotipo_prefeitura = :logotipo_prefeitura,
          logotipo_feira = :logotipo_feira,
          incluir_introducao = :incluir_introducao,
          texto_introducao = :texto_introducao,
          incluir_produtos_comercializados = :incluir_produtos_comercializados,
          incluir_conclusao = :incluir_conclusao,
          texto_conclusao = :texto_conclusao,
          assinatura_nome = :assinatura_nome,
          assinatura_cargo = :assinatura_cargo,
          mostrar_graficos = :mostrar_graficos,
          mostrar_por_categoria = :mostrar_por_categoria,
          mostrar_por_feirante = :mostrar_por_feirante,
          produtos_detalhados = :produtos_detalhados
        WHERE feira_id = :feira_id
      ";
    } else {
      // INSERT
      $sql = "
        INSERT INTO config_relatorio (
          feira_id, titulo_feira, subtitulo_feira, municipio, estado, secretaria,
          logotipo_prefeitura, logotipo_feira, incluir_introducao, texto_introducao,
          incluir_produtos_comercializados, incluir_conclusao, texto_conclusao,
          assinatura_nome, assinatura_cargo, mostrar_graficos, mostrar_por_categoria,
          mostrar_por_feirante, produtos_detalhados
        ) VALUES (
          :feira_id, :titulo_feira, :subtitulo_feira, :municipio, :estado, :secretaria,
          :logotipo_prefeitura, :logotipo_feira, :incluir_introducao, :texto_introducao,
          :incluir_produtos_comercializados, :incluir_conclusao, :texto_conclusao,
          :assinatura_nome, :assinatura_cargo, :mostrar_graficos, :mostrar_por_categoria,
          :mostrar_por_feirante, :produtos_detalhados
        )
      ";
    }

    $st = $pdo->prepare($sql);
    $st->execute($dados);

    $_SESSION['flash_ok'] = 'Configurações salvas com sucesso!';
    header('Location: configRelatorio.php');
    exit;

  } catch (Exception $e) {
    $err = 'Erro ao salvar configurações: ' . $e->getMessage();
  }
}

/* ======================
   FINAL
====================== */
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Configurar Relatórios</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover {
      color: blue !important;
    }

    .nav-link {
      color: black !important;
    }

    .sidebar .sub-menu .nav-item .nav-link {
      margin-left: -35px !important;
    }

    .sidebar .sub-menu li {
      list-style: none !important;
    }

    .form-control {
      height: 42px;
    }

    .btn {
      height: 42px;
    }

    textarea.form-control {
      height: auto;
      min-height: 100px;
    }

    /* Multi-step progress */
    .step-progress {
      display: flex;
      justify-content: space-between;
      margin-bottom: 40px;
      position: relative;
    }

    .step-progress::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 0;
      right: 0;
      height: 3px;
      background: #e9ecef;
      z-index: 0;
    }

    .step-progress-fill {
      position: absolute;
      top: 20px;
      left: 0;
      height: 3px;
      background: #231475;
      z-index: 1;
      transition: width 0.3s ease;
    }

    .step-item {
      flex: 1;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #fff;
      border: 3px solid #e9ecef;
      margin: 0 auto 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #6c757d;
      transition: all 0.3s ease;
    }

    .step-item.active .step-circle {
      background: #231475;
      border-color: #231475;
      color: white;
    }

    .step-item.completed .step-circle {
      background: #22c55e;
      border-color: #22c55e;
      color: white;
    }

    .step-label {
      font-size: 13px;
      font-weight: 600;
      color: #6c757d;
    }

    .step-item.active .step-label {
      color: #231475;
    }

    /* Step content */
    .step-content {
      display: none;
    }

    .step-content.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .config-section {
      border-left: 4px solid #231475;
      padding-left: 20px;
      margin-bottom: 30px;
    }

    .config-section h5 {
      color: #231475;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .form-label {
      font-weight: 600;
      margin-bottom: 6px;
      font-size: 13px;
    }

    .form-text {
      font-size: 12px;
      color: #6c757d;
    }

    .custom-switch {
      padding-left: 2.5rem;
    }

    .custom-switch .custom-control-label::before {
      left: -2.5rem;
      width: 2rem;
      height: 1.2rem;
      border-radius: 1rem;
    }

    .custom-switch .custom-control-label::after {
      top: calc(0.15rem + 2px);
      left: calc(-2.5rem + 2px);
      width: calc(1.2rem - 4px);
      height: calc(1.2rem - 4px);
      border-radius: 0.5rem;
    }

    .preview-box {
      background: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: 8px;
      padding: 20px;
      margin-top: 10px;
    }

    .preview-box h6 {
      color: #231475;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .variable-tag {
      background: #e3f2fd;
      color: #1976d2;
      padding: 2px 8px;
      border-radius: 4px;
      font-family: monospace;
      font-size: 12px;
      margin: 2px;
      display: inline-block;
    }

    /* Upload área */
    .upload-area {
      border: 2px dashed #dee2e6;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      background: #f8f9fa;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .upload-area:hover {
      border-color: #231475;
      background: #f1f3ff;
    }

    .upload-area.dragover {
      border-color: #231475;
      background: #e8ebff;
    }

    .upload-area i {
      font-size: 48px;
      color: #231475;
      margin-bottom: 15px;
    }

    .upload-area input[type="file"] {
      display: none;
    }

    .image-preview {
      margin-top: 20px;
      display: none;
    }

    .image-preview.show {
      display: block;
    }

    .preview-img {
      max-width: 100%;
      max-height: 200px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .preview-actions {
      margin-top: 15px;
    }

    /* Flash top-right */
    .sig-flash-wrap {
      position: fixed;
      top: 78px;
      right: 18px;
      width: min(420px, calc(100vw - 36px));
      z-index: 9999;
      pointer-events: none;
    }

    .sig-toast.alert {
      pointer-events: auto;
      border: 0 !important;
      border-left: 6px solid !important;
      border-radius: 14px !important;
      padding: 10px 12px !important;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .10) !important;
      font-size: 13px !important;
      margin-bottom: 10px !important;
      opacity: 0;
      transform: translateX(10px);
      animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }

    .sig-toast--success {
      background: #f1fff6 !important;
      border-left-color: #22c55e !important;
    }

    .sig-toast--danger {
      background: #fff1f2 !important;
      border-left-color: #ef4444 !important;
    }

    .sig-toast__row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .sig-toast__icon i {
      font-size: 16px;
      margin-top: 2px;
    }

    .sig-toast__title {
      font-weight: 800;
      margin-bottom: 1px;
      line-height: 1.1;
    }

    .sig-toast__text {
      margin: 0;
      line-height: 1.25;
    }

    .sig-toast .close {
      opacity: .55;
      font-size: 18px;
      line-height: 1;
      padding: 0 6px;
    }

    .sig-toast .close:hover {
      opacity: 1;
    }

    @keyframes sigToastIn {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes sigToastOut {
      to {
        opacity: 0;
        transform: translateX(12px);
        visibility: hidden;
      }
    }

    .btn-navigation {
      min-width: 120px;
    }

    .loading-spinner {
      display: inline-block;
      width: 14px;
      height: 14px;
      border: 2px solid #fff;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
        <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>

        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item">
            <span class="nav-link">
              <i class="ti-user mr-1"></i> <?= h($nomeUsuario) ?> (ADMIN)
            </span>
          </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>

    <?php if ($msg || $err): ?>
      <div class="sig-flash-wrap">
        <?php if ($msg): ?>
          <div class="alert sig-toast sig-toast--success alert-dismissible" role="alert">
            <div class="sig-toast__row">
              <div class="sig-toast__icon"><i class="ti-check"></i></div>
              <div>
                <div class="sig-toast__title">Tudo certo!</div>
                <p class="sig-toast__text"><?= h($msg) ?></p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>

        <?php if ($err): ?>
          <div class="alert sig-toast sig-toast--danger alert-dismissible" role="alert">
            <div class="sig-toast__row">
              <div class="sig-toast__icon"><i class="ti-alert"></i></div>
              <div>
                <div class="sig-toast__title">Atenção!</div>
                <p class="sig-toast__text"><?= h($err) ?></p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="container-fluid page-body-wrapper">

      <!-- SIDEBAR -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- CADASTROS -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse" id="feiraCadastros">
              <ul class="nav flex-column sub-menu" style="background: white !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./listaProduto.php">
                    <i class="ti-clipboard mr-2"></i> Lista de Produtos
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./listaCategoria.php">
                    <i class="ti-layers mr-2"></i> Categorias
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./listaUnidade.php">
                    <i class="ti-ruler-pencil mr-2"></i> Unidades
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./listaProdutor.php">
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <!-- MOVIMENTO -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i>
              <span class="menu-title">Movimento</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item">
                  <a class="nav-link" href="./lancamentos.php">
                    <i class="ti-write mr-2"></i> Lançamentos (Vendas)
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./fechamentoDia.php">
                    <i class="ti-check-box mr-2"></i> Fechamento do Dia
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <!-- RELATÓRIOS -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i>
              <span class="menu-title">Relatórios</span>
              <i class="menu-arrow"></i>
            </a>

            <div class="collapse show" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <style>
                  .sub-menu .nav-item .nav-link {
                    color: black !important;
                  }

                  .sub-menu .nav-item .nav-link:hover {
                    color: blue !important;
                  }
                </style>
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioFinanceiro.php">
                    <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioProdutos.php">
                    <i class="ti-list mr-2"></i> Produtos Comercializados
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="./relatorioMensal.php">
                    <i class="ti-calendar mr-2"></i> Resumo Mensal
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link active" href="./configRelatorio.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-settings mr-2"></i> Configurar
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <!-- Título DIVERSOS -->
          <li class="nav-item" style="pointer-events:none;">
            <span style="
                  display:block;
                  padding: 5px 15px 5px;
                  font-size: 11px;
                  font-weight: 600;
                  letter-spacing: 1px;
                  color: #6c757d;
                  text-transform: uppercase;
                ">
              Links Diversos
            </span>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="../index.php">
              <i class="ti-home menu-icon"></i>
              <span class="menu-title"> Painel Principal</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="../produtor/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Produtor</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="../mercado/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Mercado Municipal</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
              <i class="ti-headphone-alt menu-icon"></i>
              <span class="menu-title">Suporte</span>
            </a>
          </li>

        </ul>
      </nav>

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <!-- ======================
         CABEÇALHO
         ====================== -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                  <h2 class="font-weight-bold mb-1">Configurar Relatórios</h2>
                  <p class="text-muted mb-0">
                    Personalize o cabeçalho, textos e informações dos relatórios mensais gerados
                  </p>
                </div>
                <div>
                  <a href="previewRelatorio.php" target="_blank" class="btn btn-outline-primary">
                    <i class="ti-eye mr-1"></i> Pré-visualizar
                  </a>
                </div>
              </div>
              <hr>
            </div>
          </div>

          <!-- PROGRESS STEPS -->
          <div class="step-progress">
            <div class="step-progress-fill" id="progressFill"></div>
            <div class="step-item active" data-step="1">
              <div class="step-circle">1</div>
              <div class="step-label">Informações Gerais</div>
            </div>
            <div class="step-item" data-step="2">
              <div class="step-circle">2</div>
              <div class="step-label">Logotipos</div>
            </div>
            <div class="step-item" data-step="3">
              <div class="step-circle">3</div>
              <div class="step-label">Textos</div>
            </div>
            <div class="step-item" data-step="4">
              <div class="step-circle">4</div>
              <div class="step-label">Conteúdo</div>
            </div>
            <div class="step-item" data-step="5">
              <div class="step-circle">5</div>
              <div class="step-label">Revisão</div>
            </div>
          </div>

          <!-- FORMULÁRIO -->
          <form method="POST" action="" id="configForm">
            
            <!-- ======================
               STEP 1: INFORMAÇÕES GERAIS
            ====================== -->
            <div class="step-content active" data-step="1">
              <div class="card">
                <div class="card-body">
                  <div class="config-section">
                    <h5><i class="ti-info-alt mr-2"></i>Informações Gerais</h5>
                    
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Nome do Mercado Municipal *</label>
                        <input type="text" name="titulo_feira" class="form-control" 
                               value="<?= h($config['titulo_feira']) ?>" required>
                        <small class="form-text">Ex: Mercado Municipal de São Paulo</small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="form-label">Subtítulo / Nome popular</label>
                        <input type="text" name="subtitulo_feira" class="form-control" 
                               value="<?= h($config['subtitulo_feira']) ?>">
                        <small class="form-text">Ex: Francisco Lopes da Silva – "Folha"</small>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-12 mb-3">
                        <label class="form-label">Secretaria / Órgão Responsável</label>
                        <input type="text" name="secretaria" class="form-control" 
                               value="<?= h($config['secretaria']) ?>">
                        <small class="form-text">Ex: Secretaria de Desenvolvimento Rural e Econômico</small>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-10 mb-3">
                        <label class="form-label">Município *</label>
                        <input type="text" name="municipio" class="form-control" 
                               value="<?= h($config['municipio']) ?>" required>
                      </div>

                      <div class="col-md-2 mb-3">
                        <label class="form-label">UF *</label>
                        <input type="text" name="estado" class="form-control text-uppercase" 
                               value="<?= h($config['estado']) ?>" maxlength="2" required>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ======================
               STEP 2: LOGOTIPOS
            ====================== -->
            <div class="step-content" data-step="2">
              <div class="card">
                <div class="card-body">
                  <div class="config-section">
                    <h5><i class="ti-image mr-2"></i>Logotipos</h5>
                    
                    <div class="row">
                      <!-- Logotipo Prefeitura -->
                      <div class="col-md-6 mb-4">
                        <label class="form-label">Logotipo da Prefeitura</label>
                        <div class="upload-area" id="uploadAreaPrefeitura">
                          <i class="ti-upload"></i>
                          <h6>Arraste ou clique para fazer upload</h6>
                          <p class="text-muted mb-0">JPG, PNG, GIF ou WebP (máx. 5MB)</p>
                          <input type="file" id="fileInputPrefeitura" accept="image/*">
                        </div>
                        <input type="hidden" name="logotipo_prefeitura" id="urlLogoPrefeitura" 
                               value="<?= h($config['logotipo_prefeitura']) ?>">
                        
                        <div class="image-preview <?= $config['logotipo_prefeitura'] ? 'show' : '' ?>" id="previewPrefeitura">
                          <img src="<?= h($config['logotipo_prefeitura']) ?>" class="preview-img" alt="Preview">
                          <div class="preview-actions">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeImage('prefeitura')">
                              <i class="ti-trash mr-1"></i> Remover
                            </button>
                          </div>
                        </div>
                      </div>

                      <!-- Logotipo Feira -->
                      <div class="col-md-6 mb-4">
                        <label class="form-label">Logotipo da Feira</label>
                        <div class="upload-area" id="uploadAreaFeira">
                          <i class="ti-upload"></i>
                          <h6>Arraste ou clique para fazer upload</h6>
                          <p class="text-muted mb-0">JPG, PNG, GIF ou WebP (máx. 5MB)</p>
                          <input type="file" id="fileInputFeira" accept="image/*">
                        </div>
                        <input type="hidden" name="logotipo_feira" id="urlLogoFeira" 
                               value="<?= h($config['logotipo_feira']) ?>">
                        
                        <div class="image-preview <?= $config['logotipo_feira'] ? 'show' : '' ?>" id="previewFeira">
                          <img src="<?= h($config['logotipo_feira']) ?>" class="preview-img" alt="Preview">
                          <div class="preview-actions">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeImage('feira')">
                              <i class="ti-trash mr-1"></i> Remover
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ======================
               STEP 3: TEXTOS
            ====================== -->
            <div class="step-content" data-step="3">
              <div class="card">
                <div class="card-body">
                  
                  <!-- Introdução -->
                  <div class="config-section">
                    <h5><i class="ti-file mr-2"></i>Introdução do Relatório</h5>

                    <div class="custom-control custom-switch mb-3">
                      <input type="checkbox" class="custom-control-input" id="incluir_introducao" 
                             name="incluir_introducao" <?= $config['incluir_introducao'] ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="incluir_introducao">
                        Incluir seção de introdução no relatório
                      </label>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Texto da Introdução</label>
                      <textarea name="texto_introducao" class="form-control" rows="4"><?= h($config['texto_introducao']) ?></textarea>
                      
                      <div class="preview-box">
                        <h6>Variáveis disponíveis:</h6>
                        <span class="variable-tag">{titulo_feira}</span>
                        <span class="variable-tag">{subtitulo_feira}</span>
                        <span class="variable-tag">{municipio}</span>
                        <span class="variable-tag">{estado}</span>
                        <span class="variable-tag">{periodo}</span>
                        <p class="mt-2 mb-0 small">Essas variáveis serão substituídas automaticamente no relatório final.</p>
                      </div>
                    </div>
                  </div>

                  <!-- Conclusão -->
                  <div class="config-section">
                    <h5><i class="ti-check-box mr-2"></i>Conclusão do Relatório</h5>

                    <div class="custom-control custom-switch mb-3">
                      <input type="checkbox" class="custom-control-input" id="incluir_conclusao" 
                             name="incluir_conclusao" <?= $config['incluir_conclusao'] ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="incluir_conclusao">
                        Incluir seção de conclusão no relatório
                      </label>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Texto da Conclusão</label>
                      <textarea name="texto_conclusao" class="form-control" rows="4"><?= h($config['texto_conclusao']) ?></textarea>
                      
                      <div class="preview-box">
                        <h6>Variáveis disponíveis:</h6>
                        <span class="variable-tag">{titulo_feira}</span>
                        <span class="variable-tag">{subtitulo_feira}</span>
                        <span class="variable-tag">{municipio}</span>
                        <span class="variable-tag">{estado}</span>
                        <span class="variable-tag">{total_periodo}</span>
                        <p class="mt-2 mb-0 small">Essas variáveis serão substituídas automaticamente no relatório final.</p>
                      </div>
                    </div>
                  </div>

                  <!-- Assinatura -->
                  <div class="config-section">
                    <h5><i class="ti-pencil-alt mr-2"></i>Assinatura</h5>

                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label">Nome do Responsável</label>
                        <input type="text" name="assinatura_nome" class="form-control" 
                               value="<?= h($config['assinatura_nome']) ?>" 
                               placeholder="Ex: João Silva">
                        <small class="form-text">Opcional: nome para rodapé do relatório</small>
                      </div>

                      <div class="col-md-6 mb-3">
                        <label class="form-label">Cargo</label>
                        <input type="text" name="assinatura_cargo" class="form-control" 
                               value="<?= h($config['assinatura_cargo']) ?>" 
                               placeholder="Ex: Secretário de Agricultura">
                        <small class="form-text">Opcional: cargo do responsável</small>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <!-- ======================
               STEP 4: CONTEÚDO
            ====================== -->
            <div class="step-content" data-step="4">
              <div class="card">
                <div class="card-body">
                  
                  <div class="config-section">
                    <h5><i class="ti-shopping-cart mr-2"></i>Produtos Comercializados</h5>

                    <div class="custom-control custom-switch mb-3">
                      <input type="checkbox" class="custom-control-input" id="incluir_produtos_comercializados" 
                             name="incluir_produtos_comercializados" <?= $config['incluir_produtos_comercializados'] ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="incluir_produtos_comercializados">
                        Listar produtos comercializados no período
                      </label>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                      <input type="checkbox" class="custom-control-input" id="produtos_detalhados" 
                             name="produtos_detalhados" <?= $config['produtos_detalhados'] ? 'checked' : '' ?>>
                      <label class="custom-control-label" for="produtos_detalhados">
                        Mostrar produtos organizados por categoria (frutas, legumes, etc.)
                      </label>
                    </div>
                  </div>

                  <div class="config-section">
                    <h5><i class="ti-layout mr-2"></i>Visualização e Gráficos</h5>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="custom-control custom-switch mb-3">
                          <input type="checkbox" class="custom-control-input" id="mostrar_graficos" 
                                 name="mostrar_graficos" <?= $config['mostrar_graficos'] ? 'checked' : '' ?>>
                          <label class="custom-control-label" for="mostrar_graficos">
                            Incluir gráficos e visualizações
                          </label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                          <input type="checkbox" class="custom-control-input" id="mostrar_por_categoria" 
                                 name="mostrar_por_categoria" <?= $config['mostrar_por_categoria'] ? 'checked' : '' ?>>
                          <label class="custom-control-label" for="mostrar_por_categoria">
                            Exibir resumo por categoria de produto
                          </label>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="custom-control custom-switch mb-3">
                          <input type="checkbox" class="custom-control-input" id="mostrar_por_feirante" 
                                 name="mostrar_por_feirante" <?= $config['mostrar_por_feirante'] ? 'checked' : '' ?>>
                          <label class="custom-control-label" for="mostrar_por_feirante">
                            Exibir vendas por feirante
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <!-- ======================
               STEP 5: REVISÃO
            ====================== -->
            <div class="step-content" data-step="5">
              <div class="card">
                <div class="card-body">
                  <div class="config-section">
                    <h5><i class="ti-check mr-2"></i>Revisão Final</h5>
                    
                    <div class="alert alert-info">
                      <i class="ti-info-alt mr-2"></i>
                      Revise todas as informações antes de salvar. Você pode voltar às etapas anteriores para fazer alterações.
                    </div>

                    <div class="table-responsive">
                      <table class="table table-borderless">
                        <tr>
                          <td width="200"><strong>Nome da Feira:</strong></td>
                          <td id="review-titulo-feira"></td>
                        </tr>
                        <tr>
                          <td><strong>Subtítulo:</strong></td>
                          <td id="review-subtitulo"></td>
                        </tr>
                        <tr>
                          <td><strong>Localização:</strong></td>
                          <td id="review-localizacao"></td>
                        </tr>
                        <tr>
                          <td><strong>Secretaria:</strong></td>
                          <td id="review-secretaria"></td>
                        </tr>
                        <tr>
                          <td><strong>Logo Prefeitura:</strong></td>
                          <td id="review-logo-prefeitura"></td>
                        </tr>
                        <tr>
                          <td><strong>Logo Feira:</strong></td>
                          <td id="review-logo-feira"></td>
                        </tr>
                        <tr>
                          <td><strong>Introdução:</strong></td>
                          <td id="review-introducao"></td>
                        </tr>
                        <tr>
                          <td><strong>Conclusão:</strong></td>
                          <td id="review-conclusao"></td>
                        </tr>
                        <tr>
                          <td><strong>Assinatura:</strong></td>
                          <td id="review-assinatura"></td>
                        </tr>
                      </table>
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <!-- ======================
               NAVEGAÇÃO
            ====================== -->
            <div class="row mt-4">
              <div class="col-12">
                <div class="d-flex justify-content-between">
                  <button type="button" class="btn btn-outline-secondary btn-navigation" id="btnPrev" style="visibility: hidden;">
                    <i class="ti-arrow-left mr-1"></i> Anterior
                  </button>
                  
                  <div>
                    <button type="button" class="btn btn-primary btn-navigation" id="btnNext">
                      Próximo <i class="ti-arrow-right ml-1"></i>
                    </button>
                    <button type="submit" name="salvar_config" class="btn btn-success btn-navigation px-4" id="btnSave" style="display: none;">
                      <i class="ti-check mr-1"></i> Salvar Tudo
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </form>

        </div>

        <!-- ======================
       FOOTER
       ====================== -->
        <footer class="footer">
          <span class="text-muted">
            © <?= date('Y') ?> SIGRelatórios —
            <a href="https://www.lucascorrea.pro/" target="_blank">lucascorrea.pro</a>
          </span>
        </footer>

      </div>

    </div>
  </div>

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script>
    // Multi-step form
    let currentStep = 1;
    const totalSteps = 5;

    function updateProgress() {
      const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
      document.getElementById('progressFill').style.width = percentage + '%';

      // Update step items
      document.querySelectorAll('.step-item').forEach((item, index) => {
        const step = index + 1;
        if (step < currentStep) {
          item.classList.add('completed');
          item.classList.remove('active');
        } else if (step === currentStep) {
          item.classList.add('active');
          item.classList.remove('completed');
        } else {
          item.classList.remove('active', 'completed');
        }
      });

      // Update buttons
      document.getElementById('btnPrev').style.visibility = currentStep === 1 ? 'hidden' : 'visible';
      
      if (currentStep === totalSteps) {
        document.getElementById('btnNext').style.display = 'none';
        document.getElementById('btnSave').style.display = 'inline-block';
        updateReview();
      } else {
        document.getElementById('btnNext').style.display = 'inline-block';
        document.getElementById('btnSave').style.display = 'none';
      }
    }

    function showStep(step) {
      document.querySelectorAll('.step-content').forEach(content => {
        content.classList.remove('active');
      });
      
      const targetStep = document.querySelector(`.step-content[data-step="${step}"]`);
      if (targetStep) {
        targetStep.classList.add('active');
      }
      
      currentStep = step;
      updateProgress();
      window.scrollTo(0, 0);
    }

    document.getElementById('btnNext').addEventListener('click', () => {
      if (currentStep < totalSteps) {
        showStep(currentStep + 1);
      }
    });

    document.getElementById('btnPrev').addEventListener('click', () => {
      if (currentStep > 1) {
        showStep(currentStep - 1);
      }
    });

    // Click on step circles
    document.querySelectorAll('.step-item').forEach(item => {
      item.addEventListener('click', () => {
        const step = parseInt(item.dataset.step);
        showStep(step);
      });
    });

    // Upload handling
    function setupUpload(tipo) {
      const uploadArea = document.getElementById(`uploadArea${tipo}`);
      const fileInput = document.getElementById(`fileInput${tipo}`);
      const preview = document.getElementById(`preview${tipo}`);
      const urlInput = document.getElementById(`urlLogo${tipo}`);

      // Click to upload
      uploadArea.addEventListener('click', () => fileInput.click());

      // Drag & drop
      uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
      });

      uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
      });

      uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          handleFileUpload(files[0], tipo);
        }
      });

      // File input change
      fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
          handleFileUpload(e.target.files[0], tipo);
        }
      });
    }

    function handleFileUpload(file, tipo) {
      const formData = new FormData();
      formData.append('upload_logo', file);
      formData.append('tipo_logo', tipo.toLowerCase());

      const uploadArea = document.getElementById(`uploadArea${tipo}`);
      uploadArea.innerHTML = '<div class="loading-spinner"></div><p class="mt-3">Enviando...</p>';

      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById(`urlLogo${tipo}`).value = data.url;
          const preview = document.getElementById(`preview${tipo}`);
          preview.querySelector('img').src = data.url;
          preview.classList.add('show');
          
          uploadArea.innerHTML = `
            <i class="ti-check" style="color: #22c55e;"></i>
            <h6 style="color: #22c55e;">Upload concluído!</h6>
            <p class="text-muted mb-0">${data.filename}</p>
          `;
        } else {
          alert('Erro: ' + data.error);
          resetUploadArea(tipo);
        }
      })
      .catch(error => {
        alert('Erro ao fazer upload');
        resetUploadArea(tipo);
      });
    }

    function resetUploadArea(tipo) {
      const uploadArea = document.getElementById(`uploadArea${tipo}`);
      uploadArea.innerHTML = `
        <i class="ti-upload"></i>
        <h6>Arraste ou clique para fazer upload</h6>
        <p class="text-muted mb-0">JPG, PNG, GIF ou WebP (máx. 5MB)</p>
      `;
    }

    function removeImage(tipo) {
      const tipoCapitalized = tipo.charAt(0).toUpperCase() + tipo.slice(1);
      document.getElementById(`urlLogo${tipoCapitalized}`).value = '';
      document.getElementById(`preview${tipoCapitalized}`).classList.remove('show');
      resetUploadArea(tipoCapitalized);
    }

    // Setup uploads
    setupUpload('Prefeitura');
    setupUpload('Feira');

    // Update review
    function updateReview() {
      const tituloFeira = document.querySelector('[name="titulo_feira"]').value;
      const subtitulo = document.querySelector('[name="subtitulo_feira"]').value;
      const municipio = document.querySelector('[name="municipio"]').value;
      const estado = document.querySelector('[name="estado"]').value;
      const secretaria = document.querySelector('[name="secretaria"]').value;
      const logoPrefeitura = document.querySelector('[name="logotipo_prefeitura"]').value;
      const logoFeira = document.querySelector('[name="logotipo_feira"]').value;
      const incluirIntro = document.querySelector('[name="incluir_introducao"]').checked;
      const incluirConclusao = document.querySelector('[name="incluir_conclusao"]').checked;
      const assinaturaNome = document.querySelector('[name="assinatura_nome"]').value;
      const assinaturaCargo = document.querySelector('[name="assinatura_cargo"]').value;

      document.getElementById('review-titulo-feira').textContent = tituloFeira || '(não preenchido)';
      document.getElementById('review-subtitulo').textContent = subtitulo || '(não preenchido)';
      document.getElementById('review-localizacao').textContent = `${municipio} - ${estado}`;
      document.getElementById('review-secretaria').textContent = secretaria || '(não preenchido)';
      document.getElementById('review-logo-prefeitura').innerHTML = logoPrefeitura 
        ? '<span class="badge badge-success">✓ Configurado</span>' 
        : '<span class="badge badge-secondary">Não configurado</span>';
      document.getElementById('review-logo-feira').innerHTML = logoFeira 
        ? '<span class="badge badge-success">✓ Configurado</span>' 
        : '<span class="badge badge-secondary">Não configurado</span>';
      document.getElementById('review-introducao').innerHTML = incluirIntro 
        ? '<span class="badge badge-success">✓ Incluir</span>' 
        : '<span class="badge badge-secondary">Não incluir</span>';
      document.getElementById('review-conclusao').innerHTML = incluirConclusao 
        ? '<span class="badge badge-success">✓ Incluir</span>' 
        : '<span class="badge badge-secondary">Não incluir</span>';
      document.getElementById('review-assinatura').textContent = assinaturaNome 
        ? `${assinaturaNome} - ${assinaturaCargo}` 
        : '(não configurado)';
    }

    // Initialize
    updateProgress();
  </script>
</body>

</html>