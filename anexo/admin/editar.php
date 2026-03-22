<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro: conexão com o banco não encontrada.');location.href='dashboard.php';</script>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s) ?? '';
}
function maskCpf(?string $cpf): string
{
    $d = only_digits($cpf);
    if (strlen($d) !== 11) return (string)$cpf;
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '${1}.${2}.${3}-${4}', $d);
}
function formatPhone(?string $t): string
{
    $d = only_digits($t);
    if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    return (string)$t;
}
function moneyBR($v): string
{
    return ($v === null || $v === '') ? '' : number_format((float)$v, 2, ',', '.');
}

/* ===== MINI API: salvar edição via AJAX ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');

    $cpfPost = only_digits((string)($_POST['cpf'] ?? ''));
    if (strlen($cpfPost) !== 11) {
        echo json_encode(['ok' => false, 'error' => 'CPF inválido.']);
        exit;
    }

    $check = $pdo->prepare("SELECT ae.id, ae.foto_path FROM ajudas_entregas ae INNER JOIN solicitantes s ON s.id = ae.pessoa_id WHERE s.cpf = :cpf ORDER BY ae.id DESC LIMIT 1");
    $check->execute([':cpf' => $cpfPost]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo json_encode(['ok' => false, 'error' => 'Registro de entrega não encontrado.']);
        exit;
    }

    $entregaId = (int)$existing['id'];

    try {
        $dataEntrega   = trim((string)($_POST['data_entrega'] ?? ''));
        $horaEntrega   = trim((string)($_POST['hora_entrega'] ?? ''));
        $quantidade    = (int)($_POST['quantidade'] ?? 1);
        $valorAplicado = trim((string)($_POST['valor_aplicado'] ?? ''));
        $responsavel   = trim((string)($_POST['responsavel'] ?? ''));
        $observacao    = trim((string)($_POST['observacao'] ?? ''));
        $entregue      = trim((string)($_POST['entregue'] ?? 'Sim'));

        if ($valorAplicado !== '') {
            $valorAplicado = str_replace(['.', ','], ['', '.'], $valorAplicado);
            if (!is_numeric($valorAplicado)) $valorAplicado = null;
        } else {
            $valorAplicado = null;
        }

        $fotoBase64 = trim((string)($_POST['foto_base64'] ?? ''));
        $fotoMime   = trim((string)($_POST['foto_mime'] ?? ''));
        $newFotoPath = $existing['foto_path'] ?? '';
        $newFotoMime = null;

        if ($fotoBase64 !== '' && str_contains($fotoBase64, ',')) {
            $ext = 'jpg';
            if (str_contains($fotoMime, 'png')) $ext = 'png';
            elseif (str_contains($fotoMime, 'webp')) $ext = 'webp';

            $dir = __DIR__ . '/../dist/uploads/fotos/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);

            $filename = 'entrega_' . $entregaId . '_' . time() . '.' . $ext;
            $filepath = $dir . '/' . $filename;

            $data = base64_decode(explode(',', $fotoBase64)[1] ?? '');
            if ($data && file_put_contents($filepath, $data)) {
                $newFotoPath = '/../dist/uploads/fotos/' . $filename;
                $newFotoMime = $fotoMime;
            }
        }

        $sql = "UPDATE ajudas_entregas SET data_entrega=:data_entrega,hora_entrega=:hora_entrega,quantidade=:quantidade,valor_aplicado=:valor,responsavel=:responsavel,observacao=:obs,entregue=:entregue,foto_path=:foto,foto_mime=:foto_mime WHERE id=:id";

        $st = $pdo->prepare($sql);
        $st->execute([
            ':data_entrega' => $dataEntrega ?: null,
            ':hora_entrega' => $horaEntrega ?: null,
            ':quantidade'   => $quantidade,
            ':valor'        => $valorAplicado,
            ':responsavel'  => $responsavel ?: null,
            ':obs'          => $observacao ?: null,
            ':entregue'     => $entregue,
            ':foto'         => $newFotoPath ?: null,
            ':foto_mime'    => $newFotoMime ?: null,
            ':id'           => $entregaId,
        ]);

        echo json_encode(['ok' => true, 'message' => 'Dados atualizados com sucesso!']);
        exit;
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => 'Erro ao salvar: ' . $ex->getMessage()]);
        exit;
    }
}

/* ===== Busca a entrega pelo CPF da URL ===== */
$cpf = only_digits((string)($_GET['cpf'] ?? ''));

if (strlen($cpf) !== 11) {
    echo "<script>alert('CPF inválido.');location.href='beneficiariosSemas.php';</script>";
    exit;
}

try {
    $st = $pdo->prepare("SELECT ae.*,s.nome AS pessoa_nome,s.cpf AS pessoa_cpf,s.telefone AS pessoa_telefone,s.endereco AS pessoa_endereco,s.numero AS pessoa_numero,COALESCE(b.nome,'') AS bairro_nome,at.nome AS ajuda_tipo_nome FROM ajudas_entregas ae INNER JOIN solicitantes s ON s.id=ae.pessoa_id LEFT JOIN bairros b ON b.id=s.bairro_id LEFT JOIN ajudas_tipos at ON at.id=ae.ajuda_tipo_id WHERE s.cpf=:cpf ORDER BY ae.id DESC LIMIT 1");
    $st->execute([':cpf' => $cpf]);
    $entrega = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $entrega = null;
}

if (!$entrega) {
    echo "<script>alert('Entrega não encontrada.');location.href='beneficiariosSemas.php';</script>";
    exit;
}

// Pré-processamento para otimização
$fotoAtual = (isset($entrega['foto_path']) && trim((string)$entrega['foto_path']) !== '')
    ? trim((string)$entrega['foto_path'])
    : '/../dist/assets/images/placeholder-user.jpg';

$d = [
    'id' => (int)$entrega['id'],
    'pessoa_nome' => e((string)($entrega['pessoa_nome'] ?? '—')),
    'pessoa_cpf' => e(maskCpf((string)($entrega['pessoa_cpf'] ?? ''))),
    'pessoa_telefone' => e(formatPhone((string)($entrega['pessoa_telefone'] ?? ''))),
    'pessoa_endereco_completo' => e(trim(($entrega['pessoa_endereco'] ?? '') . (((string)($entrega['pessoa_numero'] ?? '')) !== '' ? ', ' . $entrega['pessoa_numero'] : '')) ?: '—'),
    'bairro_nome' => e((string)($entrega['bairro_nome'] ?? '—')),
    'ajuda_tipo_nome' => e((string)($entrega['ajuda_tipo_nome'] ?? 'Não especificado')),
    'data_entrega' => e((string)($entrega['data_entrega'] ?? date('Y-m-d'))),
    'hora_entrega' => e((string)($entrega['hora_entrega'] ?? '')),
    'quantidade' => (int)($entrega['quantidade'] ?? 1),
    'valor_aplicado' => e(moneyBR($entrega['valor_aplicado'] ?? null)),
    'responsavel' => e((string)($entrega['responsavel'] ?? '')),
    'observacao' => e((string)($entrega['observacao'] ?? '')),
    'entregue' => ($entrega['entregue'] ?? 'Sim'),
    'cpf_only_digits' => e(only_digits((string)($entrega['pessoa_cpf'] ?? ''))),
    'foto_path' => e($fotoAtual),
    'foto_nome' => ($fotoAtual !== '../dist/assets/images/placeholder-user.jpg') ? e(basename($fotoAtual)) : 'Nenhuma foto selecionada'
];

$userRole = $_SESSION['user_role'] ?? '';
$showAdminMenu = ($userRole === 'prefeito' || $userRole === 'secretario');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <title>Editar Entrega – ANEXO</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css" />
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css" />
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../dist/assets/css/app.css" />
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg" />
    <style>
        .card-header {
            font-weight: 700
        }

        .help {
            font-size: .875rem;
            color: #6c757d
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem 1.25rem
        }

        @media (min-width:768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr
            }

            .form-grid .col-span-2-md {
                grid-column: 1/-1
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            min-width: 0
        }

        .field .form-label {
            margin-bottom: 0
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem
        }

        @media (max-width:576px) {
            .actions {
                justify-content: space-between
            }

            .actions .btn {
                flex: 1 1 auto
            }
        }

        .readonly-clean[readonly] {
            background: #fff !important;
            opacity: 1 !important;
            cursor: default
        }

        .profile-card {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            padding: .75rem;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: .75rem;
            background: #f8f9fa;
            margin-bottom: 1.25rem
        }

        .profile-card .avatar {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid rgba(0, 0, 0, .10);
            background: #fff
        }

        .profile-card .info {
            flex: 1;
            min-width: 200px
        }

        .profile-card .info h5 {
            margin: 0 0 .25rem;
            font-weight: 800;
            color: #25396f
        }

        .profile-card .info .meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .6rem;
            font-size: .9rem;
            color: #6c757d
        }

        .badge-tipo {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .65rem;
            border-radius: .5rem;
            font-size: .85rem;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0
        }

        .file-like .input-group .form-control[readonly] {
            background: #fff !important;
            cursor: default
        }

        .file-like .hint-line {
            font-size: .85rem;
            color: #6c757d;
            margin-top: .25rem
        }

        .foto-preview-wrap {
            margin-top: .5rem;
            text-align: center
        }

        .foto-preview-wrap img {
            max-width: 320px;
            max-height: 200px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, .10);
            object-fit: cover;
            background: #f8f9fa
        }

        .modal-camera .modal-dialog {
            width: calc(100% - 1.5rem);
            margin-left: auto;
            margin-right: auto;
            max-width: 920px
        }

        .cam-wrap {
            border: 1px solid rgba(0, 0, 0, .12);
            border-radius: .6rem;
            padding: .9rem;
            background: #fff
        }

        .cam-frame {
            width: 100%;
            border-radius: .6rem;
            overflow: hidden;
            background: #000;
            aspect-ratio: 16/9;
            position: relative
        }

        .cam-frame video,
        .cam-frame img {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            max-width: none !important;
            max-height: none !important
        }

        #camCanvas {
            display: none !important
        }

        .cam-hint {
            margin-top: .65rem;
            font-size: .9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap
        }

        .modal-camera .modal-footer {
            justify-content: flex-end;
            gap: .5rem
        }

        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo" /></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>
                        <li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="pessoasCadastradas.php">Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub active"><a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item active"><a href="beneficiariosSemas.php">ANEXO</a></li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>
                        <li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li><?php if ($showAdminMenu): ?><li class="sidebar-item has-sub"><a href="#" class="sidebar-link"><i class="bi bi-person-fill"></i><span>Usuários</span></a>
                                <ul class="submenu">
                                    <li class="submenu-item"><a href="usuariosPermitidos.php">Permitidos</a></li>
                                    <li class="submenu-item"><a href="usuariosNaoPermitidos.php">Não Permitidos</a></li>
                                </ul>
                            </li><?php endif; ?><li class="sidebar-item"><a href="../../gpsemas/index.php" class="sidebar-link"><i class="bi bi-map-fill"></i><span>Rastreamento</span></a></li>
                        <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>
            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Editar Entrega</h3>
                            <p class="text-subtitle text-muted">Altere os dados da entrega e salve.</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="beneficiariosSemas.php">Beneficiários</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Editar Entrega</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <section class="section">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-pencil-square"></i> Editando Entrega #<?= $d['id'] ?></div>
                        <div class="card-body">
                            <div class="profile-card"><img class="avatar" id="avatarAtual" src="../dist/<?= $d['foto_path'] ?>" alt="Foto" onerror="this.src='../dist/assets/images/placeholder-user.jpg'">
                                <div class="info">
                                    <h5><?= $d['pessoa_nome'] ?></h5>
                                    <div class="meta"><span><i class="bi bi-card-list"></i> <?= $d['pessoa_cpf'] ?></span><span><i class="bi bi-telephone"></i> <?= $d['pessoa_telefone'] ?></span><span><i class="bi bi-geo-alt"></i> <?= $d['pessoa_endereco_completo'] ?></span><span><i class="bi bi-pin-map"></i> <?= $d['bairro_nome'] ?></span></div>
                                    <div class="mt-2"><span class="badge-tipo"><i class="bi bi-gift"></i> <?= $d['ajuda_tipo_nome'] ?></span></div>
                                </div>
                            </div>
                            <form id="formEditar" class="form-grid" novalidate><input type="hidden" name="cpf" value="<?= $d['cpf_only_digits'] ?>"><input type="hidden" name="_ajax" value="1"><input type="hidden" name="foto_base64" id="foto_base64" value=""><input type="hidden" name="foto_mime" id="foto_mime" value="">
                                <div class="field"><label class="form-label">Data da Entrega <span class="text-danger">*</span></label><input type="date" name="data_entrega" id="data_entrega" class="form-control" value="<?= $d['data_entrega'] ?>" required></div>
                                <div class="field"><label class="form-label">Hora da Entrega</label><input type="time" name="hora_entrega" id="hora_entrega" class="form-control" value="<?= $d['hora_entrega'] ?>">
                                    <div class="help">Opcional. Ex: 14:30</div>
                                </div>
                                <div class="field"><label class="form-label">Quantidade <span class="text-danger">*</span></label><input type="number" name="quantidade" id="quantidade" class="form-control" min="1" value="<?= $d['quantidade'] ?>" required>
                                    <div class="help">Quantidade de itens entregues</div>
                                </div>
                                <div class="field"><label class="form-label">Valor Aplicado (R$)</label><input type="text" name="valor_aplicado" id="valor_aplicado" class="form-control" placeholder="Ex.: 150,00" value="<?= $d['valor_aplicado'] ?>">
                                    <div class="help">Se deixar em branco, entenderemos como sem valor fixo.</div>
                                </div>
                                <div class="field"><label class="form-label">Responsável pela Entrega</label><input type="text" name="responsavel" id="responsavel" class="form-control" placeholder="Nome do responsável" value="<?= $d['responsavel'] ?>"></div>
                                <div class="field"><label class="form-label">Status <span class="text-danger">*</span></label><select name="entregue" id="entregue" class="form-select" required>
                                        <option value="Sim" <?= $d['entregue'] === 'Sim' ? 'selected' : '' ?>>Sim - Entregue</option>
                                        <option value="Não" <?= $d['entregue'] === 'Não' ? 'selected' : '' ?>>Não - Pendente</option>
                                    </select></div>
                                <div class="field col-span-2-md file-like"><label class="form-label">Foto da Entrega</label><input type="file" id="foto_file" accept="image/*" class="d-none">
                                    <div class="input-group"><input type="text" class="form-control" id="foto_nome" value="<?= $d['foto_nome'] ?>" readonly><button type="button" class="btn btn-outline-secondary" id="btnGaleria" title="Escolher do dispositivo"><i class="bi bi-image"></i> Galeria</button><button type="button" class="btn btn-outline-secondary" id="btnEscolherFoto" title="Usar câmera"><i class="bi bi-camera"></i> Câmera</button></div>
                                    <div class="hint-line">Abrirá a câmera (traseira por padrão). Você pode alternar para a frontal.</div>
                                    <div class="foto-preview-wrap" id="fotoPreviewWrap"><?php if ($d['foto_path'] !== '../dist/assets/images/placeholder-user.jpg'): ?><img id="fotoPreview" src="<?= $d['foto_path'] ?>" alt="Foto atual" onerror="this.parentElement.style.display='none'"><?php else: ?><img id="fotoPreview" src="" alt="Preview" style="display:none"><?php endif; ?></div>
                                </div>
                                <div class="field col-span-2-md"><label class="form-label">Observação</label><textarea name="observacao" id="observacao" class="form-control" rows="3" placeholder="Opcional"><?= $d['observacao'] ?></textarea></div>
                                <div class="col-span-2-md actions"><button class="btn btn-primary" type="submit" id="btnSalvar"><i class="bi bi-check2"></i> Salvar Alterações</button><a class="btn btn-outline-secondary" href="beneficiariosSemas.php"><i class="bi bi-arrow-left"></i> Voltar</a></div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
            <footer class="mt-auto py-3 bg-body-tertiary">
                <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3 small text-muted">
                    <div><span id="current-year"></span> &copy; Todos os direitos reservados à <strong class="text-body">Prefeitura Municipal de Coari-AM.</strong></div>
                    <div>Desenvolvido por <strong class="text-body">Junior Praia, Lucas Correa e Luiz Frota.</strong></div>
                </div>
            </footer>
        </div>
    </div>
    <div class="toast-container">
        <div id="toastMsg" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">Salvo com sucesso!</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
        </div>
    </div>
    <div class="modal fade modal-camera" id="modalCamera" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2"><i class="bi bi-camera"></i>
                        <h5 class="modal-title mb-0">Capturar foto</h5>
                    </div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3 d-none" id="camInfo">Ao abrir, o navegador vai pedir <strong>permissão</strong> para usar a câmera. Clique em <strong>Permitir</strong>.</div>
                    <div class="cam-wrap">
                        <div class="cam-frame"><video id="camVideo" autoplay playsinline muted></video><canvas id="camCanvas"></canvas><img id="camPhoto" alt="Foto capturada" style="display:none;"></div>
                        <div class="cam-hint"><button type="button" class="btn btn-outline-secondary btn-sm" id="btnAlternarCam"><i class="bi bi-arrow-repeat"></i> Alternar câmera</button><span>Por padrão abrimos a traseira.</span></div>
                    </div>
                    <div class="alert alert-warning mt-3 d-none" id="camWarn"></div>
                </div>
                <div class="modal-footer">
                    <div class="w-100 d-flex justify-content-end gap-2" id="liveActions"><button type="button" class="btn btn-primary" id="btnTirarFoto"><i class="bi bi-camera"></i> Tirar foto</button></div>
                    <div class="w-100 d-none justify-content-end gap-2" id="reviewActions"><button type="button" class="btn btn-outline-secondary" id="btnTirarOutra"><i class="bi bi-arrow-counterclockwise"></i> Tirar outra</button><button type="button" class="btn btn-success" id="btnUsarFoto"><i class="bi bi-check-circle"></i> Usar foto</button></div>
                </div>
            </div>
        </div>
    </div>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>
    <script>
        (() => {
            'use strict';
            document.getElementById('current-year').textContent = new Date().getFullYear();
            const toastEl = document.getElementById('toastMsg'),
                toastBody = document.getElementById('toastBody');

            function showToast(msg, type = 'success') {
                toastBody.textContent = msg;
                toastEl.className = 'toast align-items-center border-0 text-bg-' + (type === 'error' ? 'danger' : 'success');
                bootstrap.Toast.getOrCreateInstance(toastEl, {
                    delay: 4000
                }).show()
            }
            const valorInput = document.getElementById('valor_aplicado');
            valorInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                if (!v) {
                    e.target.value = '';
                    return
                }
                v = (parseInt(v) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                e.target.value = v
            });
            const form = document.getElementById('formEditar'),
                btnSalvar = document.getElementById('btnSalvar');
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    showToast('Preencha todos os campos obrigatórios.', 'error');
                    return
                }
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
                try {
                    const fd = new FormData(form),
                        res = await fetch(window.location.pathname + window.location.search, {
                            method: 'POST',
                            body: fd
                        }),
                        j = await res.json();
                    if (j.ok) {
                        showToast(j.message || 'Salvo com sucesso!', 'success');
                        const b64 = document.getElementById('foto_base64').value;
                        if (b64) document.getElementById('avatarAtual').src = b64
                    } else showToast(j.error || 'Erro ao salvar.', 'error')
                } catch (err) {
                    showToast('Erro de conexão. Tente novamente.', 'error')
                } finally {
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = '<i class="bi bi-check2"></i> Salvar Alterações'
                }
            });
            const fileInput = document.getElementById('foto_file'),
                hidFoto = document.getElementById('foto_base64'),
                hidMime = document.getElementById('foto_mime'),
                fotoNome = document.getElementById('foto_nome'),
                fotoPreview = document.getElementById('fotoPreview'),
                btnGaleria = document.getElementById('btnGaleria'),
                btnEscolherFoto = document.getElementById('btnEscolherFoto'),
                modalEl = document.getElementById('modalCamera'),
                modal = new bootstrap.Modal(modalEl, {
                    backdrop: 'static'
                }),
                btnAlternarCam = document.getElementById('btnAlternarCam'),
                btnTirarFoto = document.getElementById('btnTirarFoto'),
                btnTirarOutra = document.getElementById('btnTirarOutra'),
                btnUsarFoto = document.getElementById('btnUsarFoto'),
                camVideo = document.getElementById('camVideo'),
                camCanvas = document.getElementById('camCanvas'),
                camPhoto = document.getElementById('camPhoto'),
                camWarn = document.getElementById('camWarn'),
                camInfo = document.getElementById('camInfo'),
                liveActions = document.getElementById('liveActions'),
                reviewActions = document.getElementById('reviewActions');
            let camStream = null,
                facingMode = 'environment',
                lastDataUrl = '';

            function showWarn(msg) {
                camWarn.textContent = msg;
                camWarn.classList.remove('d-none')
            }

            function hideWarn() {
                camWarn.textContent = '';
                camWarn.classList.add('d-none')
            }

            function showInfo() {
                camInfo.classList.remove('d-none')
            }

            function hideInfo() {
                camInfo.classList.add('d-none')
            }

            function setStateLive() {
                lastDataUrl = '';
                camPhoto.src = '';
                camPhoto.style.display = 'none';
                camVideo.style.display = 'block';
                camVideo.classList.remove('d-none');
                reviewActions.classList.add('d-none');
                liveActions.classList.remove('d-none')
            }

            function setStateReview(dataUrl) {
                lastDataUrl = dataUrl || '';
                camPhoto.src = lastDataUrl;
                camVideo.style.display = 'none';
                camVideo.classList.add('d-none');
                camPhoto.style.display = 'block';
                liveActions.classList.add('d-none');
                reviewActions.classList.remove('d-none')
            }
            async function stopCamera() {
                try {
                    if (camStream) camStream.getTracks().forEach(t => t.stop())
                } catch {}
                camStream = null;
                camVideo.srcObject = null
            }
            async function startCamera() {
                hideWarn();
                await stopCamera();
                if (!navigator.mediaDevices?.getUserMedia) {
                    showWarn('Seu navegador não suporta câmera.');
                    showInfo();
                    return false
                }
                try {
                    const state = await navigator.permissions?.query({
                        name: 'camera'
                    }).then(p => p.state).catch(() => 'unknown');
                    if (state === 'granted') hideInfo();
                    else showInfo();
                    if (state === 'denied') {
                        showWarn('Permissão de câmera BLOQUEADA. Libere nas configurações do navegador.');
                        return false
                    }
                } catch {}
                try {
                    camStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: {
                                ideal: facingMode
                            }
                        },
                        audio: false
                    });
                    camVideo.srcObject = camStream;
                    await camVideo.play();
                    hideInfo();
                    return true
                } catch (err) {
                    if (err?.name === 'NotAllowedError') showWarn('Permissão negada. Clique em "Permitir".');
                    else if (err?.name === 'NotFoundError') showWarn('Nenhuma câmera encontrada.');
                    else if (err?.name === 'NotReadableError') showWarn('Câmera em uso por outro aplicativo.');
                    else showWarn('Não foi possível acessar a câmera.');
                    showInfo();
                    return false
                }
            }

            function applyChosenImage(dataUrl, mime, filename) {
                hidFoto.value = dataUrl || '';
                hidMime.value = mime || '';
                fotoNome.value = filename || 'Foto selecionada';
                if (fotoPreview) {
                    fotoPreview.src = dataUrl;
                    fotoPreview.style.display = 'block'
                }
            }
            btnGaleria.addEventListener('click', () => {
                fileInput.value = '';
                fileInput.click()
            });
            fileInput.addEventListener('change', () => {
                const file = fileInput.files?.[0];
                if (!file) return;
                if (!file.type?.startsWith('image/')) {
                    showToast('Selecione uma imagem válida.', 'error');
                    return
                }
                if (file.size > 6 * 1024 * 1024) {
                    showToast('Imagem muito grande (máx 6MB).', 'error');
                    return
                }
                const reader = new FileReader();
                reader.onload = () => applyChosenImage(String(reader.result), file.type, file.name);
                reader.onerror = () => showToast('Erro ao ler imagem.', 'error');
                reader.readAsDataURL(file)
            });
            btnEscolherFoto.addEventListener('click', async () => {
                modal.show();
                setStateLive();
                await startCamera()
            });
            btnAlternarCam.addEventListener('click', async () => {
                facingMode = facingMode === 'environment' ? 'user' : 'environment';
                setStateLive();
                await startCamera()
            });
            btnTirarFoto.addEventListener('click', () => {
                if (!camVideo.videoWidth || !camVideo.videoHeight) {
                    showWarn('Câmera ainda não está pronta.');
                    return
                }
                const w = camVideo.videoWidth,
                    h = camVideo.videoHeight;
                camCanvas.width = w;
                camCanvas.height = h;
                const ctx = camCanvas.getContext('2d', {
                    willReadFrequently: true
                });
                if (facingMode === 'user') {
                    ctx.save();
                    ctx.translate(w, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(camVideo, 0, 0, w, h);
                    ctx.restore()
                } else {
                    ctx.drawImage(camVideo, 0, 0, w, h)
                }
                setStateReview(camCanvas.toDataURL('image/jpeg', 0.88))
            });
            btnTirarOutra.addEventListener('click', async () => {
                setStateLive();
                if (!camStream) await startCamera();
                else try {
                    await camVideo.play()
                } catch {}
            });
            btnUsarFoto.addEventListener('click', () => {
                if (!lastDataUrl) return;
                applyChosenImage(lastDataUrl, 'image/jpeg', 'Foto capturada.jpg');
                modal.hide()
            });
            modalEl.addEventListener('hidden.bs.modal', async () => {
                await stopCamera();
                hideWarn();
                setStateLive()
            })
        })();
    </script>
</body>

</html>