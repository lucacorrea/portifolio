<?php

declare(strict_types=1);

require_once __DIR__ . '/./auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro: conexão com o banco não encontrada.');location.href='dashboard.php';</script>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
    return preg_replace('/\D+/', '', (string) $s) ?? '';
}
function maskCpf(string $cpf): string
{
    $d = only_digits($cpf);
    if (strlen($d) !== 11)
        return $cpf;
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '${1}.${2}.${3}-${4}', $d);
}

/* ===== Usuário logado (para preencher Responsável) ===== */
$nomeLogado =
    ((string) ($_SESSION['usuario_nome'] ?? '')) ?: ((string) ($_SESSION['nome'] ?? '')) ?: ((string) ($_SESSION['user_nome'] ?? '')) ?: ((string) ($_SESSION['usuario'] ?? '')) ?: ((string) ($_SESSION['username'] ?? '')) ?:
    '';

/* ===== MINI API: busca pessoas ===== */
if (($_GET['searchPessoa'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    $qDigits = only_digits($q);
    $sql = "SELECT id, nome, cpf, endereco, numero, telefone
            FROM solicitantes
            WHERE (nome LIKE :q OR cpf LIKE :qraw OR telefone LIKE :qraw)
            ORDER BY nome ASC
            LIMIT 10";
    $st = $pdo->prepare($sql);
    $st->bindValue(':q', "%{$q}%");
    $st->bindValue(':qraw', "%{$qDigits}%");
    $st->execute();

    $items = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'id' => (int) $r['id'],
            'nome' => (string) $r['nome'],
            'cpf' => (string) $r['cpf'],
            'endereco' => trim(($r['endereco'] ?? '') . (((string) ($r['numero'] ?? '')) !== '' ? ', ' . $r['numero'] : '')),
            'telefone' => (string) ($r['telefone'] ?? '')
        ];
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== MINI API: pegar tipo de ajuda ===== */
if (($_GET['getAjuda'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $st = $pdo->prepare("SELECT id, nome, valor_padrao, periodicidade, qtd_padrao
                         FROM ajudas_tipos
                         WHERE id=:id AND status='Ativa'
                         LIMIT 1");
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => (bool) $row, 'item' => $row ?: null], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== Tipos de ajuda (ativas) ===== */
$tipos = [];
try {
    $q = $pdo->query("SELECT id, nome, categoria, valor_padrao, periodicidade, qtd_padrao
                      FROM ajudas_tipos
                      WHERE status='Ativa'
                      ORDER BY nome ASC");
    $tipos = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tipos = [];
}

/* ===== Pré-seleção por CPF ===== */
$prefPessoa = null;
$cpfUrl = only_digits($_GET['cpf'] ?? '');
if ($cpfUrl !== '' && strlen($cpfUrl) === 11) {
    $st = $pdo->prepare("SELECT id, nome, cpf, endereco, numero, telefone
                         FROM solicitantes
                         WHERE cpf = :cpf
                         ORDER BY id DESC
                         LIMIT 1");
    $st->execute([':cpf' => $cpfUrl]);
    $prefPessoa = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$solicitacaoId = (int)($_GET['solicitacao_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <title>Atribuir Benefício – ANEXO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/bootstrap.css" />
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/app.css" />
    <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg" />

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
            gap: 1rem 1.25rem;
        }

        @media (min-width:768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

            .form-grid .col-span-2-md {
                grid-column: 1/-1;
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            min-width: 0;
        }

        .field .form-label {
            margin-bottom: 0
        }

        .search-group {
            position: relative
        }

        .search-results {
            position: absolute;
            inset: auto 0 0 0;
            top: calc(100% + 6px);
            z-index: 1050;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .125);
            border-radius: .375rem;
            max-height: 320px;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
        }

        .search-results.d-none {
            display: none !important
        }

        .search-results .item {
            padding: .55rem .75rem;
            cursor: pointer
        }

        .search-results .item:hover {
            background: #f8f9fa
        }

        .muted {
            color: #6c757d;
            font-size: .9rem
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
            cursor: default;
        }

        /* ===== Input visual igual do print ===== */
        .file-like .input-group .form-control[readonly] {
            background: #fff !important;
            cursor: default;
        }

        .file-like .hint-line {
            font-size: .85rem;
            color: #6c757d;
            margin-top: .25rem;
        }

        /* ===== Modal câmera ===== */
        .modal-camera .modal-dialog {
            width: calc(100% - 1.5rem);
            margin-left: auto;
            margin-right: auto;
            max-width: 920px;
        }

        .cam-wrap {
            border: 1px solid rgba(0, 0, 0, .12);
            border-radius: .6rem;
            padding: .9rem;
            background: #fff;
        }

        /* ✅ UM ÚNICO FRAME GRANDE */
        .cam-frame {
            width: 100%;
            border-radius: .6rem;
            overflow: hidden;
            background: #000;
            aspect-ratio: 16 / 9;
            position: relative;
            /* ✅ overlay perfeito */
        }

        /* ✅ video e img ocupam o MESMO lugar */
        .cam-frame video,
        .cam-frame img {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            max-width: none !important;
            max-height: none !important;
        }

        /* canvas nunca aparece */
        #camCanvas {
            display: none !important;
        }

        .cam-hint {
            margin-top: .65rem;
            font-size: .9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .modal-camera .modal-footer {
            justify-content: flex-end;
            gap: .5rem;
        }
    </style>
</head>

<body>
    <div id="app">
        <!-- Sidebar padrão -->
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="dashboard.php"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo"
                                    style="height:48px"></a>
                        </div>
                        <div class="toggler">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                        </div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i
                                    class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link"><i
                                    class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="./pessoasCadastradas.php">Cadastrados</a></li>
                                <li class="submenu-item active"><a href="#">Atribuir Benefício</a></li>
                                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
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

                        <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i
                                    class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
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
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Atribuir Benefício – ANEXO</h3>
                            <p class="text-subtitle text-muted">Registre a entrega de uma ajuda/benefício para um
                                solicitante.</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Ajuda Social</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Atribuir Benefício</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <div class="card-header">Formulário</div>
                        <div class="card-body">

                            <form method="post" action="./dados/processarBeneficioSolicitante.php" class="form-grid">
                                <input type="hidden" name="pessoa_id" id="pessoa_id"
                                    value="<?= $prefPessoa ? (int) $prefPessoa['id'] : '' ?>">
                                <input type="hidden" name="pessoa_cpf" id="pessoa_cpf"
                                    value="<?= $prefPessoa ? only_digits($prefPessoa['cpf'] ?? '') : '' ?>">
                                <input type="hidden" name="solicitacao_id" value="<?= $solicitacaoId ?>">

                                <!-- Foto (base64) -->
                                <input type="hidden" name="foto_base64" id="foto_base64" value="">
                                <input type="hidden" name="foto_mime" id="foto_mime" value="">

                                <!-- Solicitante -->
                                <div class="field col-span-2-md search-group">
                                    <label class="form-label">Solicitante</label>
                                    <input type="text" id="buscaPessoa" class="form-control"
                                        placeholder="Digite nome ou CPF..." autocomplete="off" value="<?php if ($prefPessoa) {
                                                                                                            echo e($prefPessoa['nome'] . ' — ' . maskCpf((string) $prefPessoa['cpf']));
                                                                                                        } ?>">
                                    <div id="resultPessoa" class="search-results d-none"></div>

                                    <div class="form-text" id="infoPessoa">
                                        <?php if ($prefPessoa):
                                            $end = trim(($prefPessoa['endereco'] ?? '') . (((string) ($prefPessoa['numero'] ?? '')) !== '' ? ', ' . $prefPessoa['numero'] : '')); ?>
                                            Selecionado: <strong><?= e($prefPessoa['nome']) ?></strong> — CPF:
                                            <strong><?= e(maskCpf((string) $prefPessoa['cpf'])) ?></strong>
                                            <?= $end ? ' — ' . e($end) : '' ?>
                                        <?php else: ?>
                                            <span class="muted">Nenhum selecionado.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Tipo de Ajuda -->
                                <div class="field">
                                    <label class="form-label">Tipo de Ajuda</label>
                                    <select name="ajuda_tipo_id" id="ajuda_tipo_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($tipos as $t): ?>
                                            <option value="<?= (int) $t['id'] ?>"
                                                data-valor="<?= e((string) ($t['valor_padrao'] ?? '')) ?>"
                                                data-qtd="<?= (int) ($t['qtd_padrao'] ?? 1) ?>">
                                                <?= e((string) $t['nome']) ?> <?= !empty($t['categoria']) ? ' — ' . e((string) $t['categoria']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="help">Somente “Ativas” são exibidas.</div>
                                </div>

                                <!-- Quantidade -->
                                <div class="field">
                                    <label class="form-label">Quantidade</label>
                                    <input type="number" min="1" step="1" name="quantidade" id="quantidade"
                                        class="form-control" value="1" required>
                                </div>

                                <!-- Valor -->
                                <div class="field">
                                    <label class="form-label">Valor (R$)</label>
                                    <input type="text" name="valor_aplicado" id="valor_aplicado" class="form-control"
                                        placeholder="Ex.: 150,00">
                                    <div class="help">Se deixar em branco, entenderemos como sem valor fixo.</div>
                                </div>

                                <!-- Data -->
                                <div class="field">
                                    <label class="form-label">Data da Entrega</label>
                                    <input type="date" name="data_entrega" class="form-control" required
                                        value="<?= date('Y-m-d') ?>">
                                </div>

                                <!-- Hora (tempo real) -->
                                <div class="field">
                                    <label class="form-label">Hora da Entrega</label>
                                    <input type="text" name="hora_entrega" id="hora_entrega"
                                        class="form-control readonly-clean" required readonly>
                                    <div class="help">Atualiza automaticamente (tempo real) pelo horário do dispositivo.
                                    </div>
                                </div>

                                <!-- Responsável -->
                                <div class="field">
                                    <label class="form-label">Responsável (Servidor)</label>
                                    <input type="text" name="responsavel" id="responsavel"
                                        class="form-control readonly-clean" value="<?= e($nomeLogado) ?>" readonly>
                                    <div class="help">Preenchido automaticamente com o usuário logado.</div>
                                </div>

                                <!-- FOTO: campo igual print -->
                                <div class="field col-span-2-md file-like">
                                    <label class="form-label">Foto da Entrega</label>

                                    <input type="file" id="foto_file" accept="image/*" class="d-none">

                                    <div class="input-group">
                                        <input type="text" class="form-control" id="foto_nome"
                                            value="Nenhuma foto selecionada" readonly>
                                        <button type="button" class="btn btn-outline-secondary" id="btnGaleria"
                                            title="Escolher do dispositivo">
                                            <i class="bi bi-image"></i> Galeria
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnEscolherFoto"
                                            title="Usar câmera">
                                            <i class="bi bi-camera"></i> Câmera
                                        </button>
                                    </div>

                                    <div class="hint-line">
                                        Abrirá a câmera (traseira por padrão). Você pode alternar para a frontal.
                                    </div>
                                </div>

                                <!-- Observação -->
                                <div class="field col-span-2-md">
                                    <label class="form-label">Observação</label>
                                    <textarea name="observacao" class="form-control" rows="3"
                                        placeholder="Opcional"></textarea>
                                </div>

                                <!-- Checkbox -->
                                <div class="col-span-2-md">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="marcar_cadastro"
                                            id="marcar_cadastro" checked>
                                        <label class="form-check-label" for="marcar_cadastro">
                                            Marcar no cadastro do solicitante: Benefício ANEXO = “Sim” e atualizar
                                            valor.
                                        </label>
                                    </div>
                                </div>

                                <!-- Ações -->
                                <div class="col-span-2-md actions">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i>
                                        Salvar</button>
                                    <a class="btn btn-outline-secondary" href="beneficiosCadastrados.php"><i
                                            class="bi bi-list"></i> Ver Entregas</a>
                                </div>
                            </form>

                        </div>
                    </div>
                </section>
            </div>

            <footer class="mt-auto py-3 bg-body-tertiary">
                <div
                    class="container d-flex flex-wrap justify-content-between align-items-center gap-3 small text-muted">
                    <div><span id="current-year"></span> &copy; Todos os direitos reservados à <strong
                            class="text-body">Prefeitura Municipal de Coari-AM.</strong></div>
                    <div>Desenvolvido por <strong class="text-body">Junior Praia, Lucas Correa e Luiz Frota.</strong>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- MODAL CÂMERA -->
    <div class="modal fade modal-camera" id="modalCamera" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-camera"></i>
                        <h5 class="modal-title mb-0">Capturar foto</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info mb-3 d-none" id="camInfo">
                        Ao abrir, o navegador vai pedir <strong>permissão</strong> para usar a câmera.
                        Clique em <strong>Permitir</strong>. Depois que permitir, essa mensagem não aparece mais.
                    </div>

                    <div class="cam-wrap">
                        <div class="cam-frame">
                            <!-- ✅ UM FRAME. No review, o vídeo some e a foto ocupa tudo -->
                            <video id="camVideo" autoplay playsinline muted></video>
                            <canvas id="camCanvas"></canvas>
                            <img id="camPhoto" alt="Foto capturada" style="display:none;">
                        </div>

                        <div class="cam-hint">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAlternarCam">
                                <i class="bi bi-arrow-repeat"></i> Alternar câmera
                            </button>
                            <span>Por padrão abrimos a traseira.</span>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3 d-none" id="camWarn"></div>
                </div>

                <div class="modal-footer">
                    <!-- ✅ Estado LIVE: só Tirar foto -->
                    <div class="w-100 d-flex justify-content-end gap-2" id="liveActions">
                        <button type="button" class="btn btn-primary" id="btnTirarFoto">
                            <i class="bi bi-camera"></i> Tirar foto
                        </button>
                    </div>

                    <!-- ✅ Estado REVIEW: só Tirar outra + Usar foto -->
                    <div class="w-100 d-none justify-content-end gap-2" id="reviewActions">
                        <button type="button" class="btn btn-outline-secondary" id="btnTirarOutra">
                            <i class="bi bi-arrow-counterclockwise"></i> Tirar outra
                        </button>
                        <button type="button" class="btn btn-success" id="btnUsarFoto">
                            <i class="bi bi-check-circle"></i> Usar foto
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();

        const escapeHtml = (s) => String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", "&#039;");

        const maskCPF = (cpf) => {
            const d = String(cpf || '').replace(/\D+/g, '');
            if (d.length !== 11) return cpf || '';
            return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        };

        const brMoney = (n) => {
            if (n === null || n === undefined || n === '') return '';
            const v = Number(n);
            if (Number.isNaN(v)) return String(n);
            return v.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        // ===== Autocomplete =====
        const $busca = document.getElementById('buscaPessoa');
        const $box = document.getElementById('resultPessoa');
        const $id = document.getElementById('pessoa_id');
        const $cpfH = document.getElementById('pessoa_cpf');
        const $info = document.getElementById('infoPessoa');
        let debounce;

        function setPessoa(item) {
            $id.value = item.id;
            $cpfH.value = String(item.cpf || '').replace(/\D+/g, '');
            $busca.value = `${item.nome} — ${maskCPF(item.cpf)}`;
            $info.innerHTML = `Selecionado: <strong>${escapeHtml(item.nome)}</strong> — CPF: <strong>${maskCPF(item.cpf)}</strong>${item.endereco ? ' — ' + escapeHtml(item.endereco) : ''}`;
            $box.classList.add('d-none');
            $box.innerHTML = '';
        }

        $busca.addEventListener('input', () => {
            const term = $busca.value.trim();

            if (document.activeElement === $busca) {
                $id.value = '';
                $cpfH.value = '';
            }

            clearTimeout(debounce);
            debounce = setTimeout(async () => {
                if (!term) {
                    $box.classList.add('d-none');
                    $box.innerHTML = '';
                    return;
                }

                try {
                    const url = new URL(window.location.href);
                    url.search = '';
                    url.searchParams.set('searchPessoa', '1');
                    url.searchParams.set('q', term);

                    const res = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const j = await res.json();

                    $box.innerHTML = '';

                    if (j.ok && j.items && j.items.length) {
                        j.items.forEach(it => {
                            const div = document.createElement('div');
                            div.className = 'item';
                            div.innerHTML = `<div><strong>${escapeHtml(it.nome)}</strong> — ${maskCPF(it.cpf)}</div>
                                             <div class="muted">${escapeHtml(it.endereco || '')}${it.telefone ? ' • ' + escapeHtml(it.telefone) : ''}</div>`;
                            div.addEventListener('click', () => setPessoa(it));
                            $box.appendChild(div);
                        });
                        $box.classList.remove('d-none');
                    } else {
                        const div = document.createElement('div');
                        div.className = 'item muted';
                        div.textContent = 'Nenhum resultado';
                        $box.appendChild(div);
                        $box.classList.remove('d-none');
                    }
                } catch (e) {}
            }, 250);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#resultPessoa') && e.target !== $busca) $box.classList.add('d-none');
        });

        // ===== Pré-preencher quantidade/valor =====
        const selAjuda = document.getElementById('ajuda_tipo_id');
        const inpQtd = document.getElementById('quantidade');
        const inpVal = document.getElementById('valor_aplicado');

        selAjuda.addEventListener('change', () => {
            const opt = selAjuda.options[selAjuda.selectedIndex];
            if (!opt || !opt.dataset) return;

            const qtd = Number(opt.dataset.qtd || 1);
            const val = opt.dataset.valor ?? '';

            if (qtd > 0) inpQtd.value = String(qtd);
            if (val !== '' && !isNaN(Number(val))) inpVal.value = brMoney(Number(val));
        });

        // ===== Hora em tempo real =====
        const inpHora = document.getElementById('hora_entrega');
        const pad2 = (n) => String(n).padStart(2, '0');

        function tickHora() {
            const d = new Date();
            inpHora.value = `${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
        }
        tickHora();
        setInterval(tickHora, 250);

        // ===== FOTO + MODAL CÂMERA =====
        const btnEscolherFoto = document.getElementById('btnEscolherFoto');
        const fotoNome = document.getElementById('foto_nome');

        const fileInput = document.getElementById('foto_file'); // hidden
        const hidFoto = document.getElementById('foto_base64');
        const hidMime = document.getElementById('foto_mime');

        const modalEl = document.getElementById('modalCamera');
        const modal = new bootstrap.Modal(modalEl, {
            backdrop: 'static'
        });

        const btnAlternarCam = document.getElementById('btnAlternarCam');
        const btnTirarFoto = document.getElementById('btnTirarFoto');
        const btnTirarOutra = document.getElementById('btnTirarOutra');
        const btnUsarFoto = document.getElementById('btnUsarFoto');

        const camVideo = document.getElementById('camVideo');
        const camCanvas = document.getElementById('camCanvas');
        const camPhoto = document.getElementById('camPhoto');

        const camWarn = document.getElementById('camWarn');
        const camInfo = document.getElementById('camInfo');

        const liveActions = document.getElementById('liveActions');
        const reviewActions = document.getElementById('reviewActions');

        let camStream = null;
        let facingMode = 'environment';
        let lastDataUrl = '';

        function showWarn(msg) {
            camWarn.textContent = msg;
            camWarn.classList.remove('d-none');
        }

        function hideWarn() {
            camWarn.textContent = '';
            camWarn.classList.add('d-none');
        }

        function showInfo() {
            camInfo.classList.remove('d-none');
        }

        function hideInfo() {
            camInfo.classList.add('d-none');
        }

        function setStateLive() {
            lastDataUrl = '';
            camPhoto.src = '';
            camPhoto.style.display = 'none';

            camVideo.style.display = 'block';
            camVideo.classList.remove('d-none');

            reviewActions.classList.add('d-none');
            liveActions.classList.remove('d-none');
        }

        function setStateReview(dataUrl) {
            lastDataUrl = dataUrl || '';
            camPhoto.src = lastDataUrl;

            camVideo.style.display = 'none';
            camVideo.classList.add('d-none');

            camPhoto.style.display = 'block';

            liveActions.classList.add('d-none');
            reviewActions.classList.remove('d-none');
        }

        async function stopCamera() {
            try {
                if (camStream) camStream.getTracks().forEach(t => t.stop());
            } catch (e) {}
            camStream = null;
            camVideo.srcObject = null;
        }

        async function getCameraPermissionState() {
            try {
                if (navigator.permissions && navigator.permissions.query) {
                    const p = await navigator.permissions.query({
                        name: 'camera'
                    });
                    return p.state; // granted | prompt | denied
                }
            } catch (e) {}
            return 'unknown';
        }

        async function startCamera() {
            hideWarn();
            // NÃO parar ao tirar foto, só ao fechar modal.
            // Aqui, quando abrir/trocar, a gente reinicia pra garantir.
            await stopCamera();

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showWarn('Seu navegador não suporta câmera (getUserMedia).');
                showInfo();
                return false;
            }

            const state = await getCameraPermissionState();
            if (state === 'granted') hideInfo();
            else showInfo();

            if (state === 'denied') {
                showWarn('Permissão de câmera BLOQUEADA. Libere no ícone de câmera/cadeado do navegador e tente novamente.');
                return false;
            }

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
                return true;
            } catch (err) {
                console.error(err);

                if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
                    showWarn('Permissão negada. Clique em "Permitir" no popup do navegador, ou libere nas configurações do site (ícone de câmera/cadeado).');
                } else if (err && err.name === 'NotFoundError') {
                    showWarn('Nenhuma câmera encontrada no dispositivo.');
                } else if (err && err.name === 'NotReadableError') {
                    showWarn('A câmera está sendo usada por outro aplicativo (WhatsApp/Zoom/Teams). Feche ele e tente novamente.');
                } else if (err && err.name === 'OverconstrainedError') {
                    showWarn('Não foi possível abrir a câmera solicitada. Tente alternar a câmera.');
                } else {
                    showWarn('Não foi possível acessar a câmera. Verifique permissões do navegador/Windows e se algum app está usando a câmera.');
                }

                showInfo();
                return false;
            }
        }

        function dataURLtoFile(dataUrl, filename) {
            const arr = String(dataUrl || '').split(',');
            const mime = (arr[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
            const bstr = atob(arr[1] || '');
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) u8arr[n] = bstr.charCodeAt(n);
            return new File([u8arr], filename, {
                type: mime
            });
        }

        function setHiddenInputFileFromDataUrl(dataUrl, filename) {
            const file = dataURLtoFile(dataUrl, filename);
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
        }

        function setChosenName(name) {
            fotoNome.value = name && String(name).trim() ? String(name) : 'Nenhuma foto selecionada';
        }

        function applyChosenImage(dataUrl, mime, filename) {
            hidFoto.value = dataUrl || '';
            hidMime.value = mime || '';
            setChosenName(filename || 'Foto capturada.jpg');
            setHiddenInputFileFromDataUrl(dataUrl, filename || 'Foto capturada.jpg');
        }

        // ✅ LOGICA DA GALERIA
        const btnGaleria = document.getElementById('btnGaleria');
        if (btnGaleria) {
            btnGaleria.addEventListener('click', () => {
                hideWarn();
                fileInput.value = ''; // Limpa para permitir selecionar o mesmo arquivo
                fileInput.click();
            });
        }

        // Quando selecionar arquivo (Galeria)
        if (fileInput) {
            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (!file) return;

                // Validações
                if (!file.type || !file.type.startsWith('image/')) {
                    showWarn('Selecione um arquivo de imagem válido (JPG, PNG, WEBP).');
                    showInfo();
                    fileInput.value = '';
                    return;
                }

                // Limite 6MB
                if (file.size > 6 * 1024 * 1024) {
                    showWarn('A imagem selecionada é muito grande (máx 6MB). Escolha uma menor.');
                    showInfo();
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = () => {
                    const dataUrl = String(reader.result || '');
                    applyChosenImage(dataUrl, file.type, file.name);
                    hideWarn();
                    hideInfo();
                };
                reader.onerror = () => {
                    showWarn('Erro ao ler o arquivo de imagem.');
                };
                reader.readAsDataURL(file);
            });
        }

        btnEscolherFoto.addEventListener('click', async () => {
            modal.show();
            setStateLive();
            await startCamera();
        });

        btnAlternarCam.addEventListener('click', async () => {
            facingMode = (facingMode === 'environment') ? 'user' : 'environment';
            setStateLive();
            await startCamera();
        });

        btnTirarFoto.addEventListener('click', () => {
            if (!camVideo.videoWidth || !camVideo.videoHeight) {
                showWarn('Câmera ainda não está pronta. Aguarde um instante e tente novamente.');
                return;
            }

            const w = camVideo.videoWidth;
            const h = camVideo.videoHeight;

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
                ctx.restore();
            } else {
                ctx.drawImage(camVideo, 0, 0, w, h);
            }

            const dataUrl = camCanvas.toDataURL('image/jpeg', 0.88);

            // ✅ REVIEW ocupa O MESMO frame grande (sem preview embaixo)
            setStateReview(dataUrl);
        });

        btnTirarOutra.addEventListener('click', async () => {
            setStateLive();

            // se por algum motivo o stream caiu, reinicia
            if (!camStream) {
                await startCamera();
            } else {
                // garante que o video volte a tocar
                try {
                    await camVideo.play();
                } catch (e) {}
            }
        });

        btnUsarFoto.addEventListener('click', () => {
            if (!lastDataUrl) return;
            applyChosenImage(lastDataUrl, 'image/jpeg', 'Foto capturada.jpg');
            modal.hide();
        });

        modalEl.addEventListener('hidden.bs.modal', async () => {
            await stopCamera();
            hideWarn();
            setStateLive();
        });
    </script>
</body>

</html>