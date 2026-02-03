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
   CARREGAR CONFIGURAÇÕES
====================== */
$config = [
  'titulo_feira' => 'Feira do Produtor Rural',
  'subtitulo_feira' => 'Francisco Lopes da Silva – "Folha"',
  'municipio' => 'Coari',
  'estado' => 'AM',
  'secretaria' => 'Secretaria de Desenvolvimento Rural e Econômico',
  'logotipo_prefeitura' => '',
  'logotipo_feira' => '',
  'incluir_introducao' => 1,
  'texto_introducao' => 'A Feira do Produtor Rural "{titulo_feira}" é um espaço de valorização da agricultura familiar e de comercialização de alimentos cultivados no município de {municipio}-{estado}.',
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
      AND table_name = 'relatorio_config'
  ");
  
  if ((int)$st->fetchColumn() > 0) {
    $st = $pdo->query("SELECT * FROM relatorio_config WHERE id = 1");
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
    $st = $pdo->query("SELECT COUNT(*) FROM relatorio_config WHERE id = 1");
    $existe = (int)$st->fetchColumn() > 0;

    if ($existe) {
      // UPDATE
      $sql = "
        UPDATE relatorio_config SET
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
        WHERE id = 1
      ";
    } else {
      // INSERT
      $sql = "
        INSERT INTO relatorio_config (
          id, titulo_feira, subtitulo_feira, municipio, estado, secretaria,
          logotipo_prefeitura, logotipo_feira, incluir_introducao, texto_introducao,
          incluir_produtos_comercializados, incluir_conclusao, texto_conclusao,
          assinatura_nome, assinatura_cargo, mostrar_graficos, mostrar_por_categoria,
          mostrar_por_feirante, produtos_detalhados
        ) VALUES (
          1, :titulo_feira, :subtitulo_feira, :municipio, :estado, :secretaria,
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
            <a href="../alternativa/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira Alternativa</span>
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
              </div>
              <hr>
            </div>
          </div>

          <!-- FORMULÁRIO -->
          <form method="POST" action="">
            
            <!-- ======================
               INFORMAÇÕES GERAIS
            ====================== -->
            <div class="config-section">
              <h5><i class="ti-info-alt mr-2"></i>Informações Gerais</h5>
              
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Nome da Feira *</label>
                  <input type="text" name="titulo_feira" class="form-control" 
                         value="<?= h($config['titulo_feira']) ?>" required>
                  <small class="form-text">Ex: Feira do Produtor Rural</small>
                </div>

                <div class="col-md-6 mb-3">
                  <label class="form-label">Subtítulo / Nome popular</label>
                  <input type="text" name="subtitulo_feira" class="form-control" 
                         value="<?= h($config['subtitulo_feira']) ?>">
                  <small class="form-text">Ex: Francisco Lopes da Silva – "Folha"</small>
                </div>
              </div>

              <div class="row">
                <div class="col-md-8 mb-3">
                  <label class="form-label">Secretaria / Órgão Responsável</label>
                  <input type="text" name="secretaria" class="form-control" 
                         value="<?= h($config['secretaria']) ?>">
                  <small class="form-text">Ex: Secretaria de Desenvolvimento Rural e Econômico</small>
                </div>

                <div class="col-md-3 mb-3">
                  <label class="form-label">Município *</label>
                  <input type="text" name="municipio" class="form-control" 
                         value="<?= h($config['municipio']) ?>" required>
                </div>

                <div class="col-md-1 mb-3">
                  <label class="form-label">UF *</label>
                  <input type="text" name="estado" class="form-control" 
                         value="<?= h($config['estado']) ?>" maxlength="2" required>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">URL do Logotipo da Prefeitura</label>
                  <input type="text" name="logotipo_prefeitura" class="form-control" 
                         value="<?= h($config['logotipo_prefeitura']) ?>" 
                         placeholder="https://exemplo.com/logo-prefeitura.png">
                  <small class="form-text">Opcional: imagem do cabeçalho do relatório</small>
                </div>

                <div class="col-md-6 mb-3">
                  <label class="form-label">URL do Logotipo da Feira</label>
                  <input type="text" name="logotipo_feira" class="form-control" 
                         value="<?= h($config['logotipo_feira']) ?>" 
                         placeholder="https://exemplo.com/logo-feira.png">
                  <small class="form-text">Opcional: imagem adicional do cabeçalho</small>
                </div>
              </div>
            </div>

            <!-- ======================
               INTRODUÇÃO
            ====================== -->
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

            <!-- ======================
               PRODUTOS COMERCIALIZADOS
            ====================== -->
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

            <!-- ======================
               CONTEÚDO DO RELATÓRIO
            ====================== -->
            <div class="config-section">
              <h5><i class="ti-layout mr-2"></i>Conteúdo e Visualização</h5>

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

            <!-- ======================
               CONCLUSÃO
            ====================== -->
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

            <!-- ======================
               ASSINATURA
            ====================== -->
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

            <!-- ======================
               BOTÕES
            ====================== -->
            <div class="row">
              <div class="col-12">
                <button type="submit" name="salvar_config" class="btn btn-primary btn-lg px-5">
                  <i class="ti-save mr-2"></i> Salvar Configurações
                </button>
                <a href="relatorioMensal.php" class="btn btn-outline-secondary btn-lg px-4 ml-2">
                  <i class="ti-eye mr-2"></i> Visualizar Relatório
                </a>
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

</body>

</html>