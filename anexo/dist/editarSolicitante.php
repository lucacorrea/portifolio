<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* ===== Helpers ===== */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function norm($v): string
{
    $s = trim((string) $v);
    if (function_exists('mb_strtolower'))
        return mb_strtolower($s, 'UTF-8');
    return strtolower($s);
}
function is_sel($a, $b): bool
{
    return norm($a) === norm($b);
}
function opt($v, $t = null, $selectedVal = null): string
{
    $t = $t ?? $v;
    $sel = ($selectedVal !== null && is_sel($v, $selectedVal)) ? ' selected' : '';
    return '<option value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
        . htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8') . '</option>';
}

/* ===== Conexão (deve definir $pdo) ===== */
$pdo = null;
try {
    require_once __DIR__ . "/assets/conexao.php"; // deve definir $pdo (PDO)
} catch (Throwable $e) {
    $pdo = null;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro crítico: Falha na conexão com o banco de dados.');location.href='index.php';</script>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Carrega bairros (tabela: bairros) */
$bairros = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome");
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $bairros = [];
}

/* CSRF simples */
if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();
if (empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = (string) $_SESSION['csrf_token'];

/* ===== Tipos de Ajuda (para o select) ===== */
$ajudasTipos = [];
try {
    $sql = "
    SELECT id, nome
    FROM ajudas_tipos
    WHERE status = 'Ativa'
      AND nome IS NOT NULL
      AND TRIM(nome) <> ''
    ORDER BY TRIM(nome) ASC
  ";
    $stmt = $pdo->query($sql);
    $ajudasTipos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $ajudasTipos = [];
}

/* ===== Consulta Solicitante (Edição) ===== */
$id = (int) ($_GET['id'] ?? 0);
$cpfGet = (string) ($_GET['cpf'] ?? '');

if (!$id && !$cpfGet) {
    echo "<script>alert('ID inválido.');location.href='pessoasCadastradas.php';</script>";
    exit;
}

$solicitante = [];
$familiares = [];
$documentos = [];

try {
    // 1) Solicitante
    if (!$id && $cpfGet !== '') {
        $cpfLimpo = preg_replace('/\D+/', '', $cpfGet) ?? '';
        if (strlen($cpfLimpo) === 11) {
            $stmtCPF = $pdo->prepare("SELECT * FROM solicitantes WHERE cpf = :cpf LIMIT 1");
            $stmtCPF->execute([':cpf' => $cpfLimpo]);
            $solicitante = $stmtCPF->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM solicitantes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $solicitante = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    if ($solicitante)
        $id = (int) $solicitante['id'];

    if (!$solicitante) {
        echo "<script>alert('Solicitante não encontrado.');location.href='pessoasCadastradas.php';</script>";
        exit;
    }

    // 2) Familiares
    $stmtFam = $pdo->prepare("SELECT * FROM familiares WHERE solicitante_id = :id ORDER BY id ASC");
    $stmtFam->execute([':id' => $id]);
    $familiares = $stmtFam->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3) Documentos
    $stmtDoc = $pdo->prepare("SELECT * FROM solicitante_documentos WHERE solicitante_id = :id ORDER BY created_at DESC, id DESC");
    $stmtDoc->execute([':id' => $id]);
    $documentos = $stmtDoc->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    echo "Erro: " . e($e->getMessage());
    exit;
}

$s = $solicitante;

// normalizações para controle de exibição (evita “Sim ” / “Outros ”)
$grupoTrad = trim((string) ($s['grupo_tradicional'] ?? ''));
$pcdVal = trim((string) ($s['pcd'] ?? 'Não'));
$bpcSel = trim((string) ($s['bpc'] ?? 'Não'));
$pbfSel = trim((string) ($s['pbf'] ?? 'Não'));
$benMunSel = trim((string) ($s['beneficio_municipal'] ?? 'Não'));
$benEstSel = trim((string) ($s['beneficio_estadual'] ?? 'Não'));

$sitImovel = trim((string) ($s['situacao_imovel'] ?? ''));
$tipoMor = trim((string) ($s['tipo_moradia'] ?? ''));
$abast = trim((string) ($s['abastecimento'] ?? ''));
$ilum = trim((string) ($s['iluminacao'] ?? ''));
$esg = trim((string) ($s['esgoto'] ?? ''));
$lixo = trim((string) ($s['lixo'] ?? ''));
$entorno = trim((string) ($s['entorno'] ?? ''));

$fotoAtual = trim((string) ($s['foto_path'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Solicitante - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

    <style>
        .hidden {
            display: none !important;
        }

        .required:after {
            content: " *";
            color: #dc3545;
        }

        .help {
            font-size: .85rem;
            color: #6b7280;
        }

        .form-section {
            border: 0;
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }

        .section-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: .75rem;
        }

        .table-sm td,
        .table-sm th {
            padding: .35rem .5rem;
        }

        .input-like-file .form-control[disabled] {
            background: #f8f9fa;
        }

        .readonly-clean[readonly] {
            background: #fff !important;
            opacity: 1 !important;
            cursor: default;
        }

        .form-card {
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            overflow: visible !important;
            /* Force visible for sticky to work */
        }

        .form-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #eef2f7;
        }

        /* ===== Stepper ===== */
        .stepper {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .5rem;
        }

        .step-btn {
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 999px;
            padding: .35rem .75rem;
            font-size: .85rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
            user-select: none;
        }

        .step-btn .num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #fff;
        }

        .step-btn.active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .step-btn.active .num {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #fff;
        }

        .step-btn.done {
            border-color: #22c55e;
        }

        .step-btn.done .num {
            border-color: #22c55e;
            background: #22c55e;
            color: #fff;
        }

        /* ===== Barra de ações (não corta mais) ===== */
        /* ===== Barra de ações (Fixed Bottom) ===== */
        .sticky-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
        }

        /* Adjustments for sidebar (optional, if you want to avoid overlap) */
        @media (min-width: 1200px) {
            .sticky-actions {
                padding-left: 320px;
                /* Sidebar width approx + padding */
            }
        }

        .sticky-actions .left,
        .sticky-actions .right {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .sticky-actions .right {
            justify-content: flex-end;
        }

        /* ===== Câmeras ===== */
        #modalCamera .modal-dialog {
            max-width: 820px
        }

        #modalCamera .modal-body {
            background: #f8fafc
        }

        .cam-wrap {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            padding: 16px
        }

        .cam-frame {
            width: 100%;
            max-width: 640px;
            height: 480px;
            margin: 0 auto;
            background: #000;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden
        }

        #camVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            background: #000
        }

        .cam-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
            margin-top: 10px
        }

        #capturaPreview {
            width: 100%;
            max-width: 360px;
            margin: 10px auto 0;
            display: none;
            border: 1px solid #e5e7eb;
            border-radius: 10px
        }

        @media (max-width:767.98px) {
            .cam-frame {
                max-width: 100%;
                height: calc((100vw - 48px)*0.75)
            }

            .sticky-actions {
                padding: .75rem;
                flex-direction: row;
                /* Keep row on mobile for space-between */
            }

            .sticky-actions .left,
            .sticky-actions .right {
                width: auto;
            }

            .sticky-actions .btn {
                padding: .375rem .75rem;
                font-size: .9rem;
            }
        }

        .kv-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px
        }

        @media (max-width:767.98px) {
            .kv-grid {
                grid-template-columns: 1fr
            }
        }

        .kv {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
            background: #fff
        }

        .kv .kv-label {
            font-size: .775rem;
            color: #6b7280;
            margin-bottom: .25rem;
            text-transform: uppercase;
            letter-spacing: .02em
        }

        .kv .kv-value {
            font-weight: 600;
            color: #0f172a;
            word-break: break-word
        }

        .btn-icon-sm {
            padding: .15rem .4rem;
            line-height: 1
        }

        #tblFamilia {
            white-space: nowrap
        }

        .form-control:valid:not([required]),
        .form-select:valid:not([required]) {
            border-color: var(--bs-border-color);
            background-image: none;
            box-shadow: none
        }

        .thumb-foto {
            max-width: 180px;
            width: 100%;
            height: auto;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            display: block;
        }

        .box-mini {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 10px;
        }

        .box-mini .small-muted {
            color: #6b7280;
            font-size: .85rem;
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="dashboard.php"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo"
                                    style="height:48px"></a>
                        </div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i
                                    class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- Menu ANEXO -->
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item">
                            <a href="dashboard.php" class="sidebar-link"><i
                                    class="bi bi-grid-fill"></i><span>Dashboard</span></a>
                        </li>

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link"><i
                                    class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="pessoasCadastradas.php">Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
                                <li class="submenu-item active"><a href="#">Editar Cadastro</a></li>
                            </ul>
                        </li>

                        <?php
                        $role = $_SESSION['user_role'] ?? '';

                        if ($role === 'prefeito' || $role === 'secretario'):
                        ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-person-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item">
                                        <a href="usuariosPermitidos.php">Permitidos</a>
                                    </li>
                                    <li class="submenu-item">
                                        <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="sidebar-item">
                            <a href="../../gpsemas/index.php" class="sidebar-link"><i
                                    class="bi bi-map-fill"></i><span>Rastreamento</span></a>
                        </li>
                        <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'secretario'): ?>
                            <li class="sidebar-item">
                                <a href="../admin/index.php" class="sidebar-link" target="_blank" rel="noopener">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    <span>Administrador</span>
                                </a>
                            </li>
                        <?php endif; ?>


                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link"><i
                                    class="bi bi-box-arrow-right"></i><span>Sair</span></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <h3>Editar Solicitante #<?= (int) $id ?></h3>
                        </div>
                        <div class="col-12 col-md-6">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Solicitantes</a></li>
                                    <li class="breadcrumb-item active">Editar Solicitante</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <div class="card form-card mb-4" id="cardForm">
                    <div class="card-header">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <strong id="stepTitle">I. DADOS DE IDENTIFICAÇÃO</strong>
                            <span class="text-muted small">Edição guiada (4 etapas)</span>
                        </div>

                        <div class="stepper" id="stepper">
                            <button type="button" class="step-btn" data-step="1"><span class="num">1</span>
                                Identificação</button>
                            <button type="button" class="step-btn" data-step="2"><span class="num">2</span>
                                Família</button>
                            <button type="button" class="step-btn" data-step="3"><span class="num">3</span>
                                Habitação</button>
                            <button type="button" class="step-btn" data-step="4"><span class="num">4</span>
                                Resumo</button>
                        </div>
                    </div>

                    <div class="card-body">
                        <form id="formSolicitante" action="dados/atualizarSolicitante.php" method="POST"
                            enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <input type="hidden" name="foto_base64" id="foto_base64">

                            <!-- ===================== ETAPA 1 ===================== -->
                            <section class="form-section" data-form-step="1">
                                <div class="section-title">I. Identificação do Solicitante</div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nome Completo</label>
                                        <input type="text" class="form-control" name="nome"
                                            value="<?= e($s['nome'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Informe o nome completo.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">NIS</label>
                                        <input type="text" class="form-control" name="nis"
                                            value="<?= e($s['nis'] ?? '') ?>" inputmode="numeric"
                                            placeholder="Somente números">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required">CPF</label>
                                        <input type="text" class="form-control" name="cpf" id="cpf"
                                            value="<?= e($s['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                                        <div class="invalid-feedback">Informe um CPF válido.</div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label required">RG</label>
                                        <input type="text" class="form-control" name="rg"
                                            value="<?= e($s['rg'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Informe o RG.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Emissão do RG</label>
                                        <input type="date" class="form-control" name="rg_emissao"
                                            value="<?= e($s['rg_emissao'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">UF (RG)</label>
                                        <select class="form-select" name="rg_uf">
                                            <option value="">Selecione…</option>
                                            <?php
                                            $ufs = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
                                            foreach ($ufs as $uf)
                                                echo opt($uf, null, $s['rg_uf'] ?? '');
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-control" name="data_nascimento"
                                            value="<?= e($s['data_nascimento'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Naturalidade (Cidade/UF)</label>
                                        <input type="text" class="form-control" name="naturalidade"
                                            value="<?= e($s['naturalidade'] ?? '') ?>" placeholder="Ex.: Coari/AM">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gênero</label>
                                        <select name="genero" class="form-select">
                                            <option value="">Selecione…</option>
                                            <?= opt('Feminino', null, $s['genero'] ?? '') . opt('Masculino', null, $s['genero'] ?? '') . opt('Outro', null, $s['genero'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Estado Civil</label>
                                        <select name="estado_civil" class="form-select">
                                            <option value="">Selecione…</option>
                                            <?= opt('Casado(a)', null, $s['estado_civil'] ?? '') . opt('Solteiro(a)', null, $s['estado_civil'] ?? '') . opt('Viúvo(a)', null, $s['estado_civil'] ?? '') . opt('União Estável', null, $s['estado_civil'] ?? '') . opt('Outros', null, $s['estado_civil'] ?? '') ?>
                                        </select>
                                    </div>

                                    <!-- Hora do Cadastro (origem) -->
                                    <div class="col-md-3">
                                        <label class="form-label">Hora do Cadastro</label>
                                        <input type="text" class="form-control readonly-clean"
                                            value="<?= e($s['hora_cadastro'] ?? '') ?>" readonly>
                                    </div>
                                    <!-- Responsável (origem) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Responsável (Cadastro Original)</label>
                                        <input type="text" class="form-control readonly-clean"
                                            value="<?= e($s['responsavel'] ?? '') ?>" readonly>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required">Telefone</label>
                                        <input type="tel" class="form-control" name="telefone" id="telefone"
                                            value="<?= e($s['telefone'] ?? '') ?>" placeholder="(00) 00000-0000"
                                            required>
                                        <div class="invalid-feedback">Informe um telefone válido (10 ou 11 dígitos).
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required">Endereço</label>
                                        <input type="text" class="form-control" name="endereco"
                                            value="<?= e($s['endereco'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Informe o endereço.</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label required">Nº</label>
                                        <input type="text" class="form-control" name="numero"
                                            value="<?= e($s['numero'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Informe o número.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required">Bairro</label>
                                        <select class="form-select" name="bairro_id" required>
                                            <option value="">Selecione…</option>
                                            <?php foreach ($bairros as $b): ?>
                                                <option
                                                    value="<?= htmlspecialchars((string) $b['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= ((string) $b['id'] === (string) ($s['bairro_id'] ?? '')) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $b['nome'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Selecione o bairro.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Complemento</label>
                                        <input type="text" class="form-control" name="complemento"
                                            value="<?= e($s['complemento'] ?? '') ?>" placeholder="Casa, bloco...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ponto de Referência</label>
                                        <input type="text" class="form-control" name="referencia"
                                            value="<?= e($s['referencia'] ?? '') ?>" placeholder="Próximo a...">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Nacionalidade</label>
                                        <input type="text" class="form-control" name="nacionalidade"
                                            value="<?= e($s['nacionalidade'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Tempo de Moradia</label>
                                        <div class="input-group">
                                            <input type="number" min="0" class="form-control" name="tempo_anos"
                                                value="<?= e($s['tempo_anos'] ?? '') ?>" placeholder="Anos">
                                            <span class="input-group-text">anos</span>
                                            <input type="number" min="0" class="form-control" name="tempo_meses"
                                                value="<?= e($s['tempo_meses'] ?? '') ?>" placeholder="Meses">
                                            <span class="input-group-text">meses</span>
                                        </div>
                                    </div>

                                    <!-- Grupos Tradicionais -->
                                    <div class="col-md-6">
                                        <label class="form-label">Grupos Tradicionais</label>
                                        <select class="form-select" name="grupo_tradicional" id="grupo_tradicional">
                                            <option value="">Selecione…</option>
                                            <?= opt('Indígena', null, $s['grupo_tradicional'] ?? '') . opt('Quilombola', null, $s['grupo_tradicional'] ?? '') . opt('Cigano', null, $s['grupo_tradicional'] ?? '') . opt('Ribeirinho', null, $s['grupo_tradicional'] ?? '') . opt('Extrativista', null, $s['grupo_tradicional'] ?? '') . opt('Outros', null, $s['grupo_tradicional'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 <?= is_sel($grupoTrad, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_grupo_outros">
                                        <label class="form-label">Grupos (Outros)</label>
                                        <input type="text" class="form-control" name="grupo_outros" id="grupo_outros"
                                            value="<?= e($s['grupo_outros'] ?? '') ?>" placeholder="Descreva">
                                    </div>

                                    <!-- PCD / BPC / PBF e Benefícios -->
                                    <div class="col-md-6">
                                        <label class="form-label">PCD?</label>
                                        <select class="form-select" name="pcd" id="pcd">
                                            <?= opt('Não', null, $s['pcd'] ?? 'Não') . opt('Sim', null, $s['pcd'] ?? 'Não') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($pcdVal, 'Sim') ? '' : 'd-none' ?>"
                                        id="wrap_pcd_tipo">
                                        <label class="form-label">Se PCD, qual?</label>
                                        <input type="text" class="form-control" name="pcd_tipo" id="pcd_tipo"
                                            value="<?= e($s['pcd_tipo'] ?? '') ?>" placeholder="Deficiência...">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">BPC?</label>
                                        <div class="input-group">
                                            <select class="form-select" name="bpc" id="bpc">
                                                <?= opt('Não', null, $s['bpc'] ?? 'Não') . opt('Sim', null, $s['bpc'] ?? 'Não') ?>
                                            </select>
                                            <span class="input-group-text">Valor R$</span>
                                            <input type="text"
                                                class="form-control moeda <?= is_sel($bpcSel, 'Sim') ? '' : 'd-none' ?>"
                                                name="bpc_valor" id="bpc_valor" value="<?= e($s['bpc_valor'] ?? '') ?>"
                                                placeholder="0,00">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Bolsa Família (PBF)?</label>
                                        <div class="input-group">
                                            <select class="form-select" name="pbf" id="pbf">
                                                <?= opt('Não', null, $s['pbf'] ?? 'Não') . opt('Sim', null, $s['pbf'] ?? 'Não') ?>
                                            </select>
                                            <span class="input-group-text">Valor R$</span>
                                            <input type="text"
                                                class="form-control moeda <?= is_sel($pbfSel, 'Sim') ? '' : 'd-none' ?>"
                                                name="pbf_valor" id="pbf_valor" value="<?= e($s['pbf_valor'] ?? '') ?>"
                                                placeholder="0,00">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Benefícios</label>
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <span class="input-group-text">Municipal</span>
                                                    <select class="form-select" name="beneficio_municipal"
                                                        id="beneficio_municipal">
                                                        <?= opt('Não', null, $s['beneficio_municipal'] ?? 'Não') . opt('Sim', null, $s['beneficio_municipal'] ?? 'Não') ?>
                                                    </select>
                                                    <span class="input-group-text">Valor R$</span>
                                                    <input type="text"
                                                        class="form-control moeda <?= is_sel($benMunSel, 'Sim') ? '' : 'd-none' ?>"
                                                        name="beneficio_municipal_valor" id="beneficio_municipal_valor"
                                                        value="<?= e($s['beneficio_municipal_valor'] ?? '') ?>"
                                                        placeholder="0,00">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <span class="input-group-text">Estadual</span>
                                                    <select class="form-select" name="beneficio_estadual"
                                                        id="beneficio_estadual">
                                                        <?= opt('Não', null, $s['beneficio_estadual'] ?? 'Não') . opt('Sim', null, $s['beneficio_estadual'] ?? 'Não') ?>
                                                    </select>
                                                    <span class="input-group-text">Valor R$</span>
                                                    <input type="text"
                                                        class="form-control moeda <?= is_sel($benEstSel, 'Sim') ? '' : 'd-none' ?>"
                                                        name="beneficio_estadual_valor" id="beneficio_estadual_valor"
                                                        value="<?= e($s['beneficio_estadual_valor'] ?? '') ?>"
                                                        placeholder="0,00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Renda Mensal -->
                                    <div class="col-md-6">
                                        <label class="form-label">Renda Mensal</label>
                                        <div class="input-group">
                                            <select class="form-select" name="renda_mensal_faixa"
                                                id="renda_mensal_faixa">
                                                <option value="">Selecione…</option>
                                                <?= opt('Inferior a 1 salário mínimo', null, $s['renda_mensal_faixa'] ?? '') . opt('1 salário mínimo', null, $s['renda_mensal_faixa'] ?? '') . opt('2 salários mínimos', null, $s['renda_mensal_faixa'] ?? '') . opt('Outros', null, $s['renda_mensal_faixa'] ?? '') ?>
                                            </select>
                                            <span
                                                class="input-group-text <?= is_sel(($s['renda_mensal_faixa'] ?? ''), 'Outros') ? '' : 'd-none' ?>"
                                                id="lbl_renda_outros">Outros</span>
                                            <input type="text"
                                                class="form-control <?= is_sel(($s['renda_mensal_faixa'] ?? ''), 'Outros') ? '' : 'd-none' ?>"
                                                name="renda_mensal_outros" id="renda_mensal_outros"
                                                value="<?= e($s['renda_mensal_outros'] ?? '') ?>"
                                                placeholder="Descreva">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Situação de Trabalho</label>
                                        <select class="form-select" name="trabalho">
                                            <option value="">Selecione…</option>
                                            <?= opt('Empregado(a)', null, $s['trabalho'] ?? '') . opt('Desempregado(a)', null, $s['trabalho'] ?? '') . opt('Autônomo(a)', null, $s['trabalho'] ?? '') . opt('Aposentado(a)/Pensionista', null, $s['trabalho'] ?? '') . opt('Outros', null, $s['trabalho'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Renda Individual (R$)</label>
                                        <input type="text" class="form-control moeda" name="renda_individual"
                                            value="<?= e($s['renda_individual'] ?? '') ?>" placeholder="0,00">
                                    </div>

                                    <!-- FOTO (ATUAL + SUBSTITUIR/REMOVER) -->
                                    <div class="col-12">
                                        <label class="form-label">Foto do Solicitante</label>

                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <div class="input-group input-like-file">
                                                    <!-- Input file oculto -->
                                                    <input type="file" id="foto_upload_input" accept="image/*" class="d-none">

                                                    <input type="text" class="form-control" id="foto_filename"
                                                        value="<?= $fotoAtual ? 'Foto atual cadastrada' : 'Nenhuma foto selecionada' ?>"
                                                        disabled>

                                                    <!-- Botão Arquivo -->
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        onclick="document.getElementById('foto_upload_input').click()"
                                                        title="Escolher arquivo da galeria/computador">
                                                        <i class="bi bi-folder2-open"></i> Arquivo
                                                    </button>

                                                    <!-- Botão Câmera -->
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalCamera"
                                                        title="Tirar foto com a câmera">
                                                        <i class="bi bi-camera"></i> Câmera
                                                    </button>
                                                </div>
                                                <div class="help">Abrirá a câmera (traseira por padrão). Você pode
                                                    alternar para a frontal.</div>
                                                <div class="help mt-1 d-none" id="hintNovaFoto"><b>Nova foto
                                                        capturada</b> substituirá a foto atual ao salvar.</div>
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <div class="box-mini">
                                                    <div class="small-muted mb-2"><b>Foto atual</b></div>

                                                    <?php if ($fotoAtual): ?>
                                                        <a href="<?= e($fotoAtual) ?>" target="_blank" rel="noopener"
                                                            title="Abrir foto em nova aba">
                                                            <img src="<?= e($fotoAtual) ?>" class="thumb-foto"
                                                                alt="Foto atual do solicitante">
                                                        </a>

                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" value="1"
                                                                id="foto_remover" name="foto_remover">
                                                            <label class="form-check-label" for="foto_remover">Remover foto
                                                                atual</label>
                                                        </div>
                                                        <div class="help">Se você marcar para remover e não capturar outra,
                                                            a foto ficará vazia.</div>
                                                    <?php else: ?>
                                                        <div class="text-muted small">Nenhuma foto cadastrada.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DOCUMENTOS (ATUAIS + NOVOS + REMOVER) -->
                                    <div class="col-12">
                                        <label class="form-label">Documentos do Solicitante</label>

                                        <div class="box-mini">
                                            <div class="small-muted"><b>Documentos já anexados</b> (marque para remover)
                                            </div>

                                            <?php if (empty($documentos)): ?>
                                                <div class="text-muted small mt-2">Nenhum documento anexado.</div>
                                            <?php else: ?>
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm align-middle">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Arquivo</th>
                                                                <th style="width:120px" class="text-center">Remover?</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($documentos as $doc): ?>
                                                                <?php
                                                                $docId = (int) ($doc['id'] ?? 0);
                                                                $nome = (string) ($doc['original_name'] ?? 'arquivo');
                                                                $path = (string) ($doc['arquivo_path'] ?? '#');
                                                                $mime = (string) ($doc['mime_type'] ?? '');
                                                                $size = (int) ($doc['size_bytes'] ?? 0);
                                                                $sizeMb = $size > 0 ? number_format($size / 1024 / 1024, 2, ',', '.') . ' MB' : '';
                                                                ?>
                                                                <tr>
                                                                    <td>
                                                                        <a href="<?= e($path) ?>" target="_blank"
                                                                            rel="noopener"><?= e($nome) ?></a>
                                                                        <div class="text-muted small"><?= e($mime) ?>
                                                                            <?= $sizeMb ? '• ' . e($sizeMb) : '' ?>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <input type="checkbox" class="form-check-input"
                                                                            name="docs_remover[]" value="<?= $docId ?>">
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-3">
                                            <label class="form-label">Adicionar novos documentos
                                                (PDF/Word/Excel/Imagens)</label>
                                            <div class="input-group">
                                                <input type="file" class="form-control" name="documentos[]"
                                                    id="documentos" multiple
                                                    accept=".pdf,.doc,.docx,.odt,.rtf,.xls,.xlsx,.jpg,.jpeg,.png">
                                                <button type="button" class="btn btn-primary" id="btnOpenScan"
                                                    title="Escanear documentos com a câmera">
                                                    <i class="bi bi-camera-fill"></i> <span
                                                        class="d-none d-sm-inline">Escanear</span>
                                                </button>
                                            </div>
                                            <div
                                                class="d-flex flex-wrap justify-content-between align-items-center mt-1 gap-2">
                                                <div class="help m-0">Tamanho máx. por arquivo: 10MB.</div>
                                                <div id="scanStatusText" class="text-success fw-bold small d-none">
                                                </div>
                                            </div>

                                            <div id="docs_novos_list" class="small mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- ===================== ETAPA 2 ===================== -->
                            <section class="form-section hidden" data-form-step="2">
                                <div class="section-title">II. Dados do Cônjuge</div>

                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Nome</label><input type="text"
                                            class="form-control" name="conj_nome"
                                            value="<?= e($s['conj_nome'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">NIS</label><input type="text"
                                            class="form-control" name="conj_nis" value="<?= e($s['conj_nis'] ?? '') ?>"
                                            inputmode="numeric"></div>
                                    <div class="col-md-3"><label class="form-label">CPF</label><input type="text"
                                            class="form-control" name="conj_cpf" id="conj_cpf"
                                            value="<?= e($s['conj_cpf'] ?? '') ?>" placeholder="000.000.000-00"></div>
                                    <div class="col-md-3"><label class="form-label">RG</label><input type="text"
                                            class="form-control" name="conj_rg" value="<?= e($s['conj_rg'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3"><label class="form-label">Data de Nasc.</label><input
                                            type="date" class="form-control" name="conj_nasc"
                                            value="<?= e($s['conj_nasc'] ?? '') ?>"></div>

                                    <div class="col-md-3">
                                        <label class="form-label">Gênero</label>
                                        <select class="form-select" name="conj_genero">
                                            <option value="">Selecione…</option>
                                            <?= opt('Feminino', null, $s['conj_genero'] ?? '') . opt('Masculino', null, $s['conj_genero'] ?? '') . opt('Outro', null, $s['conj_genero'] ?? '') ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3"><label class="form-label">Nacionalidade</label><input
                                            type="text" class="form-control" name="conj_nacionalidade"
                                            value="<?= e($s['conj_nacionalidade'] ?? '') ?>"></div>
                                    <div class="col-md-6"><label class="form-label">Naturalidade
                                            (Cidade/UF)</label><input type="text" class="form-control"
                                            name="conj_naturalidade" value="<?= e($s['conj_naturalidade'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Situação de Trabalho</label>
                                        <select class="form-select" name="conj_trabalho">
                                            <option value="">Selecione…</option>
                                            <?= opt('Empregado(a)', null, $s['conj_trabalho'] ?? '') . opt('Desempregado(a)', null, $s['conj_trabalho'] ?? '') . opt('Autônomo(a)', null, $s['conj_trabalho'] ?? '') . opt('Aposentado(a)/Pensionista', null, $s['conj_trabalho'] ?? '') . opt('Outros', null, $s['conj_trabalho'] ?? '') ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6"><label class="form-label">Renda (R$)</label><input type="text"
                                            class="form-control moeda" name="conj_renda"
                                            value="<?= e($s['conj_renda'] ?? '') ?>" placeholder="0,00"></div>

                                    <div class="col-md-3">
                                        <label class="form-label">Pessoa com Deficiência?</label>
                                        <select class="form-select"
                                            name="conj_pcd"><?= opt('Não', null, $s['conj_pcd'] ?? 'Não') . opt('Sim', null, $s['conj_pcd'] ?? 'Não') ?></select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">BPC?</label>
                                        <select class="form-select" name="conj_bpc" id="conj_bpc">
                                            <?= opt('Não', null, $s['conj_bpc'] ?? 'Não') . opt('Sim', null, $s['conj_bpc'] ?? 'Não') ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4 <?= is_sel(($s['conj_bpc'] ?? ''), 'Sim') ? '' : 'd-none' ?>"
                                        id="wrap_conj_bpc_valor">
                                        <label class="form-label">Valor do BPC (R$)</label>
                                        <input type="text" class="form-control moeda" name="conj_bpc_valor"
                                            id="conj_bpc_valor" value="<?= e($s['conj_bpc_valor'] ?? '') ?>"
                                            placeholder="0,00">
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="section-title">III. Composição Familiar</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle text-nowrap" id="tblFamilia">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width:180px">Nome</th>
                                                <th style="min-width:140px">Data Nasc.</th>
                                                <th style="min-width:140px">Parentesco</th>
                                                <th style="min-width:160px">Escolaridade</th>
                                                <th style="min-width:180px">Observação</th>
                                                <th style="width:40px"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                    <button type="button" class="btn btn-outline-secondary" id="btnAddMembro">
                                        <i class="bi bi-plus-circle"></i> Adicionar membro
                                    </button>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-3"><label class="form-label">Total de Membros na
                                            Residência</label><input type="number" class="form-control"
                                            name="total_moradores" min="1"
                                            value="<?= e($s['total_moradores'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Total de Famílias na
                                            Residência</label><input type="number" class="form-control"
                                            name="total_familias" min="1" value="<?= e($s['total_familias'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Pessoas com Deficiência</label>
                                        <select class="form-select" name="pcd_residencia" id="pcd_residencia">
                                            <?= opt('Não', null, $s['pcd_residencia'] ?? 'Não') . opt('Sim', null, $s['pcd_residencia'] ?? 'Não') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label">Qtde PCD</label><input type="number"
                                            class="form-control" name="total_pcd" min="0"
                                            value="<?= e($s['total_pcd'] ?? '') ?>"></div>

                                    <div class="col-md-3"><label class="form-label">Renda Familiar (R$)</label><input
                                            type="text" class="form-control moeda" name="renda_familiar"
                                            value="<?= e($s['renda_familiar'] ?? '') ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Total de Rendimentos da Família
                                            (R$)</label><input type="text" class="form-control moeda"
                                            name="total_rendimentos" value="<?= e($s['total_rendimentos'] ?? '') ?>">
                                    </div>
                                    <div class="col-12"><label class="form-label">Tipificação</label><input type="text"
                                            class="form-control" name="tipificacao"
                                            value="<?= e($s['tipificacao'] ?? '') ?>"
                                            placeholder="Ex.: Vulnerabilidade social..."></div>
                                </div>
                            </section>

                            <!-- ===================== ETAPA 3 ===================== -->
                            <section class="form-section hidden" data-form-step="3">
                                <div class="section-title">IV. Condições Habitacionais</div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Situação do Imóvel</label>
                                        <select class="form-select" name="situacao_imovel" id="situacao_imovel">
                                            <option value="">Selecione…</option>
                                            <?= opt('Reside com os pais', 'Reside com os pais', $s['situacao_imovel'] ?? '') . opt('Próprio', null, $s['situacao_imovel'] ?? '') . opt('Alugado', null, $s['situacao_imovel'] ?? '') . opt('Cedido', null, $s['situacao_imovel'] ?? '') . opt('Ocupação', null, $s['situacao_imovel'] ?? '') . opt('Financiado', null, $s['situacao_imovel'] ?? '') . opt('Outros', null, $s['situacao_imovel'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= (is_sel($sitImovel, 'Alugado') || is_sel($sitImovel, 'Financiado')) ? '' : 'd-none' ?>"
                                        id="wrap_situacao_valor">
                                        <label class="form-label">Aluguel – Valor (R$)</label>
                                        <input type="text" class="form-control moeda" name="situacao_imovel_valor"
                                            id="situacao_imovel_valor"
                                            value="<?= e($s['situacao_imovel_valor'] ?? '') ?>" placeholder="0,00">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Tipo da Moradia</label>
                                        <select class="form-select" name="tipo_moradia" id="tipo_moradia">
                                            <option value="">Selecione…</option>
                                            <?= opt('Alvenaria', null, $s['tipo_moradia'] ?? '') . opt('Madeira', null, $s['tipo_moradia'] ?? '') . opt('Mista', null, $s['tipo_moradia'] ?? '') . opt('Outros', null, $s['tipo_moradia'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($tipoMor, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_tipo_moradia_outros">
                                        <label class="form-label">Tipo da Moradia (Outros)</label>
                                        <input type="text" class="form-control" name="tipo_moradia_outros"
                                            id="tipo_moradia_outros" value="<?= e($s['tipo_moradia_outros'] ?? '') ?>"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Abastecimento de Água</label>
                                        <select class="form-select" name="abastecimento" id="abastecimento">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rede Pública', null, $s['abastecimento'] ?? '') . opt('Poço', null, $s['abastecimento'] ?? '') . opt('Outros', null, $s['abastecimento'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($abast, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_abastecimento_outros">
                                        <label class="form-label">Abastecimento (Outros)</label>
                                        <input type="text" class="form-control" name="abastecimento_outros"
                                            id="abastecimento_outros" value="<?= e($s['abastecimento_outros'] ?? '') ?>"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Iluminação</label>
                                        <select class="form-select" name="iluminacao" id="iluminacao">
                                            <option value="">Selecione…</option>
                                            <?= opt('Próprio', null, $s['iluminacao'] ?? '') . opt('Comunitário', null, $s['iluminacao'] ?? '') . opt('Sem', null, $s['iluminacao'] ?? '') . opt('Lampião', null, $s['iluminacao'] ?? '') . opt('Outros', null, $s['iluminacao'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($ilum, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_iluminacao_outros">
                                        <label class="form-label">Iluminação (Outros)</label>
                                        <input type="text" class="form-control" name="iluminacao_outros"
                                            id="iluminacao_outros" value="<?= e($s['iluminacao_outros'] ?? '') ?>"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Esgotamento Sanitário</label>
                                        <select class="form-select" name="esgoto" id="esgoto">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rede Pública', null, $s['esgoto'] ?? '') . opt('Fossa Rudimentar', null, $s['esgoto'] ?? '') . opt('Fossa Séptica', null, $s['esgoto'] ?? '') . opt('Céu Aberto', null, $s['esgoto'] ?? '') . opt('Outros', null, $s['esgoto'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($esg, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_esgoto_outros">
                                        <label class="form-label">Esgoto (Outros)</label>
                                        <input type="text" class="form-control" name="esgoto_outros" id="esgoto_outros"
                                            value="<?= e($s['esgoto_outros'] ?? '') ?>" placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Coleta de Lixo</label>
                                        <select class="form-select" name="lixo" id="lixo">
                                            <option value="">Selecione…</option>
                                            <?= opt('Coletado', null, $s['lixo'] ?? '') . opt('Queimado', null, $s['lixo'] ?? '') . opt('Enterrado', null, $s['lixo'] ?? '') . opt('Céu Aberto', null, $s['lixo'] ?? '') . opt('Outros', null, $s['lixo'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 <?= is_sel($lixo, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_lixo_outros">
                                        <label class="form-label">Lixo (Outros)</label>
                                        <input type="text" class="form-control" name="lixo_outros" id="lixo_outros"
                                            value="<?= e($s['lixo_outros'] ?? '') ?>" placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Características do Entorno</label>
                                        <select class="form-select" name="entorno" id="entorno">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rua Pavimentada', null, $s['entorno'] ?? '') . opt('Rua não Pavimentada', null, $s['entorno'] ?? '') . opt('Às Margens de Igarapé', null, $s['entorno'] ?? '') . opt('Barranco', null, $s['entorno'] ?? '') . opt('Invasão', null, $s['entorno'] ?? '') . opt('Outros', null, $s['entorno'] ?? '') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8 <?= is_sel($entorno, 'Outros') ? '' : 'd-none' ?>"
                                        id="wrap_entorno_outros">
                                        <label class="form-label">Características do Entorno (Outros)</label>
                                        <input type="text" class="form-control" name="entorno_outros"
                                            id="entorno_outros" value="<?= e($s['entorno_outros'] ?? '') ?>"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-12">
                                        <label for="categoria_entrevista" class="form-label">Tipo de Ajuda /
                                            Categoria</label>
                                        <select name="categoria_entrevista" id="categoria_entrevista"
                                            class="form-control">
                                            <option value="">Selecione...</option>
                                            <?php foreach ($ajudasTipos as $r): ?>
                                                <?php
                                                $idT = (string) ($r['id'] ?? '');
                                                $nomeT = trim((string) ($r['nome'] ?? ''));
                                                if ($idT === '' || $nomeT === '')
                                                    continue;
                                                // Verifica seleção
                                                $selT = ((string) ($s['ajuda_tipo_id'] ?? '') === $idT) ? 'selected' : '';
                                                ?>
                                                <option value="<?= e($idT) ?>" <?= $selT ?>><?= e($nomeT) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Resumo do Caso (relato do atendimento)</label>
                                        <textarea class="form-control" name="resumo_caso" rows="4"
                                            placeholder="Descreva o caso, necessidade, providências…"><?= e($s['resumo_caso'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </section>

                            <!-- ===================== ETAPA 4 ===================== -->
                            <section class="form-section hidden" data-form-step="4">
                                <div id="boxResumo" class="bg-light border rounded p-3"></div>

                                <div class="form-check mt-3" style="text-align: justify !important;">
                                    <input class="form-check-input" type="checkbox" value="1" id="chkConfirm" required>
                                    <label class="form-check-label" for="chkConfirm">
                                        Declaro, para os devidos fins, que as informações prestadas são verdadeiras e
                                        autorizo o ANEXO a utilizá-las para fins de atendimento socioassistencial,
                                        conforme a legislação vigente.
                                    </label>
                                    <div class="invalid-feedback">É necessário concordar com a declaração para enviar.
                                    </div>
                                </div>
                            </section>

                            <!-- Spacer para não esconder conteúdo atrás da barra fixa -->
                            <div style="height: 80px;"></div>

                            <!-- ===== Barra de ações fixa ===== -->
                            <div class="sticky-actions">
                                <div class="left">
                                    <button type="button" class="btn btn-light" id="btnPrev"><i
                                            class="bi bi-arrow-left"></i> Voltar</button>
                                </div>
                                <div class="right">
                                    <button type="button" class="btn btn-primary" id="btnNext">Próximo <i
                                            class="bi bi-arrow-right"></i></button>
                                    <button type="button" class="btn btn-primary d-none" id="btnReview"><i
                                            class="bi bi-card-checklist"></i> Avançar para Resumo</button>
                                    <button type="submit" class="btn btn-success d-none" id="btnSave"><i
                                            class="bi bi-check2-circle"></i> Salvar Alterações</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal
                                de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = (new Date()).getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- ===== MODAL SCANNER DE DOCUMENTOS ===== -->
    <div class="modal fade" id="modalScanDoc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="bi bi-file-earmark-pdf"></i> Escanear Documentos
                        (Multi-páginas)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="row">
                        <div class="col-lg-9 col-12 mb-3">
                            <div class="cam-frame border rounded bg-black"
                                style="height: 60vh; position: relative; overflow: hidden;">
                                <video id="scanVideo" autoplay playsinline
                                    style="width: 100%; height: 100%; object-fit: contain;"></video>
                            </div>
                            <div class="text-center mt-3">
                                <button id="btnScanSnap" class="btn btn-primary btn-lg rounded-circle"
                                    style="width: 64px; height: 64px;">
                                    <i class="bi bi-camera fs-4"></i>
                                </button>
                                <div class="help mt-2">Centralize o documento e clique para capturar.</div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-12">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header fw-bold small text-uppercase bg-white">Páginas Capturadas</div>
                                <div class="card-body bg-white p-2" style="overflow-y: auto; max-height: 60vh;">
                                    <div id="scanThumbs" class="text-center text-muted py-5">
                                        <i class="bi bi-images fs-1 d-block mb-2"></i>
                                        Nenhuma foto tirada
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <button id="btnScanFinish" class="btn btn-success w-100" disabled>
                                        <i class="bi bi-check-lg"></i> Finalizar PDF
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div><!-- row -->
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL CÂMERA (PERFIL) ===== -->
    <div class="modal fade" id="modalCamera" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-camera-video"></i> Capturar foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="cam-wrap">
                        <div class="cam-frame"><video id="camVideo" autoplay playsinline></video></div>
                        <div class="cam-controls">
                            <button id="btnTrocarCamera" type="button" class="btn btn-outline-secondary btn-sm"><i
                                    class="bi bi-arrow-left-right"></i> Alternar câmera</button>
                            <span class="help">Por padrão abrimos a <b>traseira</b>.</span>
                        </div>
                        <img id="capturaPreview" alt="Prévia da captura">
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="btnCapturar" type="button" class="btn btn-primary"><i class="bi bi-camera"></i> Tirar
                        foto</button>
                    <button id="btnTirarOutra" type="button" class="btn btn-outline-secondary d-none"><i
                            class="bi bi-arrow-counterclockwise"></i> Tirar outra</button>
                    <button id="btnUsarFoto" type="button" class="btn btn-success d-none"><i
                            class="bi bi-check2-circle"></i> Usar foto</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Config PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>

    <script>
        // ===== Dados do PHP =====
        const familiaresDB = <?= json_encode($familiares, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const documentosDB = <?= json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // ===== Utilidades =====
        function onlyDigits(v) {
            return (v || '').toString().replace(/\D+/g, '');
        }

        function isVisible(el) {
            if (!el) return false;
            if (el.classList.contains('d-none')) return false;
            const r = el.getClientRects();
            return !!(r && r.length);
        }

        // ===== Máscaras =====
        function maskCPF(v) {
            const d = onlyDigits(v).slice(0, 11);
            let r = '';
            if (d.length > 0) r = d.slice(0, 3);
            if (d.length >= 4) r += '.' + d.slice(3, 6);
            if (d.length >= 7) r += '.' + d.slice(6, 9);
            if (d.length >= 10) r += '-' + d.slice(9, 11);
            return r;
        }

        function maskPhone(v) {
            const d = onlyDigits(v).slice(0, 11);
            if (d.length <= 10) {
                const p1 = d.slice(0, 2),
                    p2 = d.slice(2, 6),
                    p3 = d.slice(6, 10);
                return (p1 ? '(' + p1 + ') ' : '') + p2 + (p3 ? '-' + p3 : '');
            } else {
                const p1 = d.slice(0, 2),
                    p2 = d.slice(2, 7),
                    p3 = d.slice(7, 11);
                return (p1 ? '(' + p1 + ') ' : '') + p2 + (p3 ? '-' + p3 : '');
            }
        }

        function toCentsFromMaybe(val) {
            if (val == null) return null;
            let s = val.toString().trim();
            if (!s) return null;
            if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
            const n = Number(s);
            if (!isFinite(n)) return null;
            return Math.round(n * 100);
        }

        function formatCents(cents) {
            const sign = cents < 0 ? '-' : '';
            cents = Math.abs(cents);
            const int = Math.floor(cents / 100).toString();
            const dec = (cents % 100).toString().padStart(2, '0');
            return sign + int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
        }

        function formatMoneyOnLoad(el) {
            const cents = toCentsFromMaybe(el.value);
            if (cents === null) return;
            el.value = formatCents(cents);
        }

        function maskMoneyLive(v) {
            v = (v || '').replace(/\D/g, '');
            if (!v) return '';
            let i = parseInt(v, 10).toString();
            if (i === 'NaN') i = '0';
            while (i.length < 3) i = '0' + i;
            const int = i.slice(0, -2),
                dec = i.slice(-2);
            return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
        }

        function attachMask(el, fn) {
            if (!el) return;
            ['input', 'paste', 'change'].forEach(evt => {
                el.addEventListener(evt, () => {
                    el.value = fn(el.value);
                    try {
                        el.setSelectionRange(el.value.length, el.value.length);
                    } catch (_) {}
                });
            });
        }

        // ===== Família =====
        function createSelect(options, name, value) {
            const sel = document.createElement('select');
            sel.className = 'form-select form-select-sm';
            sel.name = name;
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = 'Selecione...';
            sel.appendChild(opt0);
            options.forEach(o => {
                const op = document.createElement('option');
                op.value = o;
                op.textContent = o;
                if ((value || '') === o) op.selected = true;
                sel.appendChild(op);
            });
            return sel;
        }

        function addMembroRowFromData(fam) {
            const tb = document.querySelector('#tblFamilia tbody');
            if (!tb) return;

            const parentescos = ['Cônjuge/Companheiro', 'Filho(a)', 'Enteado(a)', 'Neto(a)', 'Pai/Mãe', 'Sogro(a)', 'Irmão/Irmã', 'Genro/Nora', 'Outro'];
            const escolaridades = ['Não alfabetizado', 'Fundamental Incompleto', 'Fundamental Completo', 'Médio Incompleto', 'Médio Completo', 'Superior Incompleto', 'Superior Completo'];

            const tr = document.createElement('tr');

            const tdNome = document.createElement('td');
            const inNome = document.createElement('input');
            inNome.type = 'text';
            inNome.className = 'form-control form-control-sm';
            inNome.name = 'fam_nome[]';
            inNome.value = fam?.nome ?? '';
            inNome.placeholder = 'Nome';
            tdNome.appendChild(inNome);

            const tdNasc = document.createElement('td');
            const inNasc = document.createElement('input');
            inNasc.type = 'date';
            inNasc.className = 'form-control form-control-sm';
            inNasc.name = 'fam_nasc[]';
            inNasc.value = fam?.data_nascimento ?? '';
            tdNasc.appendChild(inNasc);

            const tdPar = document.createElement('td');
            tdPar.appendChild(createSelect(parentescos, 'fam_parentesco[]', fam?.parentesco ?? ''));

            const tdEsc = document.createElement('td');
            tdEsc.appendChild(createSelect(escolaridades, 'fam_escolaridade[]', fam?.escolaridade ?? ''));

            const tdObs = document.createElement('td');
            const inObs = document.createElement('input');
            inObs.type = 'text';
            inObs.className = 'form-control form-control-sm';
            inObs.name = 'fam_obs[]';
            inObs.value = fam?.obs ?? '';
            inObs.placeholder = 'Obs';
            tdObs.appendChild(inObs);

            const tdDel = document.createElement('td');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-danger btn-icon-sm';
            btn.title = 'Remover';
            btn.innerHTML = '<i class="bi bi-trash"></i>';
            btn.addEventListener('click', () => tr.remove());
            tdDel.appendChild(btn);

            tr.appendChild(tdNome);
            tr.appendChild(tdNasc);
            tr.appendChild(tdPar);
            tr.appendChild(tdEsc);
            tr.appendChild(tdObs);
            tr.appendChild(tdDel);
            tb.appendChild(tr);
        }

        function addMembroRowEmpty() {
            addMembroRowFromData({
                nome: '',
                data_nascimento: '',
                parentesco: '',
                escolaridade: '',
                obs: ''
            });
        }

        // ===== Lista de novos arquivos selecionados =====
        function bindDocsInput() {
            const docsInput = document.getElementById('documentos');
            const box = document.getElementById('docs_novos_list');
            if (!docsInput || !box) return;

            docsInput.addEventListener('change', function() {
                box.innerHTML = '';
                if (!this.files || !this.files.length) {
                    box.innerHTML = '<div class="text-muted">Nenhum novo arquivo selecionado.</div>';
                    return;
                }
                Array.from(this.files).forEach(f => {
                    const p = document.createElement('div');
                    p.textContent = `• $ { f.na m e} (${(f.size / 1024 / 1024).toFixed(2)} MB)`;
                    box.appendChild(p);
                });
            });
        }

        // ===== Navegação por etapas (Stepper) ==== =
        (function() {
            const sections = Array.from(document.querySelectorAll('[data-form-step]'));
            const stepTitleEl = document.getElementById('stepTitle');
            const stepper = document.getElementById('stepper');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const btnReview = document.getElementById('btnReview');
            const btnSave = document.getElementById('btnSave');

            const titles = {
                1: 'I. DADOS DE IDENTIFICAÇÃO',
                2: 'II. FAMÍLIA E COMPOSIÇÃO',
                3: 'III. CONDIÇÕES HABITACIONAIS',
                4: 'IV. RESUMO E CONFIRMAÇÃO'
            };

            let current = 1;

            function setTitle(s) {
                if (stepTitleEl) stepTitleEl.textContent = titles[s] || '';
            }

            function setStepperState() {
                if (!stepper) return;
                stepper.querySelectorAll('.step-btn').forEach(btn => {
                    const s = Number(btn.dataset.step || 0);
                    btn.classList.toggle('active', s === current);
                    btn.classList.toggle('done', s < current);
                });
            }

            function setButtons() {
                if (btnPrev) btnPrev.disabled = (current === 1);

                // Força exibição explicita via style
                if (btnNext) {
                    const showNext = (current === 1 || current === 2);
                    btnNext.style.display = showNext ? 'inline-flex' : 'none';
                    if (showNext) btnNext.classList.remove('d-none');
                }

                if (btnReview) {
                    const showReview = (current === 3);
                    btnReview.style.display = showReview ? 'inline-flex' : 'none';
                    if (showReview) btnReview.classList.remove('d-none');
                }

                if (btnSave) {
                    const showSave = (current === 4);
                    btnSave.style.display = showSave ? 'inline-flex' : 'none';
                    if (showSave) btnSave.classList.remove('d-none');
                }
            }

            function goto(step) {
                current = Math.max(1, Math.min(4, step));
                sections.forEach(sec => {
                    const n = Number(sec.dataset.formStep);
                    sec.classList.toggle('hidden', n !== current);
                });

                setTitle(current);
                setStepperState();
                setButtons();

                const card = document.getElementById('cardForm');
                if (card) {
                    card.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }

            function validateStep(step, focus = true) {
                let ok = true;
                const sec = document.querySelector(`[data-form-step="${step}"]`);
                if (!sec) return true;

                const elems = Array.from(sec.querySelectorAll('input,select,textarea'));
                let firstInvalid = null;

                elems.forEach(el => {
                    if (el.disabled) return;
                    if (!isVisible(el)) {
                        el.classList.remove('is-invalid', 'is-valid');
                        return;
                    }

                    const isReq = el.hasAttribute('required');
                    if (isReq) {
                        let val = '';
                        if (el.name === 'cpf' || el.name === 'telefone') val = onlyDigits(el.value);
                        else val = (el.value || '').trim();

                        const valid = !!val;
                        el.classList.toggle('is-invalid', !valid);
                        el.classList.toggle('is-valid', valid);
                        if (!valid) {
                            ok = false;
                            if (!firstInvalid) firstInvalid = el;
                        }
                    } else {
                        el.classList.remove('is-valid');
                    }

                    if (el.name === 'cpf' && el.hasAttribute('required')) {
                        const d = onlyDigits(el.value);
                        const v = d.length === 11;
                        el.classList.toggle('is-invalid', !v);
                        el.classList.toggle('is-valid', v);
                        if (!v) {
                            ok = false;
                            if (!firstInvalid) firstInvalid = el;
                        }
                    }

                    if (el.name === 'telefone' && el.hasAttribute('required')) {
                        const d = onlyDigits(el.value);
                        const v = d.length >= 10 && d.length <= 11;
                        el.classList.toggle('is-invalid', !v);
                        el.classList.toggle('is-valid', v);
                        if (!v) {
                            ok = false;
                            if (!firstInvalid) firstInvalid = el;
                        }
                    }
                });

                if (!ok && firstInvalid && focus) {
                    firstInvalid.focus({
                        preventScroll: true
                    });
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                return ok;
            }

            function validateAllAndGo() {
                for (let s = 1; s <= 3; s++) {
                    if (!validateStep(s, false)) {
                        goto(s);
                        validateStep(s, true);
                        return false;
                    }
                }
                return true;
            }

            if (stepper) {
                stepper.addEventListener('click', (ev) => {
                    const btn = ev.target.closest('.step-btn');
                    if (!btn) return;
                    const step = Number(btn.dataset.step || 1);

                    if (step > current) {
                        if (!validateStep(current, true)) return;
                        if (step === 4) buildResumo();
                    }
                    goto(step);
                });
            }

            if (btnPrev) btnPrev.addEventListener('click', () => goto(current - 1));
            if (btnNext) btnNext.addEventListener('click', () => {
                if (!validateStep(current, true)) return;
                goto(current + 1);
            });
            if (btnReview) btnReview.addEventListener('click', () => {
                if (!validateStep(3, true)) return;
                buildResumo();
                goto(4);
            });

            const form = document.getElementById('formSolicitante');
            if (form) {
                form.addEventListener('submit', (e) => {
                    if (!validateAllAndGo()) {
                        e.preventDefault();
                        return;
                    }
                    const chk = document.getElementById('chkConfirm');
                    if (!chk || !chk.checked) {
                        e.preventDefault();
                        goto(4);
                        if (chk) chk.classList.add('is-invalid');
                        return;
                    }

                    // Limpar máscaras antes de enviar
                    const cpf = document.getElementById('cpf');
                    const conj = document.getElementById('conj_cpf');
                    const tel = document.getElementById('telefone');

                    if (cpf) cpf.value = onlyDigits(cpf.value);
                    if (conj) conj.value = onlyDigits(conj.value);
                    if (tel) tel.value = onlyDigits(tel.value);

                    document.querySelectorAll('.moeda').forEach(el => {
                        if (el.value) {
                            el.value = el.value.replace(/\./g, '').replace(',', '.');
                        }
                    });
                });
            }

            window.__goto = goto;
            window.validateStep = validateStep;
            goto(1);
        })();

        // ===== Toggles condicionais =====
        (function() {
            const conjBpcSel = document.getElementById('conj_bpc');
            const wrapConjBpcValor = document.getElementById('wrap_conj_bpc_valor');

            const situacaoSel = document.getElementById('situacao_imovel');
            const wrapSitValor = document.getElementById('wrap_situacao_valor');

            const rendaFaixa = document.getElementById('renda_mensal_faixa');
            const rendaOutros = document.getElementById('renda_mensal_outros');
            const lblRendaOutros = document.getElementById('lbl_renda_outros');

            const pbfSel = document.getElementById('pbf');
            const pbfVal = document.getElementById('pbf_valor');
            const bpcSel = document.getElementById('bpc');
            const bpcVal = document.getElementById('bpc_valor');

            const benMunSel = document.getElementById('beneficio_municipal');
            const benMunVal = document.getElementById('beneficio_municipal_valor');
            const benEstSel = document.getElementById('beneficio_estadual');
            const benEstVal = document.getElementById('beneficio_estadual_valor');

            const pcdSel = document.getElementById('pcd');
            const pcdTipoWrap = document.getElementById('wrap_pcd_tipo');

            const grupoSel = document.getElementById('grupo_tradicional');
            const wrapGrupoOutros = document.getElementById('wrap_grupo_outros');

            function toggle(el, show) {
                if (el) el.classList.toggle('d-none', !show);
            }

            function isYes(sel) {
                return (sel?.value || 'Não') === 'Sim';
            }

            function toggleConjBpc() {
                toggle(wrapConjBpcValor, isYes(conjBpcSel));
            }

            function toggleSituacaoValor() {
                const v = situacaoSel?.value || '';
                toggle(wrapSitValor, v === 'Alugado' || v === 'Financiado');
            }

            function toggleRendaOutros() {
                const isOutros = (rendaFaixa?.value || '') === 'Outros';
                toggle(rendaOutros, isOutros);
                toggle(lblRendaOutros, isOutros);
            }

            function togglePbf() {
                toggle(pbfVal, isYes(pbfSel));
            }

            function toggleBpc() {
                toggle(bpcVal, isYes(bpcSel));
            }

            function toggleBenMun() {
                toggle(benMunVal, isYes(benMunSel));
            }

            function toggleBenEst() {
                toggle(benEstVal, isYes(benEstSel));
            }

            function togglePcdTipo() {
                toggle(pcdTipoWrap, (pcdSel?.value || 'Não') === 'Sim');
            }

            function toggleGrupoOutros() {
                if (wrapGrupoOutros) wrapGrupoOutros.classList.toggle('d-none', (grupoSel?.value || '') !== 'Outros');
            }

            const outrosMap = [{
                    sel: 'tipo_moradia',
                    wrap: 'wrap_tipo_moradia_outros'
                },
                {
                    sel: 'abastecimento',
                    wrap: 'wrap_abastecimento_outros'
                },
                {
                    sel: 'iluminacao',
                    wrap: 'wrap_iluminacao_outros'
                },
                {
                    sel: 'esgoto',
                    wrap: 'wrap_esgoto_outros'
                },
                {
                    sel: 'lixo',
                    wrap: 'wrap_lixo_outros'
                },
                {
                    sel: 'entorno',
                    wrap: 'wrap_entorno_outros'
                },
            ];

            function setupOutros(selId, wrapId) {
                const sel = document.getElementById(selId);
                const wrap = document.getElementById(wrapId);
                const fn = () => toggle(wrap, (sel?.value || '') === 'Outros');
                if (sel) {
                    sel.addEventListener('change', fn);
                    fn();
                }
            }

            if (conjBpcSel) conjBpcSel.addEventListener('change', toggleConjBpc);
            if (situacaoSel) situacaoSel.addEventListener('change', toggleSituacaoValor);
            if (rendaFaixa) rendaFaixa.addEventListener('change', toggleRendaOutros);
            if (pbfSel) pbfSel.addEventListener('change', togglePbf);
            if (bpcSel) bpcSel.addEventListener('change', toggleBpc);
            if (benMunSel) benMunSel.addEventListener('change', toggleBenMun);
            if (benEstSel) benEstSel.addEventListener('change', toggleBenEst);
            if (pcdSel) pcdSel.addEventListener('change', togglePcdTipo);
            if (grupoSel) grupoSel.addEventListener('change', toggleGrupoOutros);

            toggleConjBpc();
            toggleSituacaoValor();
            toggleRendaOutros();
            togglePbf();
            toggleBpc();
            toggleBenMun();
            toggleBenEst();
            togglePcdTipo();
            toggleGrupoOutros();
            outrosMap.forEach(o => setupOutros(o.sel, o.wrap));
        })();

        // ===== Resumo =====
        function escapeHtml(s) {
            return (s ?? '').toString().replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [m]));
        }

        function kv(label, value) {
            return `<div class="kv"><div class="kv-label">${escapeHtml(label)}</div><div class="kv-value">${escapeHtml(value || '-')}</div></div>`;
        }

        function getSelectedText(sel) {
            if (!sel) return '';
            if (sel.multiple) return Array.from(sel.selectedOptions).map(o => o.textContent.trim()).join(', ');
            return sel.options[sel.selectedIndex]?.textContent?.trim() || '';
        }

        function buildResumo() {
            const box = document.getElementById('boxResumo');
            if (!box) return;

            const form = document.getElementById('formSolicitante');
            const data = new FormData(form);

            const membros = [];
            document.querySelectorAll('#tblFamilia tbody tr').forEach(tr => {
                membros.push({
                    nome: tr.querySelector('[name="fam_nome[]"]')?.value || '',
                    nasc: tr.querySelector('[name="fam_nasc[]"]')?.value || '',
                    parent: tr.querySelector('[name="fam_parentesco[]"]')?.value || '',
                    esc: tr.querySelector('[name="fam_escolaridade[]"]')?.value || '',
                    obs: tr.querySelector('[name="fam_obs[]"]')?.value || ''
                });
            });

            const removDocs = document.querySelectorAll('input[name="docs_remover[]"]:checked').length;
            const fotoRemover = document.getElementById('foto_remover')?.checked ? 'Sim' : 'Não';
            const fotoNova = document.getElementById('foto_base64')?.value ? 'Sim' : 'Não';

            let html = '';
            html += '<h6 class="mb-2">I. Identificação</h6><div class="kv-grid mb-3">';
            html += kv('Nome', data.get('nome'));
            html += kv('NIS', data.get('nis'));
            html += kv('CPF', document.getElementById('cpf')?.value || '');
            html += kv('RG', data.get('rg'));
            html += kv('Emissão do RG', data.get('rg_emissao'));
            html += kv('UF (RG)', data.get('rg_uf'));
            html += kv('Nascimento', data.get('data_nascimento'));
            html += kv('Naturalidade', data.get('naturalidade'));
            html += kv('Gênero', getSelectedText(document.querySelector('[name="genero"]')));
            html += kv('Estado Civil', getSelectedText(document.querySelector('[name="estado_civil"]')));
            html += kv('Telefone', document.getElementById('telefone')?.value || '');

            const end = (data.get('endereco') || '') + ', Nº ' + (data.get('numero') || '') + (data.get('complemento') ? (' – ' + data.get('complemento')) : '');
            html += kv('Endereço', end);

            const bairroSel = document.querySelector('select[name="bairro_id"] option:checked')?.textContent || '';
            html += kv('Bairro', bairroSel);
            html += kv('Nacionalidade', data.get('nacionalidade'));
            html += kv('Tempo de Moradia', (data.get('tempo_anos') || '0') + ' anos, ' + (data.get('tempo_meses') || '0') + ' meses');

            const gruposTxt = getSelectedText(document.getElementById('grupo_tradicional'));
            const gruposOutros = data.get('grupo_outros') || '';
            html += kv('Grupos Tradicionais', gruposTxt + (gruposTxt.includes('Outros') && gruposOutros ? ' – ' + gruposOutros : ''));

            const pcdTxt = (data.get('pcd') || 'Não') + (data.get('pcd') === 'Sim' && data.get('pcd_tipo') ? ' – ' + data.get('pcd_tipo') : '');
            html += kv('PCD', pcdTxt);

            const bpcTxt = (data.get('bpc') || 'Não') + (data.get('bpc') === 'Sim' && data.get('bpc_valor') ? ' • R$ ' + data.get('bpc_valor') : '');
            html += kv('BPC', bpcTxt);

            const pbfTxt = (data.get('pbf') || 'Não') + (data.get('pbf') === 'Sim' && data.get('pbf_valor') ? ' • R$ ' + data.get('pbf_valor') : '');
            html += kv('Bolsa Família', pbfTxt);

            const benMunTxt = (data.get('beneficio_municipal') || 'Não') + (data.get('beneficio_municipal') === 'Sim' && data.get('beneficio_municipal_valor') ? ' • R$ ' + data.get('beneficio_municipal_valor') : '');
            const benEstTxt = (data.get('beneficio_estadual') || 'Não') + (data.get('beneficio_estadual') === 'Sim' && data.get('beneficio_estadual_valor') ? ' • R$ ' + data.get('beneficio_estadual_valor') : '');
            html += kv('Benefícios (Municipal)', benMunTxt);
            html += kv('Benefícios (Estadual)', benEstTxt);

            const rendaFaixaValue = data.get('renda_mensal_faixa');
            html += kv('Renda Mensal', rendaFaixaValue === 'Outros' ? ('Outros: ' + (data.get('renda_mensal_outros') || '')) : rendaFaixaValue);
            html += kv('Situação de Trabalho', getSelectedText(document.querySelector('[name="trabalho"]')));
            html += kv('Renda Individual', data.get('renda_individual'));

            // Foto/Docs no resumo
            html += kv('Foto nova capturada?', fotoNova);
            html += kv('Remover foto atual?', fotoRemover);
            html += kv('Docs marcados para remover', String(removDocs));

            const docs = document.getElementById('documentos');
            const scanIn = document.getElementById('input_camera_pdf');
            if (docs?.files?.length) html += kv('Novos arquivos anexados', `${docs.files.length} arquivo(s)`);
            if (scanIn && scanIn.files.length > 0) html += kv('PDF escaneado', 'Sim (' + scanIn.files[0].name + ')');

            html += '</div>';

            html += '<h6 class="mb-2">II. Dados do Cônjuge</h6><div class="kv-grid mb-3">';
            html += kv('Nome', data.get('conj_nome'));
            html += kv('NIS', data.get('conj_nis'));
            html += kv('CPF', data.get('conj_cpf'));
            html += kv('RG', data.get('conj_rg'));
            html += kv('Nascimento', data.get('conj_nasc'));
            html += kv('Gênero', getSelectedText(document.querySelector('[name="conj_genero"]')));
            html += kv('Nacionalidade', data.get('conj_nacionalidade'));
            html += kv('Naturalidade', data.get('conj_naturalidade'));
            html += kv('Trabalho', getSelectedText(document.querySelector('[name="conj_trabalho"]')));
            html += kv('Renda', data.get('conj_renda'));
            html += kv('PCD', data.get('conj_pcd'));
            const conjBpcTxt = (data.get('conj_bpc') || 'Não') + (data.get('conj_bpc') === 'Sim' && data.get('conj_bpc_valor') ? ' • R$ ' + data.get('conj_bpc_valor') : '');
            html += kv('BPC', conjBpcTxt);
            html += '</div>';

            html += '<h6 class="mb-2">III. Composição Familiar</h6>';
            if (membros.length) {
                html += '<div class="table-responsive"><table class="table table-sm align-middle text-nowrap"><thead><tr><th>Nome</th><th>Nasc.</th><th>Parentesco</th><th>Escolaridade</th><th>Observação</th></tr></thead><tbody>';
                membros.forEach(m => {
                    if (m.nome || m.parent || m.esc) {
                        html += `<tr><td>${escapeHtml(m.nome)}</td><td>${escapeHtml(m.nasc)}</td><td>${escapeHtml(m.parent)}</td><td>${escapeHtml(m.esc)}</td><td>${escapeHtml(m.obs)}</td></tr>`;
                    }
                });
                html += '</tbody></table></div>';
            } else {
                html += '<div class="kv-grid mb-3">' + kv('Membros', 'Sem membros adicionais') + '</div>';
            }

            html += '<div class="kv-grid mb-3">';
            html += kv('Total de Membros', data.get('total_moradores'));
            html += kv('Total de Famílias', data.get('total_familias'));
            html += kv('PCD na Residência', data.get('pcd_residencia'));
            html += kv('Qtde PCD', data.get('total_pcd'));
            html += kv('Renda Familiar', data.get('renda_familiar'));
            html += kv('Total Rendimentos Família', data.get('total_rendimentos'));
            html += kv('Tipificação', data.get('tipificacao'));
            html += '</div>';

            function valueWithOutros(campo, campoOutros) {
                const v = (data.get(campo) || '').trim();
                const vo = (data.get(campoOutros) || '').trim();
                return v === 'Outros' ? (vo || 'Outros') : v;
            }

            html += '<h6 class="mb-2">IV. Condições Habitacionais</h6><div class="kv-grid mb-3">';
            const sit = data.get('situacao_imovel');
            const sitValor = data.get('situacao_imovel_valor');
            html += kv('Situação do Imóvel', sit + ((sit === 'Alugado' || sit === 'Financiado') && sitValor ? ' • R$ ' + sitValor : ''));

            html += kv('Tipo da Moradia', valueWithOutros('tipo_moradia', 'tipo_moradia_outros'));
            html += kv('Abastecimento', valueWithOutros('abastecimento', 'abastecimento_outros'));
            html += kv('Iluminação', valueWithOutros('iluminacao', 'iluminacao_outros'));
            html += kv('Esgotamento Sanitário', valueWithOutros('esgoto', 'esgoto_outros'));
            html += kv('Coleta de Lixo', valueWithOutros('lixo', 'lixo_outros'));
            html += kv('Características do Entorno', valueWithOutros('entorno', 'entorno_outros'));

            const ajudaSel = document.getElementById('categoria_entrevista');
            const ajudaTxt = (ajudaSel && ajudaSel.options[ajudaSel.selectedIndex]) ? ajudaSel.options[ajudaSel.selectedIndex].text : '';
            if (ajudaTxt && ajudaSel.value) html += kv('Tipo de Ajuda', ajudaTxt);

            html += kv('Resumo do caso', data.get('resumo_caso'));
            html += '</div>';

            box.innerHTML = html;
        }

        // ===== Inicialização =====
        document.addEventListener('DOMContentLoaded', () => {
            (familiaresDB || []).forEach(fam => addMembroRowFromData(fam));
            const btnAdd = document.getElementById('btnAddMembro');
            if (btnAdd) btnAdd.addEventListener('click', addMembroRowEmpty);

            bindDocsInput();

            const cpf = document.getElementById('cpf');
            const conjCpf = document.getElementById('conj_cpf');
            const tel = document.getElementById('telefone');

            if (cpf) {
                cpf.value = maskCPF(cpf.value);
                attachMask(cpf, maskCPF);
            }
            if (conjCpf) {
                conjCpf.value = maskCPF(conjCpf.value);
                attachMask(conjCpf, maskCPF);
            }
            if (tel) {
                tel.value = maskPhone(tel.value);
                attachMask(tel, maskPhone);
            }

            document.querySelectorAll('.moeda').forEach(el => {
                formatMoneyOnLoad(el);
                attachMask(el, maskMoneyLive);
            });
        });

        // ===== CÂMERA FOTO PERFIL =====
        (function() {
            const video = document.getElementById('camVideo');
            const btnCapture = document.getElementById('btnCapturar');
            const btnOther = document.getElementById('btnTirarOutra');
            const btnUse = document.getElementById('btnUsarFoto');
            const btnSwap = document.getElementById('btnTrocarCamera');
            const imgPreview = document.getElementById('capturaPreview');
            const inputBase64 = document.getElementById('foto_base64');
            const inputFilename = document.getElementById('foto_filename');
            const modalEl = document.getElementById('modalCamera');
            const hintNovaFoto = document.getElementById('hintNovaFoto');

            if (!video || !modalEl) return;

            let stream = null;
            let facingMode = 'environment';

            function startCamera() {
                stopCamera();
                const constraints = {
                    video: {
                        facingMode,
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        }
                    }
                };
                navigator.mediaDevices.getUserMedia(constraints)
                    .then(s => {
                        stream = s;
                        video.srcObject = s;
                        video.play();
                    })
                    .catch(() => {
                        navigator.mediaDevices.getUserMedia({
                                video: true
                            })
                            .then(s => {
                                stream = s;
                                video.srcObject = s;
                                video.play();
                            })
                            .catch(() => alert('Não foi possível acessar a câmera do perfil.'));
                    });
            }

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
            }

            modalEl.addEventListener('shown.bs.modal', startCamera);
            modalEl.addEventListener('hidden.bs.modal', () => {
                stopCamera();
                video.classList.remove('d-none');
                imgPreview.style.display = 'none';
                btnCapture?.classList.remove('d-none');
                btnOther?.classList.add('d-none');
                btnUse?.classList.add('d-none');
            });

            btnSwap?.addEventListener('click', () => {
                facingMode = (facingMode === 'user') ? 'environment' : 'user';
                startCamera();
            });

            btnCapture?.addEventListener('click', () => {
                if (!stream) return;
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                imgPreview.src = dataUrl;
                imgPreview.style.display = 'block';
                video.classList.add('d-none');

                btnCapture.classList.add('d-none');
                btnOther?.classList.remove('d-none');
                btnUse?.classList.remove('d-none');
            });

            btnOther?.addEventListener('click', () => {
                imgPreview.style.display = 'none';
                video.classList.remove('d-none');
                btnCapture?.classList.remove('d-none');
                btnOther?.classList.add('d-none');
                btnUse?.classList.add('d-none');
            });

            btnUse?.addEventListener('click', () => {
                if (inputBase64) inputBase64.value = imgPreview.src;
                if (inputFilename) inputFilename.value = 'Nova foto capturada';
                if (hintNovaFoto) hintNovaFoto.classList.remove('d-none');

                // se marcou remover e capturou nova, desmarca remover (faz mais sentido)
                const chkRemover = document.getElementById('foto_remover');
                if (chkRemover) chkRemover.checked = false;

                const modal = bootstrap.Modal.getInstance(modalEl);
                modal?.hide();
            });
        })();

        // ===== CÂMERA DOCUMENTOS (PDF) =====
        (function() {
            const btnOpen = document.getElementById('btnOpenScan');
            const modalEl = document.getElementById('modalScanDoc');
            const video = document.getElementById('scanVideo');
            const btnSnap = document.getElementById('btnScanSnap');
            const btnFinish = document.getElementById('btnScanFinish');
            const thumbsContainer = document.getElementById('scanThumbs');
            const statusText = document.getElementById('scanStatusText');
            const novosList = document.getElementById('docs_novos_list');

            if (!modalEl || !video) return;

            let inputPdf = document.getElementById('input_camera_pdf');
            if (!inputPdf) {
                inputPdf = document.createElement('input');
                inputPdf.type = 'file';
                inputPdf.name = 'documentos[]';
                inputPdf.id = 'input_camera_pdf';
                inputPdf.style.display = 'none';
                document.getElementById('formSolicitante')?.appendChild(inputPdf);
            }

            let stream = null;
            let capturedImages = [];
            // Variáveis para "Trocar/Retake"
            let replaceMode = false;
            let replaceIndex = -1;

            let facingMode = 'environment';

            btnOpen?.addEventListener('click', () => {
                new bootstrap.Modal(modalEl).show();
            });

            function startScanCamera() {
                if (stream) stopScanCamera();
                const constraints = {
                    video: {
                        facingMode,
                        width: {
                            ideal: 1920
                        },
                        height: {
                            ideal: 1080
                        }
                    }
                };
                navigator.mediaDevices.getUserMedia(constraints)
                    .then(s => {
                        stream = s;
                        video.srcObject = s;
                        video.play();
                    })
                    .catch(() => {
                        navigator.mediaDevices.getUserMedia({
                                video: true
                            })
                            .then(s => {
                                stream = s;
                                video.srcObject = s;
                                video.play();
                            })
                            .catch(() => alert('Erro ao acessar câmera.'));
                    });
            }

            function stopScanCamera() {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
            }

            function renderThumbs() {
                if (!thumbsContainer) return;
                thumbsContainer.innerHTML = '';
                if (capturedImages.length === 0) {
                    thumbsContainer.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bi bi-images fs-1 d-block mb-2"></i>
                    Nenhuma foto tirada
                </div>`;
                    return;
                }
                capturedImages.forEach((src, idx) => {
                    const div = document.createElement('div');
                    div.style.cssText = 'position:relative; display:inline-block; margin:5px;';

                    const img = document.createElement('img');
                    img.src = src;
                    img.style.cssText = 'height:80px; width:auto; border:1px solid #ccc; border-radius:4px; display:block;';

                    // Botão Remover (X)
                    const del = document.createElement('button');
                    del.innerHTML = '&times;';
                    del.title = 'Remover esta página';
                    del.style.cssText = 'position:absolute; top:-8px; right:-8px; background:#dc3545; color:#fff; border:2px solid #fff; border-radius:50%; width:24px; height:24px; font-size:16px; cursor:pointer; line-height:1; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.2);';
                    del.onclick = (e) => {
                        e.stopPropagation();
                        if (confirm('Remover esta página do PDF?')) {
                            capturedImages.splice(idx, 1);
                            renderThumbs();
                            // Se removeu tudo, reseta input
                            if (capturedImages.length === 0) {
                                inputPdf.value = '';
                                if (novosList) novosList.innerHTML = '';
                                if (statusText) statusText.classList.add('d-none');
                            }
                            if (btnFinish) btnFinish.disabled = capturedImages.length === 0;
                        }
                    };

                    // Botão Trocar (Retake)
                    const retake = document.createElement('button');
                    retake.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
                    retake.title = 'Substituir (tirar nova foto)';
                    retake.style.cssText = 'position:absolute; bottom:-8px; right:-8px; background:#ffc107; color:#000; border:2px solid #fff; border-radius:50%; width:24px; height:24px; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.2);';
                    retake.onclick = (e) => {
                        e.stopPropagation();
                        replaceMode = true;
                        replaceIndex = idx;
                        // Feedback visual (bordas amarelas no vídeo?)
                        video.style.boxShadow = '0 0 0 4px #ffc107';
                        alert(`Tire uma nova foto para SUBSTITUIR a página ${idx + 1}.`);
                    };

                    div.appendChild(img);
                    div.appendChild(del);
                    div.appendChild(retake);
                    thumbsContainer.appendChild(div);
                });
            }

            let isLoaded = false; // Flag para carregar apenas 1 vez por sessão (ou recarregar se quiser)

            async function loadExistingPdfs() {
                if (isLoaded || !documentosDB || !documentosDB.length) return;

                // Filtra apenas PDFs ou Imagens que queremos carregar? 
                // O usuario pediu para converter PDF em foto.
                // Vamos tentar carregar TODOS os arquivos que forem PDF.

                statusText.textContent = 'Carregando documentos existentes...';
                statusText.classList.remove('d-none');

                try {
                    for (const doc of documentosDB) {
                        const path = doc.arquivo_path; // ex: uploads/documentos/xyz.pdf
                        if (!path) continue;

                        const ext = path.split('.').pop().toLowerCase();
                        if (ext === 'pdf') {
                            const loadingTask = pdfjsLib.getDocument(path);
                            const pdf = await loadingTask.promise;

                            for (let p = 1; p <= pdf.numPages; p++) {
                                const page = await pdf.getPage(p);
                                const viewport = page.getViewport({
                                    scale: 2.0
                                }); // alta resoluçao
                                const canvas = document.createElement('canvas');
                                const ctx = canvas.getContext('2d');
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;

                                await page.render({
                                    canvasContext: ctx,
                                    viewport: viewport
                                }).promise;
                                const imgData = canvas.toDataURL('image/jpeg', 0.85);
                                capturedImages.push(imgData);
                            }
                        } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                            // Se já for imagem, podemos adicionar direto? 
                            // O canvas pode sujar se for cross-origin, mas aqui é local.
                            // Mas vamos focar no PDF conforme pedido.
                            // Para garantir uniformidade, desenhamos num canvas e pegamos dataurl
                            const img = new Image();
                            img.src = path;
                            await new Promise(r => img.onload = r);
                            const canvas = document.createElement('canvas');
                            canvas.width = img.width;
                            canvas.height = img.height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0);
                            capturedImages.push(canvas.toDataURL('image/jpeg', 0.85));
                        }
                    }
                    isLoaded = true;
                } catch (err) {
                    console.error("Erro ao carregar PDF existente:", err);
                    alert("Erro ao carregar alguns documentos anteriores.");
                } finally {
                    statusText.classList.add('d-none');
                    renderThumbs();
                    if (btnFinish) btnFinish.disabled = (capturedImages.length === 0);
                }
            }

            modalEl.addEventListener('shown.bs.modal', () => {
                // Se primeira vez, carrega existentes
                if (!isLoaded && capturedImages.length === 0) {
                    loadExistingPdfs();
                } else {
                    renderThumbs();
                }

                startScanCamera();
                // Habilita finalizar se já tiver imagens
                if (btnFinish) btnFinish.disabled = (capturedImages.length === 0);
            });
            modalEl.addEventListener('hidden.bs.modal', stopScanCamera);

            btnSnap?.addEventListener('click', () => {
                if (!stream) return;
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

                if (replaceMode && replaceIndex >= 0 && replaceIndex < capturedImages.length) {
                    // Substituir
                    capturedImages[replaceIndex] = dataUrl;
                    replaceMode = false;
                    replaceIndex = -1;
                    video.style.boxShadow = 'none';
                    // alert('Página substituída!');
                } else {
                    // Adicionar nova
                    capturedImages.push(dataUrl);
                }

                video.style.opacity = 0.5;
                setTimeout(() => video.style.opacity = 1, 100);
                renderThumbs();
                if (btnFinish) btnFinish.disabled = false;
            });

            btnFinish?.addEventListener('click', async () => {
                if (capturedImages.length === 0) return;
                const oldHTML = btnFinish.innerHTML;
                btnFinish.textContent = 'Gerando...';
                btnFinish.disabled = true;

                try {
                    const {
                        jsPDF
                    } = window.jspdf;
                    const doc = new jsPDF();

                    for (let i = 0; i < capturedImages.length; i++) {
                        const imgData = capturedImages[i];
                        if (i > 0) doc.addPage();

                        const imgProps = doc.getImageProperties(imgData);
                        const pdfWidth = doc.internal.pageSize.getWidth();
                        const pdfHeight = doc.internal.pageSize.getHeight();
                        const margin = 10;
                        const qualW = pdfWidth - (margin * 2);
                        const qualH = pdfHeight - (margin * 2);

                        const imgRatio = imgProps.width / imgProps.height;
                        const pageRatio = qualW / qualH;

                        let w, h;
                        if (imgRatio > pageRatio) {
                            w = qualW;
                            h = w / imgRatio;
                        } else {
                            h = qualH;
                            w = h * imgRatio;
                        }

                        doc.addImage(imgData, 'JPEG', margin, margin, w, h);
                    }

                    const blob = doc.output('blob');
                    const file = new File([blob], 'scanned_documents.pdf', {
                        type: 'application/pdf'
                    });

                    const dt = new DataTransfer();
                    dt.items.add(file);
                    inputPdf.files = dt.files;

                    if (statusText) {
                        statusText.textContent = `📦 PDF gerado com ${capturedImages.length} página(s).`;
                        statusText.classList.remove('d-none');
                    }

                    if (novosList) {
                        // Limpa lista anterior da sessão de scan para não duplicar visualmente
                        const prev = Array.from(novosList.children).find(el => el.textContent.includes('scanned_documents.pdf'));
                        if (prev) prev.remove();

                        const p = document.createElement('div');
                        p.textContent = '• scanned_documents.pdf (PDF escaneado - ' + capturedImages.length + ' págs)';
                        novosList.appendChild(p);
                    }

                    bootstrap.Modal.getInstance(modalEl)?.hide();
                } catch (err) {
                    console.error(err);
                    alert('Erro ao gerar PDF: ' + (err?.message || err));
                } finally {
                    btnFinish.innerHTML = oldHTML;
                    btnFinish.disabled = false;
                }
            });
        })();

        // ===== UPLOAD DE FOTO POR ARQUIVO (IMPLEMENTAÇÃO NOVA) =====
        (function() {
            const fileInput = document.getElementById('foto_upload_input');
            const inputBase64 = document.getElementById('foto_base64');
            const inputFilename = document.getElementById('foto_filename');
            const hintNovaFoto = document.getElementById('hintNovaFoto');
            const chkRemover = document.getElementById('foto_remover');

            if (!fileInput) return;

            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        const base64Data = e.target.result;

                        // 1. Atualizar o hidden base64 (usado no submit)
                        if (inputBase64) inputBase64.value = base64Data;

                        // 2. Atualizar o nome do arquivo no input visual
                        if (inputFilename) inputFilename.value = file.name;

                        // 3. Mostrar hint de que vai substituir
                        if (hintNovaFoto) hintNovaFoto.classList.remove('d-none');

                        // 4. Se tiver checkbox "Remover", desmarcar (pois o usuário acabou de selecionar uma nova)
                        if (chkRemover) chkRemover.checked = false;

                        // 5. Atualizar PREVIEW visual
                        // Tentamos achar a imagem existente (.thumb-foto) dentro da box-mini
                        const boxMini = document.querySelector('.col-lg-6 .box-mini');
                        if (boxMini) {
                            let thumb = boxMini.querySelector('img.thumb-foto');

                            if (thumb) {
                                // Se já existe img (foto atual), apenas troca o src
                                thumb.src = base64Data;
                            } else {
                                // Se não existe, provavelmente tem a div "Nenhuma foto cadastrada"
                                const emptyMsg = boxMini.querySelector('.text-muted.small');
                                if (emptyMsg) emptyMsg.style.display = 'none';

                                // Verifica se já criamos o preview dinamicamente antes
                                let dynamicThumb = boxMini.querySelector('#dynamic_thumb_preview');
                                if (!dynamicThumb) {
                                    // Cria a imagem
                                    dynamicThumb = document.createElement('img');
                                    dynamicThumb.id = 'dynamic_thumb_preview';
                                    dynamicThumb.className = 'thumb-foto mt-2 d-block';
                                    boxMini.appendChild(dynamicThumb);
                                }
                                dynamicThumb.src = base64Data;
                            }
                        }
                    };

                    reader.readAsDataURL(file);
                }
            });
        })();
    </script>
</body>

</html>