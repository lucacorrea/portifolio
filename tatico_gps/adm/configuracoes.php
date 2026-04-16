<?php

declare(strict_types=1);

session_start();

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
  } else {
    $config = array_merge($padrao, $config);
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

  <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

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

      <?php $paginaAtiva = 'configuracoes'; ?>
      <?php require_once __DIR__ . '/includes/menu.php'; ?>

      <div class="layout-page">
        <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
          </div>

          <ul class="navbar-nav flex-row align-items-center ms-md-auto">

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
              <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);"
                data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                  <img src="../assets/img/avatars/1.png" alt
                    class="w-px-40 h-auto rounded-circle" />
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="#">
                    <div class="d-flex">
                      <div class="flex-shrink-0 me-3">
                        <div class="avatar avatar-online">
                          <img src="../assets/img/avatars/1.png" alt
                            class="w-px-40 h-auto rounded-circle" />
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Administrador</h6>
                        <small class="text-body-secondary">Tático GPS</small>
                      </div>
                    </div>
                  </a>
                </li>
                <li>
                  <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                  <a class="dropdown-item" href="#">
                    <i class="icon-base bx bx-user icon-md me-3"></i><span>Meu Perfil</span>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="#">
                    <i class="icon-base bx bx-cog icon-md me-3"></i><span>Configurações</span>
                  </a>
                </li>
                <li>
                  <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                  <a class="dropdown-item" href="#">
                    <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Sair</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
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
                                <?= h($tipo) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-12">
                          <label class="form-label">Chave PIX</label>
                          <input type="text" class="form-control" name="pix_chave" required
                            value="<?= h($config['pix_chave']) ?>">
                        </div>

                        <div class="col-12">
                          <label class="form-label">Chave API do Google Gemini (IA para leitura de fotos)</label>
                          <input type="password" class="form-control" name="gemini_api_key" placeholder="Cole aqui sua chave do Gemini"
                            value="<?= h($config['gemini_api_key'] ?? '') ?>">
                          <small class="text-muted">Obtenha em <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></small>
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

          <?php require_once __DIR__ . '/includes/footer.php'; ?>

        </div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

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
