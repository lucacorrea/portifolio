<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* =========================
   Conexão (PDO)
========================= */
require_once __DIR__ . '/assets/conexao.php'; // deve definir $pdo (PDO)

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Erro crítico: conexão com o banco não encontrada.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================
   Helpers
========================= */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function opt($v, $t = null): string
{
    $t = $t ?? $v;
    return '<option value="' . e($v) . '">' . e($t) . '</option>';
}

/* =========================
   Carrega bairros
========================= */
$bairros = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome");
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {
    $bairros = [];
}

/* =========================
   Categorias (ajudas_tipos)
========================= */
$categoriaSelecionada = (string) ($_POST['categoria_entrevista'] ?? ($_GET['categoria_entrevista'] ?? ''));

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

/* =========================
   Usuário logado
========================= */
$nomeLogado =
    (string) ($_SESSION['usuario_nome'] ?? '') ?:
    (string) ($_SESSION['nome'] ?? '') ?:
    (string) ($_SESSION['user_nome'] ?? '') ?:
    (string) ($_SESSION['usuario'] ?? '') ?:
    (string) ($_SESSION['username'] ?? '') ?:
    '';

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Solicitante (Multi-etapas) - SEMAS</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

    <style>
        .stepper {
            display: none !important
        }

        .hidden {
            display: none !important
        }

        .required:after {
            content: " *";
            color: #dc3545
        }

        .help {
            font-size: .85rem;
            color: #6b7280
        }

        .form-section {
            border: 0;
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem
        }

        .section-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: .75rem
        }

        .table-sm td,
        .table-sm th {
            padding: .35rem .5rem
        }

        .input-like-file .form-control[disabled] {
            background: #f8f9fa
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
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04)
        }

        .form-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #eef2f7
        }

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
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo"><a href="dashboard.php"><img src="assets/images/logo/logo_pmc_2025.jpg"
                                    alt="Logo" style="height:48px"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i
                                    class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- Menu SEMAS (padrão aprovado) -->
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
                                <li class="submenu-item active"><a href="cadastrarSolicitante.php">Novo Cadastro</a>
                                </li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i
                                    class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item"><a href="beneficiariosSemas.php">SEMAS</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda
                                    Social</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a>
                                </li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i
                                    class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-person-fill"></i><span>Usuários</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="usuariosPermitidos.php">Permitidos</a></li>
                                <li class="submenu-item"><a href="usuariosNaoPermitidos.php">Não Permitidos</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item">
                            <a href="../../gpsemas/index.php" class="sidebar-link"><i
                                    class="bi bi-map-fill"></i><span>Rastreamento</span></a>
                        </li>

                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link"><i
                                    class="bi bi-box-arrow-right"></i><span>Sair</span></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <h3>Novo Solicitante</h3>
                        </div>
                        <div class="col-12 col-md-6">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Solicitantes</a></li>
                                    <li class="breadcrumb-item active">Novo Cadastro</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {

                        const cpfInput = document.getElementById('cpf');
                        const feedback = document.getElementById('cpfFeedback');

                        let ultimoCPFConsultado = '';

                        cpfInput.addEventListener('input', async () => {

                            let cpf = cpfInput.value.replace(/\D/g, '').slice(0, 11);

                            // Aplica máscara
                            let m = cpf;
                            m = m.replace(/(\d{3})(\d)/, '$1.$2');
                            m = m.replace(/(\d{3})(\d)/, '$1.$2');
                            m = m.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                            cpfInput.value = m;

                            // Ainda não completou os 11 dígitos
                            if (cpf.length < 11) {
                                cpfInput.classList.remove('is-valid', 'is-invalid');
                                feedback.textContent = '';
                                ultimoCPFConsultado = '';
                                return;
                            }

                            // Evita consultar o mesmo CPF várias vezes
                            if (cpf === ultimoCPFConsultado) return;
                            ultimoCPFConsultado = cpf;

                            // Mostra feedback de carregamento
                            feedback.textContent = 'Verificando...';
                            cpfInput.classList.remove('is-valid', 'is-invalid');

                            // CHAMADA AO BANCO
                            try {
                                // AJUSTE O CAMINHO CONFORME SUA ESTRUTURA DE PASTAS
                                // Opções comuns:
                                // './verifica_cpf.php'
                                // './dados/verifica_cpf.php'
                                // '/dados/verifica_cpf.php'
                                const resp = await fetch('./verifica_cpf.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        cpf
                                    })
                                });

                                // Debug: mostra o status da resposta
                                console.log('Status:', resp.status);

                                if (!resp.ok) {
                                    const errorText = await resp.text();
                                    console.error('Erro HTTP:', resp.status, errorText);
                                    throw new Error(`Erro HTTP ${resp.status}`);
                                }

                                const data = await resp.json();
                                console.log('Resposta:', data);

                                // Verifica se houve erro no PHP
                                if (data.erro) {
                                    throw new Error(data.mensagem || 'Erro desconhecido');
                                }

                                // Processa o resultado
                                if (data.existe) {
                                    cpfInput.classList.add('is-invalid');
                                    cpfInput.classList.remove('is-valid');
                                    feedback.textContent = 'CPF já cadastrado no sistema.';
                                } else {
                                    cpfInput.classList.remove('is-invalid');
                                    cpfInput.classList.add('is-valid');
                                    feedback.textContent = 'CPF disponível para cadastro ✔';
                                }

                            } catch (e) {
                                console.error('Erro completo:', e);
                                cpfInput.classList.add('is-invalid');
                                cpfInput.classList.remove('is-valid');
                                feedback.textContent = 'Erro ao consultar CPF: ' + e.message;
                            }

                        });

                    });
                </script>




                <div class="card form-card mb-4">
                    <div class="card-header"><strong id="stepTitle">I. DADOS DE IDENTIFICAÇÃO</strong></div>
                    <div class="card-body">
                        <form id="formSolicitante" class="needs-validation" novalidate
                            action="./dados/salvarSolicitante.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="foto_base64" id="foto_base64">

                            <!-- === ETAPA 1 — DADOS DO SOLICITANTE === -->
                            <section class="form-section" data-form-step="1">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nome Completo</label>
                                        <input type="text" class="form-control" name="nome" required>
                                        <div class="invalid-feedback">Informe o nome.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">NIS</label>
                                        <input type="text" class="form-control" name="nis" inputmode="numeric"
                                            placeholder="Somente números">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required">CPF</label>

                                        <input type="text"
                                            class="form-control"
                                            id="cpf"
                                            name="cpf"
                                            placeholder="000.000.000-00"
                                            maxlength="14"
                                            required>

                                        <div class="invalid-feedback" id="cpfFeedback"></div>
                                    </div>


                                    <div class="col-md-3">
                                        <label class="form-label required">RG</label>
                                        <input type="text" class="form-control" name="rg" required>
                                        <div class="invalid-feedback">Informe o RG.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Emissão do RG</label>
                                        <input type="date" class="form-control" name="rg_emissao">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">UF (RG)</label>
                                        <select class="form-select" name="rg_uf">
                                            <option value="">Selecione…</option>
                                            <?php
                                            $ufs = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
                                            foreach ($ufs as $uf)
                                                echo opt($uf);
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-control" name="data_nascimento">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Naturalidade (Cidade/UF)</label>
                                        <input type="text" class="form-control" name="naturalidade"
                                            placeholder="Ex.: Coari/AM">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gênero</label>
                                        <select name="genero" class="form-select">
                                            <option value="">Selecione…</option>
                                            <?= opt('Feminino') . opt('Masculino') . opt('Outro') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Estado Civil</label>
                                        <select name="estado_civil" class="form-select">
                                            <option value="">Selecione…</option>
                                            <?= opt('Casado(a)') . opt('Solteiro(a)') . opt('Viúvo(a)') . opt('União Estável') . opt('Outros') ?>
                                        </select>
                                    </div>

                                    <!-- Data do Cadastro (Manual/Auto) -->
                                    <div class="col-md-3">
                                        <label class="form-label">Data do Cadastro</label>
                                        <input type="date" class="form-control" name="data_cadastro" id="data_cadastro">
                                    </div>

                                    <!-- Hora do Cadastro (Auto) -->
                                    <div class="col-md-3">
                                        <label class="form-label">Hora do Cadastro</label>
                                        <input type="time" step="1" name="hora_cadastro" id="hora_cadastro"
                                            class="form-control">
                                    </div>
                                    <!-- Responsável (Auto) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Responsável</label>
                                        <input type="text" name="responsavel" class="form-control readonly-clean"
                                            value="<?= e($nomeLogado) ?>" readonly>
                                    </div>


                                    <div class="col-md-6">
                                        <label class="form-label required">Telefone</label>
                                        <input type="tel" class="form-control" name="telefone" id="telefone"
                                            placeholder="(00) 00000-0000" required>
                                        <div class="invalid-feedback">Informe um telefone válido (10 ou 11 dígitos).
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required">Endereço</label>
                                        <input type="text" class="form-control" name="endereco" required>
                                        <div class="invalid-feedback">Informe o endereço.</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label required">Nº</label>
                                        <input type="text" class="form-control" name="numero" required>
                                        <div class="invalid-feedback">Informe o número.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required">Bairro</label>
                                        <select class="form-select" name="bairro_id" required>
                                            <option value="">Selecione…</option>
                                            <?php foreach ($bairros as $b): ?>
                                                <option value="<?= htmlspecialchars((string) $b['id']) ?>">
                                                    <?= htmlspecialchars((string) $b['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Selecione o bairro.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Complemento</label>
                                        <input type="text" class="form-control" name="complemento"
                                            placeholder="Casa, bloco...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ponto de Referência</label>
                                        <input type="text" class="form-control" name="referencia"
                                            placeholder="Próximo a...">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Nacionalidade</label>
                                        <input type="text" class="form-control" name="nacionalidade" value="Brasileiro(a)">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Tempo de Moradia</label>
                                        <div class="input-group">
                                            <input type="number" min="0" class="form-control" name="tempo_anos"
                                                placeholder="Anos">
                                            <span class="input-group-text">anos</span>
                                            <input type="number" min="0" class="form-control" name="tempo_meses"
                                                placeholder="Meses">
                                            <span class="input-group-text">meses</span>
                                        </div>
                                    </div>

                                    <!-- Grupos Tradicionais (select simples) -->
                                    <div class="col-md-6">
                                        <label class="form-label">Grupos Tradicionais</label>
                                        <select class="form-select" name="grupo_tradicional" id="grupo_tradicional">
                                            <option value="">Selecione…</option>
                                            <?= opt('Indígena') . opt('Quilombola') . opt('Cigano') . opt('Ribeirinho') . opt('Extrativista') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-none" id="wrap_grupo_outros">
                                        <label class="form-label">Grupos (Outros)</label>
                                        <input type="text" class="form-control" name="grupo_outros" id="grupo_outros"
                                            placeholder="Descreva">
                                    </div>


                                    <!-- PCD / BPC / PBF e Benefícios -->
                                    <div class="col-md-6">
                                        <label class="form-label">PCD?</label>
                                        <select class="form-select" name="pcd" id="pcd">
                                            <?= opt('Não') . opt('Sim') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_pcd_tipo">
                                        <label class="form-label">Se PCD, qual?</label>
                                        <input type="text" class="form-control" name="pcd_tipo" id="pcd_tipo"
                                            placeholder="Deficiência...">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">BPC?</label>
                                        <div class="input-group">
                                            <select class="form-select" name="bpc" id="bpc">
                                                <?= opt('Não') . opt('Sim') ?>
                                            </select>
                                            <span class="input-group-text">Valor R$</span>
                                            <input type="text" class="form-control moeda d-none" name="bpc_valor"
                                                id="bpc_valor" placeholder="0,00">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Bolsa Família (PBF)?</label>
                                        <div class="input-group">
                                            <select class="form-select" name="pbf" id="pbf">
                                                <?= opt('Não') . opt('Sim') ?>
                                            </select>
                                            <span class="input-group-text">Valor R$</span>
                                            <input type="text" class="form-control moeda d-none" name="pbf_valor"
                                                id="pbf_valor" placeholder="0,00">
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
                                                        <?= opt('Não') . opt('Sim') ?>
                                                    </select>
                                                    <span class="input-group-text">Valor R$</span>
                                                    <input type="text" class="form-control moeda d-none"
                                                        name="beneficio_municipal_valor" id="beneficio_municipal_valor"
                                                        placeholder="0,00">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <span class="input-group-text">Estadual</span>
                                                    <select class="form-select" name="beneficio_estadual"
                                                        id="beneficio_estadual">
                                                        <?= opt('Não') . opt('Sim') ?>
                                                    </select>
                                                    <span class="input-group-text">Valor R$</span>
                                                    <input type="text" class="form-control moeda d-none"
                                                        name="beneficio_estadual_valor" id="beneficio_estadual_valor"
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
                                                <?= opt('Inferior a 1 salário mínimo') . opt('1 salário mínimo') . opt('2 salários mínimos') . opt('Outros') ?>
                                            </select>
                                            <span class="input-group-text d-none" id="lbl_renda_outros">Outros</span>
                                            <input type="text" class="form-control d-none" name="renda_mensal_outros"
                                                id="renda_mensal_outros" placeholder="Descreva">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Situação de Trabalho</label>
                                        <select class="form-select" name="trabalho">
                                            <option value="">Selecione…</option>
                                            <?= opt('Empregado(a)') . opt('Desempregado(a)') . opt('Autônomo(a)') . opt('Aposentado(a)/Pensionista') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Renda Individual (R$)</label>
                                        <input type="text" class="form-control moeda" name="renda_individual"
                                            placeholder="0,00">
                                    </div>

                                    <!-- Foto -->
                                    <div class="col-12">
                                        <label class="form-label">Foto do Solicitante</label>
                                        <div class="input-group">
                                            <input type="file" class="form-control" name="foto_upload" id="foto_upload"
                                                accept="image/*">
                                            <button type="button" class="btn btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#modalCamera"
                                                title="Tirar foto com a câmera">
                                                <i class="bi bi-camera-fill"></i> <span
                                                    class="d-none d-sm-inline">Câmera</span>
                                            </button>
                                        </div>
                                        <div
                                            class="d-flex flex-wrap justify-content-between align-items-center mt-1 gap-2">
                                            <div class="help m-0">Selecione uma imagem do dispositivo ou use a câmera.
                                            </div>
                                            <div id="fotoStatusText" class="text-success fw-bold small"></div>
                                        </div>
                                    </div>

                                    <!-- Documentos -->
                                    <div class="col-12">
                                        <label class="form-label">Anexar Documentos (PDF/Word/Excel...)</label>
                                        <div class="input-group">
                                            <input type="file" class="form-control" name="documentos[]" id="documentos"
                                                multiple accept=".pdf,.doc,.docx,.odt,.rtf,.xls,.xlsx,.jpg,.jpeg,.png">
                                            <button type="button" class="btn btn-primary" id="btnOpenScan"
                                                title="Escanear documentos com a câmera">
                                                <i class="bi bi-camera-fill"></i> <span
                                                    class="d-none d-sm-inline">Escanear</span>
                                            </button>
                                        </div>
                                        <div
                                            class="d-flex flex-wrap justify-content-between align-items-center mt-1 gap-2">
                                            <div class="help m-0">Tamanho máx. por arquivo: 10MB.</div>
                                            <div id="scanStatusText" class="text-success fw-bold small d-none"></div>
                                        </div>
                                        <div id="docs_list" class="small mt-2"></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary" id="btnNext1">Próximo</button>
                                </div>
                            </section>

                            <!-- === ETAPA 2 — CÔNJUGE + FAMÍLIA === -->
                            <section class="form-section hidden" data-form-step="2">
                                <div class="section-title">II. Dados do Cônjuge</div>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Nome</label><input type="text"
                                            class="form-control" name="conj_nome"></div>
                                    <div class="col-md-3"><label class="form-label">NIS</label><input type="text"
                                            class="form-control" name="conj_nis" inputmode="numeric"></div>
                                    <div class="col-md-3"><label class="form-label">CPF</label><input type="text"
                                            class="form-control" name="conj_cpf" id="conj_cpf"
                                            placeholder="000.000.000-00"></div>
                                    <div class="col-md-3"><label class="form-label">RG</label><input type="text"
                                            class="form-control" name="conj_rg"></div>
                                    <div class="col-md-3"><label class="form-label">Data de Nasc.</label><input
                                            type="date" class="form-control" name="conj_nasc"></div>
                                    <div class="col-md-3"><label class="form-label">Gênero</label>
                                        <select class="form-select" name="conj_genero">
                                            <option value="">Selecione…</option>
                                            <?= opt('Feminino') . opt('Masculino') . opt('Outro') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label">Nacionalidade</label><input
                                            type="text" class="form-control" name="conj_nacionalidade"
                                            value="Brasileiro(a)"></div>
                                    <div class="col-md-6"><label class="form-label">Naturalidade
                                            (Cidade/UF)</label><input type="text" class="form-control"
                                            name="conj_naturalidade"></div>
                                    <div class="col-md-6"><label class="form-label">Situação de Trabalho</label>
                                        <select class="form-select" name="conj_trabalho">
                                            <option value="">Selecione…</option>
                                            <?= opt('Empregado(a)') . opt('Desempregado(a)') . opt('Autônomo(a)') . opt('Aposentado(a)/Pensionista') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Renda (R$)</label><input type="text"
                                            class="form-control moeda" name="conj_renda" placeholder="0,00"></div>

                                    <div class="col-md-3"><label class="form-label">Pessoa com Deficiência?</label>
                                        <select class="form-select"
                                            name="conj_pcd"><?= opt('Não') . opt('Sim') ?></select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">BPC?</label>
                                        <select class="form-select" name="conj_bpc" id="conj_bpc">
                                            <?= opt('Não') . opt('Sim') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_conj_bpc_valor">
                                        <label class="form-label">Valor do BPC (R$)</label>
                                        <input type="text" class="form-control moeda" name="conj_bpc_valor"
                                            id="conj_bpc_valor" placeholder="0,00">
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
                                            name="total_moradores" min="1"></div>
                                    <div class="col-md-3"><label class="form-label">Total de Famílias na
                                            Residência</label><input type="number" class="form-control"
                                            name="total_familias" min="1"></div>

                                    <div class="col-md-3">
                                        <label class="form-label">Pessoas com Deficiência</label>
                                        <select class="form-select" name="pcd_residencia" id="pcd_residencia">
                                            <?= opt('Não') . opt('Sim') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label">Qtde PCD</label><input type="number"
                                            class="form-control" name="total_pcd" min="0"></div>

                                    <div class="col-md-3"><label class="form-label">Renda Familiar (R$)</label><input
                                            type="text" class="form-control moeda" name="renda_familiar"></div>
                                    <div class="col-md-3"><label class="form-label">Total de Rendimentos da Família
                                            (R$)</label><input type="text" class="form-control moeda"
                                            name="total_rendimentos"></div>
                                    <div class="col-12"><label class="form-label">Tipificação</label><input type="text"
                                            class="form-control" name="tipificacao"
                                            placeholder="Ex.: Vulnerabilidade social..."></div>
                                </div>

                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-light" data-nav="prev">Voltar</button>
                                    <button type="button" class="btn btn-primary" data-nav="next">Próximo</button>
                                </div>
                            </section>

                            <!-- === ETAPA 3 — CONDIÇÕES HABITACIONAIS === -->
                            <section class="form-section hidden" data-form-step="3">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Situação do Imóvel</label>
                                        <select class="form-select" name="situacao_imovel" id="situacao_imovel">
                                            <option value="">Selecione…</option>
                                            <?= opt('Reside com os pais', 'Reside com os pais') . opt('Próprio') . opt('Alugado') . opt('Cedido') . opt('Ocupação') . opt('Financiado') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_situacao_valor">
                                        <label class="form-label">Aluguel – Valor (R$)</label>
                                        <input type="text" class="form-control moeda" name="situacao_imovel_valor"
                                            id="situacao_imovel_valor" placeholder="0,00">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Tipo da Moradia</label>
                                        <select class="form-select" name="tipo_moradia" id="tipo_moradia">
                                            <option value="">Selecione…</option>
                                            <?= opt('Alvenaria') . opt('Madeira') . opt('Mista') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_tipo_moradia_outros">
                                        <label class="form-label">Tipo da Moradia (Outros)</label>
                                        <input type="text" class="form-control" name="tipo_moradia_outros"
                                            id="tipo_moradia_outros" placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Abastecimento de Água</label>
                                        <select class="form-select" name="abastecimento" id="abastecimento">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rede Pública') . opt('Poço') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_abastecimento_outros">
                                        <label class="form-label">Abastecimento (Outros)</label>
                                        <input type="text" class="form-control" name="abastecimento_outros"
                                            id="abastecimento_outros" placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Iluminação</label>
                                        <select class="form-select" name="iluminacao" id="iluminacao">
                                            <option value="">Selecione…</option>
                                            <?= opt('Próprio') . opt('Comunitário') . opt('Sem') . opt('Lampião') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_iluminacao_outros">
                                        <label class="form-label">Iluminação (Outros)</label>
                                        <input type="text" class="form-control" name="iluminacao_outros"
                                            id="iluminacao_outros" placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Esgotamento Sanitário</label>
                                        <select class="form-select" name="esgoto" id="esgoto">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rede Pública') . opt('Fossa Rudimentar') . opt('Fossa Séptica') . opt('Céu Aberto') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_esgoto_outros">
                                        <label class="form-label">Esgoto (Outros)</label>
                                        <input type="text" class="form-control" name="esgoto_outros" id="esgoto_outros"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Coleta de Lixo</label>
                                        <select class="form-select" name="lixo" id="lixo">
                                            <option value="">Selecione…</option>
                                            <?= opt('Coletado') . opt('Queimado') . opt('Enterrado') . opt('Céu Aberto') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none" id="wrap_lixo_outros">
                                        <label class="form-label">Lixo (Outros)</label>
                                        <input type="text" class="form-control" name="lixo_outros" id="lixo_outros"
                                            placeholder="Descreva">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Características do Entorno</label>
                                        <select class="form-select" name="entorno" id="entorno">
                                            <option value="">Selecione…</option>
                                            <?= opt('Rua Pavimentada') . opt('Rua não Pavimentada') . opt('Às Margens de Igarapé') . opt('Barranco') . opt('Invasão') . opt('Outros') ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8 d-none" id="wrap_entorno_outros">
                                        <label class="form-label">Características do Entorno (Outros)</label>
                                        <input type="text" class="form-control" name="entorno_outros"
                                            id="entorno_outros" placeholder="Descreva">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="categoria_entrevista" class="form-label">Categoria da
                                            Entrevista</label>

                                        <select name="categoria_entrevista" id="categoria_entrevista"
                                            class="form-control">
                                            <option value="">Selecione...</option>

                                            <?php foreach ($ajudasTipos as $r): ?>
                                                <?php
                                                $id = (string) ($r['id'] ?? '');
                                                $nome = trim((string) ($r['nome'] ?? ''));
                                                if ($id === '' || $nome === '')
                                                    continue;
                                                $sel = ($id === $categoriaSelecionada) ? 'selected' : '';
                                                ?>
                                                <option value="<?= e($id) ?>" <?= $sel ?>><?= e($nome) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Resumo do Caso (relato do atendimento)</label>
                                        <textarea class="form-control" name="resumo_caso" rows="4"
                                            placeholder="Descreva o caso, necessidade, providências…"></textarea>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-light" data-nav="prev">Voltar</button>
                                    <button type="button" class="btn btn-primary" id="btnReview">Avançar para
                                        Resumo</button>
                                </div>
                            </section>

                            <!-- === ETAPA 4 — RESUMO + DECLARAÇÃO === -->
                            <section class="form-section hidden" data-form-step="4">
                                <div id="boxResumo" class="bg-light border rounded p-3"></div>

                                <div class="form-check mt-3" style="text-align: justify !important;">
                                    <input class="form-check-input" type="checkbox" value="1" id="chkConfirm" required>
                                    <label class="form-check-label" for="chkConfirm">
                                        Declaro, para os devidos fins, que as informações prestadas são verdadeiras e
                                        autorizo a SEMAS a utilizá-las para fins de atendimento socioassistencial,
                                        conforme a legislação vigente.
                                    </label>
                                    <div class="invalid-feedback">É necessário concordar com a declaração para enviar.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-light" data-nav="prev">Voltar</button>
                                    <button type="submit" class="btn btn-success">Enviar cadastro</button>
                                </div>
                            </section>
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
                        <!-- Área da Camera -->
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
                        <!-- Lateral: Miniaturas -->
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
                    </div>
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

    <!-- jsPDF para gerar PDF no cliente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
        /* ===== Navegação / máscaras / resumo ===== */
        (function() {
            const sections = Array.from(document.querySelectorAll('[data-form-step]'));
            let current = 1;
            const titles = {
                1: 'I. DADOS DE IDENTIFICAÇÃO',
                2: 'II. FAMÍLIA E COMPOSIÇÃO',
                3: 'III. CONDIÇÕES HABITACIONAIS',
                4: 'IV. RESUMO E CONFIRMAÇÃO'
            };
            const stepTitleEl = document.getElementById('stepTitle');

            function setTitle(s) {
                if (stepTitleEl) stepTitleEl.textContent = titles[s] || '';
            }

            function goto(step) {
                current = step;
                sections.forEach(sec => sec.classList.toggle('hidden', Number(sec.dataset.formStep) !== step));
                setTitle(step);
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
            document.getElementById('btnNext1')?.addEventListener('click', () => {
                if (validateStep(1)) goto(2);
            });
            document.querySelectorAll('[data-nav="prev"]').forEach(b => b.addEventListener('click', () => goto(Math.max(1, current - 1))));
            document.querySelectorAll('[data-nav="next"]').forEach(b => b.addEventListener('click', () => goto(Math.min(4, current + 1))));
            document.getElementById('btnReview')?.addEventListener('click', () => {
                if (validateStep(3)) {
                    buildResumo();
                    goto(4);
                }
            });

            // ===== máscaras =====
            const $cpf = document.getElementById('cpf');
            const $conjCpf = document.getElementById('conj_cpf');
            const $tel = document.getElementById('telefone');

            function onlyDigits(v) {
                return (v || '').replace(/\D+/g, '');
            }

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

            function maskMoney(v) {
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
            attachMask($cpf, maskCPF);
            attachMask($conjCpf, maskCPF);
            attachMask($tel, maskPhone);
            document.querySelectorAll('.moeda').forEach(el => attachMask(el, maskMoney));

            // ===== toggles condicionais =====
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

            function toggle(el, show) {
                el?.classList.toggle('d-none', !show);
            }

            function isYes(sel) {
                return (sel?.value || 'Não') === 'Sim';
            }

            function toggleConjBpc() {
                toggle(wrapConjBpcValor, isYes(conjBpcSel));
            }

            function toggleSituacaoValor() {
                toggle(wrapSitValor, (situacaoSel?.value || '') === 'Alugado');
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

            // "Outros" selects (habitação)
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
                sel?.addEventListener('change', fn);
                fn();
            }

            // Grupo Tradicional (select simples) - exibe campo "Outros" se selecionado
            const grupoSel = document.getElementById('grupo_tradicional');
            const wrapGrupoOutros = document.getElementById('wrap_grupo_outros');

            function toggleGrupoOutros() {
                wrapGrupoOutros.classList.toggle('d-none', (grupoSel?.value || '') !== 'Outros');
            }

            grupoSel?.addEventListener('change', toggleGrupoOutros);
            toggleGrupoOutros();


            conjBpcSel?.addEventListener('change', toggleConjBpc);
            situacaoSel?.addEventListener('change', toggleSituacaoValor);
            rendaFaixa?.addEventListener('change', toggleRendaOutros);
            pbfSel?.addEventListener('change', togglePbf);
            bpcSel?.addEventListener('change', toggleBpc);
            benMunSel?.addEventListener('change', toggleBenMun);
            benEstSel?.addEventListener('change', toggleBenEst);
            pcdSel?.addEventListener('change', togglePcdTipo);

            toggleConjBpc();
            toggleSituacaoValor();
            toggleRendaOutros();
            togglePbf();
            toggleBpc();
            toggleBenMun();
            toggleBenEst();
            togglePcdTipo();
            outrosMap.forEach(o => setupOutros(o.sel, o.wrap));
            toggleGrupoOutros();

            // ===== validação por etapa =====
            window.validateStep = function(step) {
                let ok = true;
                const sec = document.querySelector(`[data-form-step="${step}"]`);
                const elems = Array.from(sec.querySelectorAll('input,select,textarea'));
                let firstInvalid = null;

                function onlyDigits(v) {
                    return (v || '').replace(/\D+/g, '');
                }

                elems.forEach(el => {
                    const isReq = el.hasAttribute('required');
                    if (isReq) {
                        let val = (el.name === 'cpf' || el.name === 'telefone') ? onlyDigits(el.value) : (el.value || '').trim();
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

                    if (el.name === 'cpf') {
                        const d = onlyDigits(el.value);
                        const v = d.length === 11;
                        if (isReq) {
                            el.classList.toggle('is-invalid', !v);
                            el.classList.toggle('is-valid', v);
                            if (!v) {
                                ok = false;
                                if (!firstInvalid) firstInvalid = el;
                            }
                        }
                    }
                    if (el.name === 'telefone') {
                        const d = onlyDigits(el.value);
                        const v = d.length >= 10 && d.length <= 11;
                        if (isReq) {
                            el.classList.toggle('is-invalid', !v);
                            el.classList.toggle('is-valid', v);
                            if (!v) {
                                ok = false;
                                if (!firstInvalid) firstInvalid = el;
                            }
                        }
                    }
                });

                if (!ok && firstInvalid) {
                    firstInvalid.focus({
                        preventScroll: true
                    });
                    firstInvalid.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                return ok;
            };

            // ===== resumo =====
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
                const data = new FormData(document.getElementById('formSolicitante'));
                const membros = [];
                document.querySelectorAll('#tblFamilia tbody tr').forEach(tr => {
                    membros.push({
                        nome: tr.querySelector('[name="fam_nome[]"]').value,
                        nasc: tr.querySelector('[name="fam_nasc[]"]').value,
                        parent: tr.querySelector('[name="fam_parentesco[]"]').value,
                        esc: tr.querySelector('[name="fam_escolaridade[]"]').value,
                        obs: tr.querySelector('[name="fam_obs[]"]').value
                    });
                });

                let html = '';
                // I. Identificação
                html += '<h6 class="mb-2">I. Identificação</h6><div class="kv-grid mb-3">';
                html += kv('Nome', data.get('nome'));
                html += kv('NIS', data.get('nis'));
                html += kv('CPF', document.getElementById('cpf').value);
                html += kv('RG', data.get('rg'));
                html += kv('Emissão do RG', data.get('rg_emissao'));
                html += kv('UF (RG)', data.get('rg_uf'));
                html += kv('Nascimento', data.get('data_nascimento'));
                html += kv('Naturalidade', data.get('naturalidade'));
                html += kv('Gênero', getSelectedText(document.querySelector('[name="genero"]')));
                html += kv('Estado Civil', getSelectedText(document.querySelector('[name="estado_civil"]')));
                html += kv('Telefone', document.getElementById('telefone').value);
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
                const rendaFaixa = data.get('renda_mensal_faixa');
                html += kv('Renda Mensal', rendaFaixa === 'Outros' ? ('Outros: ' + (data.get('renda_mensal_outros') || '')) : rendaFaixa);
                html += kv('Situação de Trabalho', getSelectedText(document.querySelector('[name="trabalho"]')));
                html += kv('Renda Individual', data.get('renda_individual'));
                html += '</div>';

                // II. Cônjuge
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

                // III. Família
                html += '<h6 class="mb-2">III. Composição Familiar</h6>';
                if (membros.length) {
                    html += '<div class="table-responsive"><table class="table table-sm align-middle text-nowrap"><thead><tr><th>Nome</th><th>Nasc.</th><th>Parentesco</th><th>Escolaridade</th><th>Observação</th></tr></thead><tbody>';
                    membros.forEach(m => html += `<tr><td>${escapeHtml(m.nome)}</td><td>${escapeHtml(m.nasc)}</td><td>${escapeHtml(m.parent)}</td><td>${escapeHtml(m.esc)}</td><td>${escapeHtml(m.obs)}</td></tr>`);
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

                // IV. Condições Habitacionais
                function valueWithOutros(campo, campoOutros) {
                    const v = (data.get(campo) || '').trim();
                    const vo = (data.get(campoOutros) || '').trim();
                    return v === 'Outros' ? (vo || 'Outros') : v;
                }
                html += '<h6 class="mb-2">IV. Condições Habitacionais</h6><div class="kv-grid mb-3">';
                const sit = data.get('situacao_imovel');
                const sitValor = data.get('situacao_imovel_valor');
                html += kv('Situação do Imóvel', sit + (sit === 'Alugado' && sitValor ? ' • R$ ' + sitValor : ''));
                html += kv('Tipo da Moradia', valueWithOutros('tipo_moradia', 'tipo_moradia_outros'));
                html += kv('Abastecimento', valueWithOutros('abastecimento', 'abastecimento_outros'));
                html += kv('Iluminação', valueWithOutros('iluminacao', 'iluminacao_outros'));
                html += kv('Esgotamento Sanitário', valueWithOutros('esgoto', 'esgoto_outros'));
                html += kv('Coleta de Lixo', valueWithOutros('lixo', 'lixo_outros'));
                html += kv('Características do Entorno', valueWithOutros('entorno', 'entorno_outros'));
                html += kv('Resumo do caso', data.get('resumo_caso'));
                const docs = document.getElementById('documentos');
                const scanIn = document.getElementById('input_camera_pdf');
                if (docs?.files?.length) html += kv('Documentos Anexados', `${docs.files.length} arquivo(s)`);
                if (scanIn && scanIn.files.length > 0) html += kv('Documentos Escaneados', 'Sim (' + scanIn.files[0].name + ')');
                if (document.getElementById('foto_base64').value) html += kv('Foto Capturada', 'Sim');
                html += '</div>';

                box.innerHTML = html;
            }

            // família
            addMembroRow();
            document.getElementById('btnAddMembro').addEventListener('click', addMembroRow);

            function addMembroRow() {
                const tb = document.querySelector('#tblFamilia tbody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td><input class="form-control form-control-sm" name="fam_nome[]" placeholder="Nome"></td>
      <td><input class="form-control form-control-sm" name="fam_nasc[]" type="date"></td>
      <td><input class="form-control form-control-sm" name="fam_parentesco[]" placeholder="Parentesco"></td>
      <td><input class="form-control form-control-sm" name="fam_escolaridade[]" placeholder="Escolaridade"></td>
      <td><input class="form-control form-control-sm" name="fam_obs[]" placeholder="Observação"></td>
      <td class="text-center">
        <button class="btn btn-outline-danger btn-icon-sm" type="button" title="Remover"><i class="bi bi-trash"></i></button>
      </td>`;
                tr.querySelector('button').addEventListener('click', () => tr.remove());
                tb.appendChild(tr);
            }

            // lista de docs
            document.getElementById('documentos')?.addEventListener('change', function() {
                const box = document.getElementById('docs_list');
                box.innerHTML = '';
                Array.from(this.files || []).forEach(f => {
                    const p = document.createElement('div');
                    p.textContent = `• ${f.name} (${(f.size / 1024 / 1024).toFixed(2)} MB)`;
                    box.appendChild(p);
                });
            });

            // saneia antes de submeter
            document.getElementById('formSolicitante').addEventListener('submit', function(e) {
                if (!document.getElementById('chkConfirm').checked) {
                    e.preventDefault();
                    document.getElementById('chkConfirm').classList.add('is-invalid');
                    return;
                }
                if (!validateStep(1)) {
                    e.preventDefault();
                    return;
                }

                const cpf = document.getElementById('cpf');
                const conj = document.getElementById('conj_cpf');
                const tel = document.getElementById('telefone');

                function onlyDigits(v) {
                    return (v || '').replace(/\D+/g, '');
                }
                cpf.value = onlyDigits(cpf.value);
                if (conj) conj.value = onlyDigits(conj.value);
                tel.value = onlyDigits(tel.value);
                document.querySelectorAll('.moeda').forEach(el => {
                    if (el.value) {
                        el.value = el.value.replace(/\./g, '').replace(',', '.');
                    }
                });
            });

            setTitle(1);

            // Auto-fill Hora do Cadastro
            const elHora = document.getElementById('hora_cadastro');
            if (elHora && !elHora.value) {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                elHora.value = `${h}:${m}:${s}`;
            }
        })();

        /* ===== CÂMERA FOTO PERFIL (Modal #modalCamera) ===== */
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
            // Modal pode não existir se movido, mas o ID deve estar lá

            let stream = null;
            let facingMode = 'environment';

            function startCamera() {
                stopCamera();
                const constraints = {
                    video: {
                        facingMode: facingMode,
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
                    .catch(e => {
                        console.error(e);
                        // Tenta fallback sem constraint
                        navigator.mediaDevices.getUserMedia({
                                video: true
                            })
                            .then(s => {
                                stream = s;
                                video.srcObject = s;
                                video.play();
                            })
                            .catch(err => alert('Não foi possível acessar a câmera do perfil.'));
                    });
            }

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
            }

            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', startCamera);
                modalEl.addEventListener('hidden.bs.modal', () => {
                    stopCamera();
                    video.classList.remove('d-none');
                    imgPreview.style.display = 'none';
                    btnCapture.classList.remove('d-none');
                    btnOther.classList.add('d-none');
                    btnUse.classList.add('d-none');
                });
            }

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
                btnOther.classList.remove('d-none');
                btnUse.classList.remove('d-none');
            });

            btnOther?.addEventListener('click', () => {
                imgPreview.style.display = 'none';
                video.classList.remove('d-none');
                btnCapture.classList.remove('d-none');
                btnOther.classList.add('d-none');
                btnUse.classList.add('d-none');
            });

            btnUse?.addEventListener('click', () => {
                inputBase64.value = imgPreview.src;
                inputFilename.value = "Foto capturada via câmera";
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                bsModal.hide();
            });
        })();

        /* ===== CÂMERA DOCUMENTOS (Novo Modal #modalScanDoc) ===== */
        (function() {
            const btnOpen = document.getElementById('btnOpenScan');
            const modalEl = document.getElementById('modalScanDoc');
            const video = document.getElementById('scanVideo');
            const btnSnap = document.getElementById('btnScanSnap');
            const btnFinish = document.getElementById('btnScanFinish');
            const thumbsContainer = document.getElementById('scanThumbs');
            const statusText = document.getElementById('scanStatusText');

            // Input hidden
            let inputPdf = document.getElementById('input_camera_pdf');
            if (!inputPdf) {
                inputPdf = document.createElement('input');
                inputPdf.type = 'file';
                inputPdf.name = 'documentos[]';
                inputPdf.id = 'input_camera_pdf';
                inputPdf.style.display = 'none';
                document.getElementById('formSolicitante').appendChild(inputPdf);
            }

            let stream = null;
            let capturedImages = [];
            let facingMode = 'environment';

            if (btnOpen) {
                btnOpen.addEventListener('click', () => {
                    const bsModal = new bootstrap.Modal(modalEl);
                    bsModal.show();
                });
            }

            function startScanCamera() {
                if (stream) stopScanCamera();
                const constraints = {
                    video: {
                        facingMode: facingMode,
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
                    .catch(e => {
                        console.error(e);
                        // Fallback
                        navigator.mediaDevices.getUserMedia({
                                video: true
                            })
                            .then(s => {
                                stream = s;
                                video.srcObject = s;
                                video.play();
                            })
                            .catch(err => alert('Erro ao acessar câmera: ' + err.message));
                    });
            }

            function stopScanCamera() {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
            }

            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', () => {
                    capturedImages = [];
                    renderThumbs();
                    startScanCamera();
                    btnFinish.disabled = true;
                });
                modalEl.addEventListener('hidden.bs.modal', stopScanCamera);
            }

            btnSnap?.addEventListener('click', () => {
                if (!stream) return;
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                capturedImages.push(dataUrl);

                video.style.opacity = 0.5;
                setTimeout(() => video.style.opacity = 1, 100);

                renderThumbs();
                btnFinish.disabled = false;
            });

            window.removeScanImg = function(idx) {
                capturedImages.splice(idx, 1);
                renderThumbs();
                if (capturedImages.length === 0) btnFinish.disabled = true;
            }

            function renderThumbs() {
                thumbsContainer.innerHTML = '';
                capturedImages.forEach((src, idx) => {
                    const div = document.createElement('div');
                    div.style.cssText = 'position:relative; display:inline-block; margin:5px;';

                    const img = document.createElement('img');
                    img.src = src;
                    img.style.cssText = 'height:80px; border:1px solid #ccc; border-radius:4px;';

                    const btnDel = document.createElement('button');
                    btnDel.innerHTML = '&times;';
                    btnDel.style.cssText = 'position:absolute; top:-5px; right:-5px; background:red; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:14px; cursor:pointer; line-height:1; display:flex; align-items:center; justify-content:center;';
                    btnDel.onclick = () => removeScanImg(idx);

                    div.appendChild(img);
                    div.appendChild(btnDel);
                    thumbsContainer.appendChild(div);
                });
            }

            btnFinish?.addEventListener('click', async () => {
                if (capturedImages.length === 0) return;

                const oldText = btnFinish.innerHTML;
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
                    const file = new File([blob], "scanned_documents.pdf", {
                        type: "application/pdf"
                    });

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    inputPdf.files = dataTransfer.files;

                    if (statusText) {
                        statusText.textContent = `📦 PDF gerado com ${capturedImages.length} página(s).`;
                        statusText.classList.remove('d-none');
                    }

                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    bsModal.hide();

                } catch (err) {
                    console.error(err);
                    alert('Erro ao gerar PDF: ' + err.message);
                } finally {
                    btnFinish.innerHTML = oldText;
                    btnFinish.disabled = false;
                }
            });

        })();
    </script>
</body>

</html>
<!-- jsPDF para gerar PDF no cliente -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    /* ===== Navegação / máscaras / resumo ===== */
    (function() {
            const sections = Array.from(document.querySelectorAll('[data-form-step]'));
            let current = 1;
            const titles = {
                1: 'I. DADOS DE IDENTIFICAÇÃO',
                2: 'II. FAMÍLIA E COMPOSIÇÃO',
                3: 'III. CONDIÇÕES HABITACIONAIS',
                4: 'IV. RESUMO E CONFIRMAÇÃO'
            };
            stepTitleEl.textContent = titles[s] || '';
        }

        function goto(step) {
            current = step;
            sections.forEach(sec => sec.classList.toggle('hidden', Number(sec.dataset.formStep) !== step));
            setTitle(step);
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        document.getElementById('btnNext1')?.addEventListener('click', () => {
            if (validateStep(1)) goto(2);
        }); document.querySelectorAll('[data-nav="prev"]').forEach(b => b.addEventListener('click', () => goto(Math.max(1, current - 1)))); document.querySelectorAll('[data-nav="next"]').forEach(b => b.addEventListener('click', () => goto(Math.min(4, current + 1)))); document.getElementById('btnReview')?.addEventListener('click', () => {
            if (validateStep(3)) {
                buildResumo();
                goto(4);
            }
        });

        // ===== máscaras =====
        const $cpf = document.getElementById('cpf');
        const $conjCpf = document.getElementById('conj_cpf');
        const $tel = document.getElementById('telefone');

        function onlyDigits(v) {
            return (v || '').replace(/\D+/g, '');
        }

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

        function maskMoney(v) {
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
        attachMask($cpf, maskCPF); attachMask($conjCpf, maskCPF); attachMask($tel, maskPhone); document.querySelectorAll('.moeda').forEach(el => attachMask(el, maskMoney));

        // ===== toggles condicionais =====
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

        function toggle(el, show) {
            el?.classList.toggle('d-none', !show);
        }

        function isYes(sel) {
            return (sel?.value || 'Não') === 'Sim';
        }

        function toggleConjBpc() {
            toggle(wrapConjBpcValor, isYes(conjBpcSel));
        }

        function toggleSituacaoValor() {
            toggle(wrapSitValor, (situacaoSel?.value || '') === 'Alugado');
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

        // "Outros" selects (habitação)
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
            sel?.addEventListener('change', fn);
            fn();
        }

        // Grupo Tradicional (select simples) - exibe campo "Outros" se selecionado
        const grupoSel = document.getElementById('grupo_tradicional');
        const wrapGrupoOutros = document.getElementById('wrap_grupo_outros');

        function toggleGrupoOutros() {
            wrapGrupoOutros.classList.toggle('d-none', (grupoSel?.value || '') !== 'Outros');
        }

        grupoSel?.addEventListener('change', toggleGrupoOutros); toggleGrupoOutros();


        conjBpcSel?.addEventListener('change', toggleConjBpc); situacaoSel?.addEventListener('change', toggleSituacaoValor); rendaFaixa?.addEventListener('change', toggleRendaOutros); pbfSel?.addEventListener('change', togglePbf); bpcSel?.addEventListener('change', toggleBpc); benMunSel?.addEventListener('change', toggleBenMun); benEstSel?.addEventListener('change', toggleBenEst); pcdSel?.addEventListener('change', togglePcdTipo);

        toggleConjBpc(); toggleSituacaoValor(); toggleRendaOutros(); togglePbf(); toggleBpc(); toggleBenMun(); toggleBenEst(); togglePcdTipo(); outrosMap.forEach(o => setupOutros(o.sel, o.wrap)); toggleGrupoOutros();

        // ===== validação por etapa =====
        window.validateStep = function(step) {
            let ok = true;
            const sec = document.querySelector(`[data-form-step="${step}"]`);
            const elems = Array.from(sec.querySelectorAll('input,select,textarea'));
            let firstInvalid = null;

            function onlyDigits(v) {
                return (v || '').replace(/\D+/g, '');
            }

            elems.forEach(el => {
                const isReq = el.hasAttribute('required');
                if (isReq) {
                    let val = (el.name === 'cpf' || el.name === 'telefone') ? onlyDigits(el.value) : (el.value || '').trim();
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

                if (el.name === 'cpf') {
                    const d = onlyDigits(el.value);
                    const v = d.length === 11;
                    if (isReq) {
                        el.classList.toggle('is-invalid', !v);
                        el.classList.toggle('is-valid', v);
                        if (!v) {
                            ok = false;
                            if (!firstInvalid) firstInvalid = el;
                        }
                    }
                }
                if (el.name === 'telefone') {
                    const d = onlyDigits(el.value);
                    const v = d.length >= 10 && d.length <= 11;
                    if (isReq) {
                        el.classList.toggle('is-invalid', !v);
                        el.classList.toggle('is-valid', v);
                        if (!v) {
                            ok = false;
                            if (!firstInvalid) firstInvalid = el;
                        }
                    }
                }
            });

            if (!ok && firstInvalid) {
                firstInvalid.focus({
                    preventScroll: true
                });
                firstInvalid.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
            return ok;
        };

        // ===== resumo =====
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
            const data = new FormData(document.getElementById('formSolicitante'));
            const membros = [];
            document.querySelectorAll('#tblFamilia tbody tr').forEach(tr => {
                membros.push({
                    nome: tr.querySelector('[name="fam_nome[]"]').value,
                    nasc: tr.querySelector('[name="fam_nasc[]"]').value,
                    parent: tr.querySelector('[name="fam_parentesco[]"]').value,
                    esc: tr.querySelector('[name="fam_escolaridade[]"]').value,
                    obs: tr.querySelector('[name="fam_obs[]"]').value
                });
            });

            let html = '';
            // I. Identificação
            html += '<h6 class="mb-2">I. Identificação</h6><div class="kv-grid mb-3">';
            html += kv('Nome', data.get('nome'));
            html += kv('NIS', data.get('nis'));
            html += kv('CPF', document.getElementById('cpf').value);
            html += kv('RG', data.get('rg'));
            html += kv('Emissão do RG', data.get('rg_emissao'));
            html += kv('UF (RG)', data.get('rg_uf'));
            html += kv('Nascimento', data.get('data_nascimento'));
            html += kv('Naturalidade', data.get('naturalidade'));
            html += kv('Gênero', getSelectedText(document.querySelector('[name="genero"]')));
            html += kv('Estado Civil', getSelectedText(document.querySelector('[name="estado_civil"]')));
            html += kv('Telefone', document.getElementById('telefone').value);
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
            const rendaFaixa = data.get('renda_mensal_faixa');
            html += kv('Renda Mensal', rendaFaixa === 'Outros' ? ('Outros: ' + (data.get('renda_mensal_outros') || '')) : rendaFaixa);
            html += kv('Situação de Trabalho', getSelectedText(document.querySelector('[name="trabalho"]')));
            html += kv('Renda Individual', data.get('renda_individual'));
            html += '</div>';

            // II. Cônjuge
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

            // III. Família
            html += '<h6 class="mb-2">III. Composição Familiar</h6>';
            if (membros.length) {
                html += '<div class="table-responsive"><table class="table table-sm align-middle text-nowrap"><thead><tr><th>Nome</th><th>Nasc.</th><th>Parentesco</th><th>Escolaridade</th><th>Observação</th></tr></thead><tbody>';
                membros.forEach(m => html += `<tr><td>${escapeHtml(m.nome)}</td><td>${escapeHtml(m.nasc)}</td><td>${escapeHtml(m.parent)}</td><td>${escapeHtml(m.esc)}</td><td>${escapeHtml(m.obs)}</td></tr>`);
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

            // IV. Condições Habitacionais
            function valueWithOutros(campo, campoOutros) {
                const v = (data.get(campo) || '').trim();
                const vo = (data.get(campoOutros) || '').trim();
                return v === 'Outros' ? (vo || 'Outros') : v;
            }
            html += '<h6 class="mb-2">IV. Condições Habitacionais</h6><div class="kv-grid mb-3">';
            const sit = data.get('situacao_imovel');
            const sitValor = data.get('situacao_imovel_valor');
            html += kv('Situação do Imóvel', sit + (sit === 'Alugado' && sitValor ? ' • R$ ' + sitValor : ''));
            html += kv('Tipo da Moradia', valueWithOutros('tipo_moradia', 'tipo_moradia_outros'));
            html += kv('Abastecimento', valueWithOutros('abastecimento', 'abastecimento_outros'));
            html += kv('Iluminação', valueWithOutros('iluminacao', 'iluminacao_outros'));
            html += kv('Esgotamento Sanitário', valueWithOutros('esgoto', 'esgoto_outros'));
            html += kv('Coleta de Lixo', valueWithOutros('lixo', 'lixo_outros'));
            html += kv('Características do Entorno', valueWithOutros('entorno', 'entorno_outros'));
            html += kv('Resumo do caso', data.get('resumo_caso'));
            const docs = document.getElementById('documentos');
            if (docs?.files?.length) html += kv('Documentos Anexados', `${docs.files.length} arquivo(s)`);
            const fotoFile = document.getElementById('foto_upload')?.files[0];
            if (document.getElementById('foto_base64').value) html += kv('Foto do Solicitante', 'Capturada na câmera');
            else if (fotoFile) html += kv('Foto do Solicitante', `Arquivo: ${fotoFile.name} (${(fotoFile.size / 1024 / 1024).toFixed(2)} MB)`);
            else html += kv('Foto do Solicitante', 'Não informada');
            html += '</div>';

            box.innerHTML = html;
        }

        // família
        addMembroRow(); document.getElementById('btnAddMembro').addEventListener('click', addMembroRow);

        function addMembroRow() {
            const tb = document.querySelector('#tblFamilia tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
      <td><input class="form-control form-control-sm" name="fam_nome[]" placeholder="Nome"></td>
      <td><input class="form-control form-control-sm" name="fam_nasc[]" type="date"></td>
      <td><input class="form-control form-control-sm" name="fam_parentesco[]" placeholder="Parentesco"></td>
      <td><input class="form-control form-control-sm" name="fam_escolaridade[]" placeholder="Escolaridade"></td>
      <td><input class="form-control form-control-sm" name="fam_obs[]" placeholder="Observação"></td>
      <td class="text-center">
        <button class="btn btn-outline-danger btn-icon-sm" type="button" title="Remover"><i class="bi bi-trash"></i></button>
      </td>`;
            tr.querySelector('button').addEventListener('click', () => tr.remove());
            tb.appendChild(tr);
        }

        // lista de docs
        document.getElementById('documentos')?.addEventListener('change', function() {
            const box = document.getElementById('docs_list');
            box.innerHTML = '';
            Array.from(this.files || []).forEach(f => {
                const p = document.createElement('div');
                p.textContent = `• ${f.name} (${(f.size / 1024 / 1024).toFixed(2)} MB)`;
                box.appendChild(p);
            });
        });

        // saneia antes de submeter
        document.getElementById('formSolicitante').addEventListener('submit', function(e) {
            if (!document.getElementById('chkConfirm').checked) {
                e.preventDefault();
                document.getElementById('chkConfirm').classList.add('is-invalid');
                return;
            }
            if (!validateStep(1)) {
                e.preventDefault();
                return;
            }

            const cpf = document.getElementById('cpf');
            const conj = document.getElementById('conj_cpf');
            const tel = document.getElementById('telefone');

            function onlyDigits(v) {
                return (v || '').replace(/\D+/g, '');
            }
            cpf.value = onlyDigits(cpf.value);
            if (conj) conj.value = onlyDigits(conj.value);
            tel.value = onlyDigits(tel.value);
            document.querySelectorAll('.moeda').forEach(el => {
                if (el.value) {
                    el.value = el.value.replace(/\./g, '').replace(',', '.');
                }
            });
        });

        setTitle(1);
    })();
</script>

<!-- Script Relógio (Isolado) -->
<script>
    (function() {
        const inpHora = document.getElementById('hora_cadastro');
        const pad2 = (n) => String(n).padStart(2, '0');

        function tickHora() {
            if (!inpHora) return;
            const d = new Date();
            inpHora.value = `${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;

            // Auto fill data se vazio
            const elData = document.getElementById('data_cadastro');
            if (elData && !elData.value) {
                const y = d.getFullYear();
                const m = pad2(d.getMonth() + 1);
                const day = pad2(d.getDate());
                elData.value = `${y}-${m}-${day}`;
            }
        }

        tickHora(); // chama já
        setInterval(tickHora, 250); // atualiza a cada 250ms
    })();
</script>

<script>
    /* ===== Câmera ===== */
    let currentStream = null,
        usingEnvironment = true;
    const video = document.getElementById('camVideo');
    const canvas = document.createElement('canvas');
    const preview = document.getElementById('capturaPreview');
    const btnCapturar = document.getElementById('btnCapturar');
    const btnUsar = document.getElementById('btnUsarFoto');
    const btnOutra = document.getElementById('btnTirarOutra');

    function uiShowVideo() {
        document.querySelector('.cam-frame').style.display = 'block';
        video.style.display = 'block';
        preview.style.display = 'none';
        btnUsar.classList.add('d-none');
        btnOutra.classList.add('d-none');
        btnCapturar.classList.remove('d-none');
    }

    function uiShowPreview() {
        document.querySelector('.cam-frame').style.display = 'none';
        video.style.display = 'none';
        preview.style.display = 'block';
        btnUsar.classList.remove('d-none');
        btnOutra.classList.remove('d-none');
        btnCapturar.classList.add('d-none');
    }
    async function startCamera(env) {
        stopCamera();
        const constraints = {
            audio: false,
            video: {
                facingMode: env ? {
                    exact: 'environment'
                } : 'user',
                width: {
                    ideal: 1280
                },
                height: {
                    ideal: 720
                }
            }
        };
        try {
            currentStream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            if (env) {
                try {
                    currentStream = await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: false
                    });
                    usingEnvironment = false;
                } catch (e) {
                    console.error(e);
                }
            } else {
                console.error(err);
            }
        }
        if (currentStream) {
            video.srcObject = currentStream;
        }
    }

    function stopCamera() {
        if (currentStream) {
            currentStream.getTracks().forEach(t => t.stop());
            currentStream = null;
        }
    }

    document.getElementById('modalCamera').addEventListener('shown.bs.modal', () => {
        uiShowVideo();
        startCamera(true).catch(() => startCamera(false));
    });
    document.getElementById('modalCamera').addEventListener('hidden.bs.modal', () => {
        stopCamera();
        uiShowVideo();
    });
    document.getElementById('btnTrocarCamera').addEventListener('click', () => {
        usingEnvironment = !usingEnvironment;
        startCamera(usingEnvironment).catch(console.error);
    });

    btnCapturar.addEventListener('click', () => {
        const w = 640,
            h = 480;
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        const vw = video.videoWidth || w,
            vh = video.videoHeight || h;
        const scale = Math.min(w / vw, h / vh);
        const dw = vw * scale,
            dh = vh * scale;
        const dx = (w - dw) / 2,
            dy = (h - dh) / 2;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(video, dx, dy, dw, dh);
        preview.src = canvas.toDataURL('image/jpeg', .9);
        uiShowPreview();
        stopCamera();
    });
    btnOutra.addEventListener('click', () => {
        uiShowVideo();
        startCamera(usingEnvironment).catch(console.error);
    });
    btnUsar.addEventListener('click', () => {
        document.getElementById('foto_base64').value = preview.src;
        // Limpa input file se houver
        const fInput = document.getElementById('foto_upload');
        if (fInput) fInput.value = '';

        document.getElementById('fotoStatusText').textContent = 'Foto capturada com sucesso.';

        const modal = bootstrap.Modal.getInstance(document.getElementById('modalCamera'));
        modal?.hide();
    });

    document.getElementById('foto_upload')?.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            document.getElementById('foto_base64').value = ''; // Limpa base64
            document.getElementById('fotoStatusText').textContent = 'Arquivo: ' + this.files[0].name;
        } else {
            document.getElementById('fotoStatusText').textContent = '';
        }
    });
</script>
</body>

</html>