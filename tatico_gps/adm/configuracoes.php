<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/php/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com banco de dados não disponível.');
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$padrao = [
    'empresa_nome' => 'Tático GPS',
    'empresa_cnpj' => '',
    'empresa_telefone' => '',
    'empresa_email' => '',
    'empresa_endereco' => '',
    'automacao_ativa' => 1,
    'dia_vencimento_padrao' => 10,
    'mensalidade_padrao' => '89.90',
    'multa_atraso' => '2.00',
    'juros_atraso' => '1.00',
    'bloquear_apos_dias' => 7,
    'pix_nome_recebedor' => 'Tático GPS LTDA',
    'pix_tipo_chave' => 'Telefone',
    'pix_chave' => '',
    'mensagem_10_dias' => "Olá, @cliente. Faltam 10 dias para o vencimento da sua mensalidade do Tático GPS.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome",
    'mensagem_5_dias' => "Olá, @cliente. Faltam 5 dias para o vencimento da sua mensalidade.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome",
    'mensagem_dia_vencimento' => "Olá, @cliente. Hoje é o vencimento da sua mensalidade do Tático GPS.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome\n\nAssim que pagar, nos envie o comprovante.",
    'mensagem_7_dias_atraso' => "Olá, @cliente. Sua mensalidade está em atraso há 7 dias.\n\nValor pendente: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome\n\nCaso o pagamento não seja confirmado, seu serviço poderá ser bloqueado.",
    'status_cliente_apos_atraso' => 'Pendente',
    'status_cliente_apos_bloqueio' => 'Bloqueado',
];

try {
    $stmt = $pdo->query("SELECT * FROM configuracoes_automacao ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        $config = $padrao;
    }
} catch (Throwable $e) {
    $config = $padrao;
}

$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="./assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Tático GPS - Automação de Cobrança</title>
    <meta name="description" content="Configurações de automação, cobrança e mensagens do sistema Tático GPS" />

    <link rel="icon" type="image/x-icon" href="./assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="./assets/vendor/js/helpers.js"></script>
    <script src="./assets/js/config.js"></script>

    <style>
    html,
    body {
        height: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .layout-page {
        min-height: 100vh;
    }

    .layout-menu {
        height: 100vh !important;
        overflow: hidden;
        position: sticky;
        top: 0;
    }

    .layout-menu .menu-inner {
        height: calc(100vh - 90px);
        overflow-y: auto !important;
        overflow-x: hidden;
        padding-bottom: 2rem;
    }

    .page-banner p {
        color: #697a8d;
        margin-bottom: 0;
    }

    .settings-card .card-header h5 {
        margin: 0;
    }

    .form-section-title {
        font-size: .95rem;
        font-weight: 700;
        color: #566a7f;
        margin-bottom: .85rem;
        padding-bottom: .45rem;
        border-bottom: 1px solid #eceef1;
    }

    .placeholder-box {
        background: #f8f9fa;
        border: 1px dashed #d9dee3;
        border-radius: 10px;
        padding: 12px;
        font-size: .9rem;
        color: #566a7f;
    }

    .token {
        display: inline-block;
        padding: 4px 8px;
        margin: 3px;
        border-radius: 8px;
        background: #eef5ff;
        color: #0d6efd;
        font-weight: 600;
        font-size: 12px;
    }

    .preview-box {
        background: #fff;
        border: 1px solid #d9dee3;
        border-radius: 12px;
        padding: 14px;
        white-space: pre-line;
        min-height: 140px;
    }

    @media (max-width: 1199.98px) {
        .layout-menu {
            position: fixed;
            z-index: 1100;
        }
    }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="dashboard.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <span class="text-primary">
                                <svg width="25" viewBox="0 0 25 42" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="currentColor"
                                        d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" />
                                </svg>
                            </span>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-2">Tático GPS</span>
                    </a>
                </div>

                <div class="menu-divider mt-0"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="dashboard.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div>Painel Geral</div>
                        </a></li>
                    <li class="menu-item"><a href="clientes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-user"></i>
                            <div>Clientes</div>
                        </a></li>
                    <li class="menu-item"><a href="cobrancas.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-wallet"></i>
                            <div>Cobranças</div>
                        </a></li>
                    <li class="menu-item"><a href="pagamentos.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-credit-card"></i>
                            <div>Pagamentos</div>
                        </a></li>
                    <li class="menu-item"><a href="mensagens.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-message-detail"></i>
                            <div>Mensagens</div>
                        </a></li>
                    <li class="menu-item"><a href="relatorios.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a></li>
                    <li class="menu-item active"><a href="configuracoes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-cog"></i>
                            <div>Automação</div>
                        </a></li>
                    <li class="menu-item"><a href="logout.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-power-off"></i>
                            <div>Sair</div>
                        </a></li>
                </ul>
            </aside>

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base bx bx-menu icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">
                        <div class="navbar-nav align-items-center me-auto">
                            <div class="nav-item d-flex align-items-center">
                                <span class="w-px-22 h-px-22"><i class="icon-base bx bx-cog icon-md"></i></span>
                                <input type="text"
                                    class="form-control border-0 shadow-none ps-1 ps-sm-2 d-md-block d-none"
                                    value="Automação de cobrança e mensagens" readonly />
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Automação do Sistema</h3>
                                        <p>Defina vencimento, PIX e mensagens automáticas que serão enviadas ao cliente
                                            antes e depois do pagamento.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="./php/config/processarDados.php" method="POST" id="formAutomacao">
                            <input type="hidden" name="acao" value="salvar_configuracao_automacao">

                            <div class="row g-4">

                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Dados da Empresa</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nome da empresa</label>
                                                    <input type="text" class="form-control" name="empresa_nome" required
                                                        value="<?= h($config['empresa_nome']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">CNPJ</label>
                                                    <input type="text" class="form-control" name="empresa_cnpj"
                                                        value="<?= h($config['empresa_cnpj']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Telefone</label>
                                                    <input type="text" class="form-control" name="empresa_telefone"
                                                        value="<?= h($config['empresa_telefone']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">E-mail</label>
                                                    <input type="email" class="form-control" name="empresa_email"
                                                        value="<?= h($config['empresa_email']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Endereço</label>
                                                    <input type="text" class="form-control" name="empresa_endereco"
                                                        value="<?= h($config['empresa_endereco']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="card settings-card h-100">
                                        <div class="card-header">
                                            <h5>Regras da Cobrança</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="automacao_ativa"
                                                    name="automacao_ativa" value="1"
                                                    <?= (int)$config['automacao_ativa'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="automacao_ativa">Ativar automação
                                                    de mensagens</label>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Dia padrão de vencimento</label>
                                                    <select class="form-select" name="dia_vencimento_padrao" required>
                                                        <?php for ($i = 1; $i <= 31; $i++): ?>
                                                        <option value="<?= $i ?>"
                                                            <?= (int)$config['dia_vencimento_padrao'] === $i ? 'selected' : '' ?>>
                                                            <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?>
                                                        </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Mensalidade padrão</label>
                                                    <input type="text" class="form-control money-mask"
                                                        name="mensalidade_padrao" required
                                                        value="<?= h(number_format((float)$config['mensalidade_padrao'], 2, ',', '.')) ?>">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Multa por atraso (%)</label>
                                                    <input type="text" class="form-control decimal-mask"
                                                        name="multa_atraso" required
                                                        value="<?= h(number_format((float)$config['multa_atraso'], 2, ',', '.')) ?>">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Juros por atraso (%)</label>
                                                    <input type="text" class="form-control decimal-mask"
                                                        name="juros_atraso" required
                                                        value="<?= h(number_format((float)$config['juros_atraso'], 2, ',', '.')) ?>">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Bloquear após quantos dias de
                                                        atraso</label>
                                                    <input type="number" min="1" class="form-control"
                                                        name="bloquear_apos_dias" required
                                                        value="<?= h((string)$config['bloquear_apos_dias']) ?>">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Status após atraso</label>
                                                    <select class="form-select" name="status_cliente_apos_atraso">
                                                        <option value="Pendente"
                                                            <?= $config['status_cliente_apos_atraso'] === 'Pendente' ? 'selected' : '' ?>>
                                                            Pendente</option>
                                                        <option value="Em cobrança"
                                                            <?= $config['status_cliente_apos_atraso'] === 'Em cobrança' ? 'selected' : '' ?>>
                                                            Em cobrança</option>
                                                        <option value="Atrasado"
                                                            <?= $config['status_cliente_apos_atraso'] === 'Atrasado' ? 'selected' : '' ?>>
                                                            Atrasado</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Status após bloqueio</label>
                                                    <select class="form-select" name="status_cliente_apos_bloqueio">
                                                        <option value="Bloqueado"
                                                            <?= $config['status_cliente_apos_bloqueio'] === 'Bloqueado' ? 'selected' : '' ?>>
                                                            Bloqueado</option>
                                                        <option value="Suspenso"
                                                            <?= $config['status_cliente_apos_bloqueio'] === 'Suspenso' ? 'selected' : '' ?>>
                                                            Suspenso</option>
                                                        <option value="Inadimplente"
                                                            <?= $config['status_cliente_apos_bloqueio'] === 'Inadimplente' ? 'selected' : '' ?>>
                                                            Inadimplente</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="card settings-card h-100">
                                        <div class="card-header">
                                            <h5>PIX do Recebimento</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nome do recebedor</label>
                                                    <input type="text" class="form-control" name="pix_nome_recebedor"
                                                        required value="<?= h($config['pix_nome_recebedor']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Tipo da chave</label>
                                                    <select class="form-select" name="pix_tipo_chave" required>
                                                        <?php
                                                        $tiposPix = ['CPF', 'CNPJ', 'E-mail', 'Telefone', 'Aleatória'];
                                                        foreach ($tiposPix as $tipo):
                                                        ?>
                                                        <option value="<?= h($tipo) ?>"
                                                            <?= $config['pix_tipo_chave'] === $tipo ? 'selected' : '' ?>>
                                                            <?= h($tipo) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Chave PIX</label>
                                                    <input type="text" class="form-control" name="pix_chave" required
                                                        value="<?= h($config['pix_chave']) ?>">
                                                </div>

                                                <div class="col-12">
                                                    <div class="placeholder-box">
                                                        <strong>Variáveis disponíveis nas mensagens:</strong><br>
                                                        <span class="token">@cliente</span>
                                                        <span class="token">@valor</span>
                                                        <span class="token">@vencimento</span>
                                                        <span class="token">@pix_nome</span>
                                                        <span class="token">@pix_chave</span>
                                                        <span class="token">@empresa</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Mensagens Automáticas</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-4">
                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 10 dias antes</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_10_dias"
                                                        required><?= h($config['mensagem_10_dias']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 5 dias antes</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_5_dias"
                                                        required><?= h($config['mensagem_5_dias']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem no dia do vencimento</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_dia_vencimento"
                                                        required><?= h($config['mensagem_dia_vencimento']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 7 dias após o vencimento /
                                                        bloqueio</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_7_dias_atraso"
                                                        required><?= h($config['mensagem_7_dias_atraso']) ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Pré-visualização</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <label class="form-label">Exemplo da mensagem de vencimento</label>
                                                    <div class="preview-box" id="previewMensagem"></div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">Resumo da automação</label>
                                                    <div class="preview-box">
                                                        1. Envia lembrete 10 dias antes.<br>
                                                        2. Envia lembrete 5 dias antes.<br>
                                                        3. Envia mensagem no dia do vencimento.<br>
                                                        4. Se não houver confirmação, envia aviso após 7 dias e pode
                                                        bloquear.<br><br>
                                                        O conteúdo da mensagem já leva PIX e dados do pagamento.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save me-1"></i>Salvar Configurações
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary">Restaurar campos</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl py-4">
                            © <script>
                            document.write(new Date().getFullYear())
                            </script> - Tático GPS
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="./assets/vendor/libs/jquery/jquery.js"></script>
    <script src="./assets/vendor/libs/popper/popper.js"></script>
    <script src="./assets/vendor/js/bootstrap.js"></script>
    <script src="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="./assets/vendor/js/menu.js"></script>
    <script src="./assets/js/main.js"></script>

    <script>
    function aplicarMascaraDecimal(input) {
        input.addEventListener('input', function() {
            let valor = this.value.replace(/[^\d,]/g, '');
            const partes = valor.split(',');
            if (partes.length > 2) {
                valor = partes[0] + ',' + partes[1];
            }
            this.value = valor;
        });
    }

    document.querySelectorAll('.money-mask, .decimal-mask').forEach(aplicarMascaraDecimal);

    function montarPreview() {
        const textarea = document.querySelector('textarea[name="mensagem_dia_vencimento"]');
        const pixNome = document.querySelector('input[name="pix_nome_recebedor"]').value || 'Tático GPS LTDA';
        const pixChave = document.querySelector('input[name="pix_chave"]').value || '(00) 00000-0000';
        const empresa = document.querySelector('input[name="empresa_nome"]').value || 'Tático GPS';
        const valor = document.querySelector('input[name="mensalidade_padrao"]').value || '89,90';

        let texto = textarea.value || '';
        texto = texto
            .replaceAll('@cliente', 'João da Silva')
            .replaceAll('@valor', valor)
            .replaceAll('@vencimento', '10/05/2026')
            .replaceAll('@pix_nome', pixNome)
            .replaceAll('@pix_chave', pixChave)
            .replaceAll('@empresa', empresa);

        document.getElementById('previewMensagem').textContent = texto;
    }

    document.querySelectorAll('textarea, input').forEach(el => {
        el.addEventListener('input', montarPreview);
    });

    montarPreview();
    </script>

    <?php if ($status === 'ok'): ?>
    <script>
    alert('Configurações salvas com sucesso.');
    </script>
    <?php elseif ($status === 'erro' && $msg !== ''): ?>
    <script>
    alert('<?= h($msg) ?>');
    </script>
    <?php endif; ?>
</body>

</html><?php
declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conexao/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com banco de dados não disponível.');
}

function html(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
unset($_SESSION['flash_sucesso']);

$padrao = [
    'empresa_nome' => 'Tático GPS',
    'empresa_cnpj' => '',
    'empresa_telefone' => '',
    'empresa_email' => '',
    'empresa_endereco' => '',
    'automacao_ativa' => 1,
    'dia_vencimento_padrao' => 10,
    'bloquear_apos_dias' => 7,
    'pix_nome_recebedor' => 'Tático GPS LTDA',
    'pix_tipo_chave' => 'Telefone',
    'pix_chave' => '',
    'mensagem_10_dias' => "Olá, @cliente. Faltam 10 dias para o vencimento da sua mensalidade do Tático GPS.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome",
    'mensagem_5_dias' => "Olá, @cliente. Faltam 5 dias para o vencimento da sua mensalidade.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome",
    'mensagem_dia_vencimento' => "Olá, @cliente. Hoje é o vencimento da sua mensalidade do Tático GPS.\n\nValor: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome\n\nAssim que pagar, nos envie o comprovante.",
    'mensagem_7_dias_atraso' => "Olá, @cliente. Sua mensalidade está em atraso há 7 dias.\n\nValor pendente: R$ @valor\nVencimento: @vencimento\nPIX: @pix_chave\nRecebedor: @pix_nome\n\nCaso o pagamento não seja confirmado, seu serviço poderá ser bloqueado.",
    'status_cliente_apos_atraso' => 'Pendente',
    'status_cliente_apos_bloqueio' => 'Bloqueado',
];

try {
    $stmt = $pdo->query("SELECT * FROM configuracoes_automacao ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        $config = $padrao;
    }
} catch (Throwable $e) {
    $config = $padrao;
}
?>
<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="./assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Tático GPS - Automação de Cobrança</title>
    <meta name="description" content="Configurações de automação, cobrança e mensagens do sistema Tático GPS" />

    <link rel="icon" type="image/x-icon" href="./assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="./assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="./assets/vendor/css/core.css" />
    <link rel="stylesheet" href="./assets/css/demo.css" />
    <link rel="stylesheet" href="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="./assets/vendor/js/helpers.js"></script>
    <script src="./assets/js/config.js"></script>

    <style>
    html,
    body {
        height: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .layout-page {
        min-height: 100vh;
    }

    .layout-menu {
        height: 100vh !important;
        overflow: hidden;
        position: sticky;
        top: 0;
    }

    .layout-menu .menu-inner {
        height: calc(100vh - 90px);
        overflow-y: auto !important;
        overflow-x: hidden;
        padding-bottom: 2rem;
    }

    .page-banner p {
        color: #697a8d;
        margin-bottom: 0;
    }

    .settings-card .card-header h5 {
        margin: 0;
    }

    .placeholder-box {
        background: #f8f9fa;
        border: 1px dashed #d9dee3;
        border-radius: 10px;
        padding: 12px;
        font-size: .9rem;
        color: #566a7f;
    }

    .token {
        display: inline-block;
        padding: 4px 8px;
        margin: 3px;
        border-radius: 8px;
        background: #eef5ff;
        color: #0d6efd;
        font-weight: 600;
        font-size: 12px;
    }

    .preview-box {
        background: #fff;
        border: 1px solid #d9dee3;
        border-radius: 12px;
        padding: 14px;
        white-space: pre-line;
        min-height: 140px;
    }

    @media (max-width: 1199.98px) {
        .layout-menu {
            position: fixed;
            z-index: 1100;
        }
    }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="dashboard.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <span class="text-primary">
                                <svg width="25" viewBox="0 0 25 42" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="currentColor"
                                        d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" />
                                </svg>
                            </span>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-2">Tático GPS</span>
                    </a>
                </div>

                <div class="menu-divider mt-0"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="dashboard.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div>Painel Geral</div>
                        </a></li>
                    <li class="menu-item"><a href="clientes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-user"></i>
                            <div>Clientes</div>
                        </a></li>
                    <li class="menu-item"><a href="cobrancas.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-wallet"></i>
                            <div>Cobranças</div>
                        </a></li>
                    <li class="menu-item"><a href="pagamentos.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-credit-card"></i>
                            <div>Pagamentos</div>
                        </a></li>
                    <li class="menu-item"><a href="mensagens.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-message-detail"></i>
                            <div>Mensagens</div>
                        </a></li>
                    <li class="menu-item"><a href="relatorios.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a></li>
                    <li class="menu-item active"><a href="configuracoes.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-cog"></i>
                            <div>Automação</div>
                        </a></li>
                    <li class="menu-item"><a href="logout.php" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-power-off"></i>
                            <div>Sair</div>
                        </a></li>
                </ul>
            </aside>

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base bx bx-menu icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">
                        <div class="navbar-nav align-items-center me-auto">
                            <div class="nav-item d-flex align-items-center">
                                <span class="w-px-22 h-px-22"><i class="icon-base bx bx-cog icon-md"></i></span>
                                <input type="text"
                                    class="form-control border-0 shadow-none ps-1 ps-sm-2 d-md-block d-none"
                                    value="Automação de cobrança e mensagens" readonly />
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <?php if ($flashSucesso): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bx bx-check-circle me-1"></i>
                                    <?= h($flashSucesso) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                                        aria-label="Fechar"></button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Automação do Sistema</h3>
                                        <p>Defina vencimento, PIX e mensagens automáticas que serão enviadas ao cliente
                                            antes e depois do pagamento.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="./php/config/processarDados.php" method="POST" id="formAutomacao">
                            <input type="hidden" name="acao" value="salvar_configuracao_automacao">

                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Dados da Empresa</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nome da empresa</label>
                                                    <input type="text" class="form-control" name="empresa_nome" required
                                                        value="<?= h($config['empresa_nome']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">CNPJ</label>
                                                    <input type="text" class="form-control" name="empresa_cnpj"
                                                        value="<?= h($config['empresa_cnpj']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Telefone</label>
                                                    <input type="text" class="form-control" name="empresa_telefone"
                                                        value="<?= h($config['empresa_telefone']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">E-mail</label>
                                                    <input type="email" class="form-control" name="empresa_email"
                                                        value="<?= h($config['empresa_email']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Endereço</label>
                                                    <input type="text" class="form-control" name="empresa_endereco"
                                                        value="<?= h($config['empresa_endereco']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="card settings-card h-100">
                                        <div class="card-header">
                                            <h5>Regras da Cobrança</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="automacao_ativa"
                                                    name="automacao_ativa" value="1"
                                                    <?= (int)$config['automacao_ativa'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="automacao_ativa">Ativar automação
                                                    de mensagens</label>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Dia padrão de vencimento</label>
                                                    <select class="form-select" name="dia_vencimento_padrao" required>
                                                        <?php for ($i = 1; $i <= 31; $i++): ?>
                                                        <option value="<?= $i ?>"
                                                            <?= (int)$config['dia_vencimento_padrao'] === $i ? 'selected' : '' ?>>
                                                            <?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?>
                                                        </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Bloquear após quantos dias de
                                                        atraso</label>
                                                    <input type="number" min="1" class="form-control"
                                                        name="bloquear_apos_dias" required
                                                        value="<?= h((string)$config['bloquear_apos_dias']) ?>">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Status após atraso</label>
                                                    <select class="form-select" name="status_cliente_apos_atraso">
                                                        <option value="Pendente"
                                                            <?= ($config['status_cliente_apos_atraso'] ?? '') === 'Pendente' ? 'selected' : '' ?>>
                                                            Pendente</option>
                                                        <option value="Em cobrança"
                                                            <?= ($config['status_cliente_apos_atraso'] ?? '') === 'Em cobrança' ? 'selected' : '' ?>>
                                                            Em cobrança</option>
                                                        <option value="Atrasado"
                                                            <?= ($config['status_cliente_apos_atraso'] ?? '') === 'Atrasado' ? 'selected' : '' ?>>
                                                            Atrasado</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Status após bloqueio</label>
                                                    <select class="form-select" name="status_cliente_apos_bloqueio">
                                                        <option value="Bloqueado"
                                                            <?= ($config['status_cliente_apos_bloqueio'] ?? '') === 'Bloqueado' ? 'selected' : '' ?>>
                                                            Bloqueado</option>
                                                        <option value="Suspenso"
                                                            <?= ($config['status_cliente_apos_bloqueio'] ?? '') === 'Suspenso' ? 'selected' : '' ?>>
                                                            Suspenso</option>
                                                        <option value="Inadimplente"
                                                            <?= ($config['status_cliente_apos_bloqueio'] ?? '') === 'Inadimplente' ? 'selected' : '' ?>>
                                                            Inadimplente</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="card settings-card h-100">
                                        <div class="card-header">
                                            <h5>PIX do Recebimento</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nome do recebedor</label>
                                                    <input type="text" class="form-control" name="pix_nome_recebedor"
                                                        required value="<?= h($config['pix_nome_recebedor']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Tipo da chave</label>
                                                    <select class="form-select" name="pix_tipo_chave" required>
                                                        <?php
                                                        $tiposPix = ['CPF', 'CNPJ', 'E-mail', 'Telefone', 'Aleatória'];
                                                        foreach ($tiposPix as $tipo):
                                                        ?>
                                                        <option value="<?= h($tipo) ?>"
                                                            <?= ($config['pix_tipo_chave'] ?? '') === $tipo ? 'selected' : '' ?>>
                                                            <?= h($tipo) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Chave PIX</label>
                                                    <input type="text" class="form-control" name="pix_chave" required
                                                        value="<?= h($config['pix_chave']) ?>">
                                                </div>

                                                <div class="col-12">
                                                    <div class="placeholder-box">
                                                        <strong>Variáveis disponíveis nas mensagens:</strong><br>
                                                        <span class="token">@cliente</span>
                                                        <span class="token">@valor</span>
                                                        <span class="token">@vencimento</span>
                                                        <span class="token">@pix_nome</span>
                                                        <span class="token">@pix_chave</span>
                                                        <span class="token">@empresa</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Mensagens Automáticas</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-4">
                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 10 dias antes</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_10_dias"
                                                        required><?= h($config['mensagem_10_dias']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 5 dias antes</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_5_dias"
                                                        required><?= h($config['mensagem_5_dias']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem no dia do vencimento</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_dia_vencimento"
                                                        required><?= h($config['mensagem_dia_vencimento']) ?></textarea>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label">Mensagem 7 dias após o vencimento /
                                                        bloqueio</label>
                                                    <textarea class="form-control msg-template" rows="6"
                                                        name="mensagem_7_dias_atraso"
                                                        required><?= h($config['mensagem_7_dias_atraso']) ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card settings-card">
                                        <div class="card-header">
                                            <h5>Pré-visualização</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <label class="form-label">Exemplo da mensagem de vencimento</label>
                                                    <div class="preview-box" id="previewMensagem"></div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label class="form-label">Resumo da automação</label>
                                                    <div class="preview-box">
                                                        1. Envia lembrete 10 dias antes.<br>
                                                        2. Envia lembrete 5 dias antes.<br>
                                                        3. Envia mensagem no dia do vencimento.<br>
                                                        4. Se não houver confirmação, envia aviso após 7 dias e pode
                                                        bloquear.<br><br>
                                                        O valor usado nas mensagens deve vir do cadastro do cliente.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save me-1"></i>Salvar Configurações
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary">Restaurar campos</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl py-4">
                            © <script>
                            document.write(new Date().getFullYear())
                            </script> - Tático GPS
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="./assets/vendor/libs/jquery/jquery.js"></script>
    <script src="./assets/vendor/libs/popper/popper.js"></script>
    <script src="./assets/vendor/js/bootstrap.js"></script>
    <script src="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="./assets/vendor/js/menu.js"></script>
    <script src="./assets/js/main.js"></script>

    <script>
    function montarPreview() {
        const textarea = document.querySelector('textarea[name="mensagem_dia_vencimento"]');
        const pixNome = document.querySelector('input[name="pix_nome_recebedor"]').value || 'Tático GPS LTDA';
        const pixChave = document.querySelector('input[name="pix_chave"]').value || '(00) 00000-0000';
        const empresa = document.querySelector('input[name="empresa_nome"]').value || 'Tático GPS';

        let texto = textarea.value || '';
        texto = texto
            .replaceAll('@cliente', 'João da Silva')
            .replaceAll('@valor', '89,90')
            .replaceAll('@vencimento', '10/05/2026')
            .replaceAll('@pix_nome', pixNome)
            .replaceAll('@pix_chave', pixChave)
            .replaceAll('@empresa', empresa);

        document.getElementById('previewMensagem').textContent = texto;
    }

    document.querySelectorAll('textarea, input, select').forEach(el => {
        el.addEventListener('input', montarPreview);
        el.addEventListener('change', montarPreview);
    });

    montarPreview();
    </script>
</body>

</html>