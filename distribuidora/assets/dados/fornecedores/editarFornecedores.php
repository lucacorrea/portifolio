<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

$pdo = pdo();
$flash = flash_get();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('danger', 'ID inválido.');
  redirect_to('../../../fornecedores.php');
}

$st = $pdo->prepare("SELECT * FROM fornecedores WHERE id=?");
$st->execute([$id]);
$r = $st->fetch();

if (!$r) {
  flash_set('danger', 'Fornecedor não encontrado.');
  redirect_to('../../../fornecedores.php');
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" href="../../../assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Editar Fornecedor</title>

  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/lineicons.css" type="text/css" />
  <link rel="stylesheet" href="../../../assets/css/materialdesignicons.min.css" type="text/css" />
  <link rel="stylesheet" href="../../../assets/css/main.css" />

  <style>
    .main-btn.btn-compact { height: 38px !important; padding: 8px 14px !important; font-size: 13px !important; line-height: 1 !important; }
    .form-control.compact, .form-select.compact { height: 38px; padding: 8px 12px; font-size: 13px; }
    .muted { font-size: 12px; color: #64748b; }
    .cardx { border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; background: #fff; overflow: hidden; }
    .cardx .head { padding: 12px 14px; border-bottom: 1px solid rgba(148, 163, 184, .22); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .cardx .body { padding: 14px; }
    .flash-auto-hide { transition: opacity .35s ease, transform .35s ease; }
    .flash-auto-hide.hide { opacity: 0; transform: translateY(-6px); pointer-events: none; }
  </style>
</head>

<body class="bg-light">
  <div class="container py-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-0">Editar fornecedor #<?= (int)$r['id'] ?></h3>
        <div class="muted">Atualize os dados e salve.</div>
      </div>
      <a class="main-btn light-btn btn-hover btn-compact" href="../../../fornecedores.php">
        <i class="lni lni-arrow-left me-1"></i> Voltar
      </a>
    </div>

    <?php if ($flash): ?>
      <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide">
        <?= e((string)$flash['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="cardx">
      <div class="head">
        <div style="font-weight:1000;color:#0f172a;">Dados do fornecedor</div>
        <form action="excluir.php" method="post" onsubmit="return confirm('Excluir este fornecedor?');" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="redirect_to" value="../../../fornecedores.php">
          <button class="main-btn danger-btn-outline btn-hover btn-compact" type="submit">
            <i class="lni lni-trash-can me-1"></i> Excluir
          </button>
        </form>
      </div>

      <div class="body">
        <form action="salvar.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="redirect_to" value="editar.php?id=<?= (int)$r['id'] ?>">

          <div class="row g-2">
            <div class="col-12 col-lg-8">
              <label class="form-label">Nome / Razão Social *</label>
              <input class="form-control compact" name="nome" value="<?= e((string)$r['nome']) ?>" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Status</label>
              <select class="form-select compact" name="status">
                <?php $st = only_status((string)$r['status']); ?>
                <option value="ATIVO" <?= $st==='ATIVO'?'selected':'' ?>>Ativo</option>
                <option value="INATIVO" <?= $st==='INATIVO'?'selected':'' ?>>Inativo</option>
              </select>
            </div>

            <div class="col-12 col-lg-4">
              <label class="form-label">CNPJ/CPF</label>
              <input class="form-control compact" name="doc" value="<?= e((string)($r['doc'] ?? '')) ?>" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Telefone</label>
              <input class="form-control compact" name="tel" value="<?= e((string)($r['tel'] ?? '')) ?>" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">E-mail</label>
              <input class="form-control compact" name="email" value="<?= e((string)($r['email'] ?? '')) ?>" />
            </div>

            <div class="col-12">
              <label class="form-label">Endereço</label>
              <input class="form-control compact" name="endereco" value="<?= e((string)($r['endereco'] ?? '')) ?>" />
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label">Cidade</label>
              <input class="form-control compact" name="cidade" value="<?= e((string)($r['cidade'] ?? '')) ?>" />
            </div>
            <div class="col-12 col-lg-2">
              <label class="form-label">UF</label>
              <input class="form-control compact" name="uf" maxlength="2" value="<?= e((string)($r['uf'] ?? '')) ?>" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Contato (Pessoa)</label>
              <input class="form-control compact" name="contato" value="<?= e((string)($r['contato'] ?? '')) ?>" />
            </div>

            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea class="form-control" name="obs" rows="3" style="border-radius:12px;"><?= e((string)($r['obs'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
              <a class="main-btn light-btn btn-hover btn-compact" href="../../../fornecedores.php">Voltar</a>
              <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                <i class="lni lni-save me-1"></i> Salvar
              </button>
            </div>
          </div>
        </form>
      </div>

    </div>
  </div>

  <script>
    // flash some em 1.5s
    (function(){
      const box = document.getElementById('flashBox');
      if(!box) return;
      setTimeout(()=>{
        box.classList.add('hide');
        setTimeout(()=> box.remove(), 400);
      }, 1500);
    })();
  </script>
</body>
</html>