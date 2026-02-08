<?php

declare(strict_types=1);
session_start();

/* Login */
if (empty($_SESSION['usuario_logado'])) {
    header('Location: ../../index.php');
    exit;
}

/* ADMIN */
if (!in_array('ADMIN', $_SESSION['perfis'] ?? [], true)) {
    header('Location: ../operador/index.php');
    exit;
}

$nomeTopo = $_SESSION['usuario_nome'] ?? 'Admin';

require_once '../../assets/php/conexao.php';
$pdo = db();

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* CONFIG */
$TABELA = 'comunidades';

/* Defaults */
$tipo = $_POST['tipo'] ?? '';
$feira_id = $_POST['feira_id'] ?? ''; // para comunidade: 1,2,all
$nome = trim($_POST['nome'] ?? '');
$ativo = $_POST['ativo'] ?? '1';
$observacao = trim($_POST['observacao'] ?? '');

$msgErro = '';
$msgSucesso = '';

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msgErro = 'Token de segurança inválido.';
    }

    if ($msgErro === '' && !in_array($tipo, ['comunidade', 'bairro'], true)) {
        $msgErro = 'Selecione o tipo (Comunidade ou Bairro).';
    }

    if ($msgErro === '' && $nome === '') {
        $msgErro = 'Informe o nome.';
    }

    /**
     * Regra:
     * - Comunidade -> feira_id = 1, 2 ou all (1 e 2)
     * - Bairro     -> feira_id = 3 (automático)
     */
    $feirasDestino = [];
    if ($msgErro === '') {
        if ($tipo === 'bairro') {
            $feirasDestino = [3];
        } else {
            // comunidade
            if ($feira_id === 'all') {
                $feirasDestino = [1, 2];
            } else {
                $fid = (int)$feira_id;
                if (!in_array($fid, [1, 2], true)) {
                    $msgErro = 'Para Comunidade, selecione Feira 1, Feira 2 ou Todas.';
                } else {
                    $feirasDestino = [$fid];
                }
            }
        }
    }

    if ($msgErro === '') {
        $ativo_int = ($ativo === '0') ? 0 : 1;

        try {
            // 1) checar duplicidade em todas as feiras destino
            $stCheck = $pdo->prepare("SELECT COUNT(*) FROM {$TABELA} WHERE nome = :nome AND feira_id = :feira_id");
            $duplicadas = [];

            foreach ($feirasDestino as $fid) {
                $stCheck->execute([
                    ':nome' => $nome,
                    ':feira_id' => $fid
                ]);
                if ((int)$stCheck->fetchColumn() > 0) {
                    $duplicadas[] = $fid;
                }
            }

            if (!empty($duplicadas)) {
                $msgErro = 'Já existe um registro com esse nome na(s) feira(s): ' . implode(', ', $duplicadas) . '.';
            } else {
                // 2) inserir (se forem 2 feiras, faz 2 inserts)
                $pdo->beginTransaction();

                $stIns = $pdo->prepare("
          INSERT INTO {$TABELA}
            (feira_id, nome, ativo, observacao, criado_em, atualizado_em)
          VALUES
            (:feira_id, :nome, :ativo, :observacao, NOW(), NULL)
        ");

                foreach ($feirasDestino as $fid) {
                    $stIns->execute([
                        ':feira_id' => $fid,
                        ':nome' => $nome,
                        ':ativo' => $ativo_int,
                        ':observacao' => ($observacao === '') ? null : $observacao
                    ]);
                }

                $pdo->commit();

                $msgSucesso = ($tipo === 'comunidade' && count($feirasDestino) === 2)
                    ? 'Comunidade cadastrada nas Feiras 1 e 2 com sucesso!'
                    : 'Cadastro realizado com sucesso!';

                // limpar
                $tipo = '';
                $feira_id = '';
                $nome = '';
                $ativo = '1';
                $observacao = '';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao inserir em {$TABELA}: " . $e->getMessage());
            $msgErro = 'Erro ao salvar no banco. Verifique o error_log.';
        }
    }
}

/* Últimos cadastrados */
try {
    $lista = $pdo->query("
    SELECT id, feira_id, nome, ativo, observacao, criado_em
    FROM {$TABELA}
    ORDER BY id DESC
    LIMIT 15
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Erro ao listar últimos: " . $e->getMessage());
    $lista = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SIGRelatórios — Adicionar Comunidade/Bairro</title>

    <link rel="stylesheet" href="../../vendors/feather/feather.css">
    <link rel="stylesheet" href="../../vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="../../vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../../css/vertical-layout-light/style.css">
    <link rel="shortcut icon" href="../../images/3.png" />
</head>

<body>
    <div class="container-scroller">

        <!-- NAVBAR -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
                <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../images/3.png" alt="logo" /></a>
            </div>

            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>

                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
                            <i class="ti-user"></i>
                            <span class="ml-1"><?= h($nomeTopo) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                            <a class="dropdown-item" href="../../controle/auth/logout.php">
                                <i class="ti-power-off text-primary"></i> Sair
                            </a>
                        </div>
                    </li>
                </ul>

                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>

        <div class="container-fluid page-body-wrapper">

            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item ">
                        <a class="nav-link" href="./index.php">
                            <i class="icon-grid menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./produtor/">
                            <i class="ti-shopping-cart menu-icon"></i>
                            <span class="menu-title">Feira do Produtor</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="./alternativa/">
                            <i class="ti-shopping-cart menu-icon"></i>
                            <span class="menu-title">Feira Alternativa</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="./mercado/">
                            <i class="ti-home menu-icon"></i>
                            <span class="menu-title">Mercado Municipal</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="./relatorio/">
                            <i class="ti-agenda menu-icon"></i>
                            <span class="menu-title">Relatórios</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link"  href="./localidades.php">
                            <i class="ti-map menu-icon"></i>
                            <span class="menu-title">Localidades</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
                            <i class="ti-user menu-icon"></i>
                            <span class="menu-title">Usuários</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="ui-basic">
                            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                                <li class="nav-item"><a class="nav-link" href="./users/listaUser.php">Lista de Adicionados</a></li>
                                <li class="nav-item"><a class="nav-link" href="./users/adicionarUser.php">Adicionar Usuários</a></li>
                            </ul>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
                            <i class="ti-headphone-alt menu-icon"></i>
                            <span class="menu-title">Suporte</span>
                        </a>
                    </li>
                </ul>
            </nav>


            <div class="main-panel">
                <div class="content-wrapper">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3 class="font-weight-bold">Adicionar Comunidade / Bairro</h3>
                            <h6 class="font-weight-normal mb-0">
                                Comunidade: Feira 1, Feira 2 ou Todas (1 e 2). Bairro: Feira 3 automático.
                            </h6>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">

                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div>
                                            <h4 class="card-title mb-0">Cadastro</h4>
                                            <p class="card-description mb-0">Preencha tipo, feira (se comunidade), nome e status.</p>
                                        </div>

                                        <a href="./localidades.php" class="btn btn-light btn-sm mt-2 mt-md-0">
                                            <i class="ti-arrow-left"></i> Voltar
                                        </a>
                                    </div>

                                    <hr>

                                    <?php if ($msgErro): ?>
                                        <div class="alert alert-danger"><?= h($msgErro) ?></div>
                                    <?php endif; ?>

                                    <?php if ($msgSucesso): ?>
                                        <div class="alert alert-success"><?= h($msgSucesso) ?></div>
                                    <?php endif; ?>

                                    <form method="post" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Tipo *</label>
                                                    <select class="form-control" name="tipo" id="tipo" required>
                                                        <option value="">Selecione...</option>
                                                        <option value="comunidade" <?= $tipo === 'comunidade' ? 'selected' : '' ?>>Comunidade</option>
                                                        <option value="bairro" <?= $tipo === 'bairro' ? 'selected' : '' ?>>Bairro</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4" id="wrapFeira">
                                                <div class="form-group">
                                                    <label>Feira (somente para Comunidade) *</label>
                                                    <select class="form-control" name="feira_id" id="feira_id">
                                                        <option value="">Selecione a feira...</option>
                                                        <option value="all" <?= $feira_id === 'all' ? 'selected' : '' ?>>Todas (Feira 1 e 2)</option>
                                                        <option value="1" <?= $feira_id === '1' ? 'selected' : '' ?>>Feira 1</option>
                                                        <option value="2" <?= $feira_id === '2' ? 'selected' : '' ?>>Feira 2</option>
                                                    </select>
                                                    <small class="text-muted">Bairro não precisa: será Feira 3 automaticamente.</small>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Status *</label>
                                                    <select class="form-control" name="ativo" required>
                                                        <option value="1" <?= $ativo === '1' ? 'selected' : '' ?>>Ativo</option>
                                                        <option value="0" <?= $ativo === '0' ? 'selected' : '' ?>>Inativo</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label>Nome *</label>
                                                    <input type="text" class="form-control" name="nome" value="<?= h($nome) ?>"
                                                        placeholder="Ex.: Comunidade São Francisco / Centro" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Observação (opcional)</label>
                                                    <input type="text" class="form-control" name="observacao" value="<?= h($observacao) ?>"
                                                        placeholder="Ex.: referência, região...">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap" style="gap:8px;">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti-save mr-1"></i> Salvar
                                            </button>
                                            <button type="reset" class="btn btn-light">
                                                <i class="ti-close mr-1"></i> Limpar
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <footer class="footer">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                        <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
                            © <?= date('Y') ?> SIGRelatórios —
                            <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
                            Todos os direitos reservados.
                        </span>
                    </div>
                </footer>
            </div>

        </div>
    </div>

    <script>
        (function() {
            const tipo = document.getElementById('tipo');
            const wrapFeira = document.getElementById('wrapFeira');
            const feiraSelect = document.getElementById('feira_id');

            function update() {
                const t = (tipo?.value || '');
                const isComunidade = t === 'comunidade';

                wrapFeira.style.display = isComunidade ? '' : 'none';

                if (isComunidade) {
                    feiraSelect.setAttribute('required', 'required');
                } else {
                    feiraSelect.removeAttribute('required');
                    feiraSelect.value = '';
                }
            }

            if (tipo) {
                tipo.addEventListener('change', update);
                update();
            }
        })();
    </script>

    <script src="../../vendors/js/vendor.bundle.base.js"></script>
    <script src="../../js/off-canvas.js"></script>
    <script src="../../js/hoverable-collapse.js"></script>
    <script src="../../js/template.js"></script>
    <script src="../../js/settings.js"></script>
    <script src="../../js/todolist.js"></script>
</body>

</html>