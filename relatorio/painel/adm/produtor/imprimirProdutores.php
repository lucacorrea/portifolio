<?php

declare(strict_types=1);
session_start();

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

/* Obrigatório ser ADMIN */
$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

require '../../../assets/php/conexao.php';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits(string $s): string
{
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

function formatCpf(?string $cpf): string
{
  $cpf = only_digits((string)$cpf);
  if ($cpf === '') {
    return 'CPF não informado';
  }

  if (strlen($cpf) === 11) {
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
  }

  return $cpf;
}

function buildFotoSrc(?string $foto): string
{
  $foto = trim((string)$foto);
  if ($foto === '') {
    return '';
  }

  /* Se já vier absoluta */
  if (
    stripos($foto, 'http://') === 0 ||
    stripos($foto, 'https://') === 0 ||
    stripos($foto, 'data:image/') === 0
  ) {
    return $foto;
  }

  /*
    Ajuste aqui conforme sua estrutura.
    Exemplos possíveis:
    - ../../../storage/produtores/
    - ../../../uploads/produtores/
    - ../../assets/img/produtores/
  */
  return '../../../storage/produtores/' . ltrim($foto, '/');
}

/* Feira padrão desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor')   !== false) $FEIRA_ID = 1;

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$err = '';
$errDetail = '';
$produtores = [];
$modo = (string)($_POST['modo'] ?? '');
$qRaw = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));

$cleanErr = function (string $m): string {
  $m = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $m) ?? $m;
  $m = preg_replace('/\(SQL:\s*.*\)$/', '', $m) ?? $m;
  return trim((string)$m);
};

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Acesso inválido para impressão.');
  }

  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    throw new RuntimeException('Falha de segurança (CSRF). Recarregue a página e tente novamente.');
  }

  $pdo = db();

  $tblP = $pdo->query("SHOW TABLES LIKE 'produtores'")->fetchColumn();
  if (!$tblP) {
    throw new RuntimeException("Tabela 'produtores' não existe neste banco.");
  }

  $tblC = $pdo->query("SHOW TABLES LIKE 'comunidades'")->fetchColumn();
  $hasComunidades = (bool)$tblC;

  /* Detecta se existe coluna foto em produtores */
  $hasFoto = false;
  $colFoto = $pdo->query("SHOW COLUMNS FROM produtores LIKE 'foto'")->fetch(PDO::FETCH_ASSOC);
  if ($colFoto) {
    $hasFoto = true;
  }

  $where = ["p.feira_id = :feira"];
  $params = [':feira' => $FEIRA_ID];

  if ($modo === 'selecionados') {
    $ids = $_POST['produtores'] ?? [];
    if (!is_array($ids) || !$ids) {
      throw new RuntimeException('Selecione pelo menos um produtor para imprimir.');
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids, static fn($v) => $v > 0);

    if (!$ids) {
      throw new RuntimeException('Nenhum produtor válido foi selecionado.');
    }

    $in = [];
    foreach ($ids as $i => $id) {
      $ph = ':id' . $i;
      $in[] = $ph;
      $params[$ph] = $id;
    }

    $where[] = 'p.id IN (' . implode(',', $in) . ')';
  } elseif ($modo === 'todos') {
    $qDigits = only_digits($qRaw);

    if ($qRaw !== '') {
      $parts = [];

      $params[':q_nome'] = '%' . $qRaw . '%';
      $parts[] = "p.nome LIKE :q_nome";

      $params[':q_contato'] = '%' . $qRaw . '%';
      $parts[] = "p.contato LIKE :q_contato";

      $params[':q_doc'] = '%' . $qRaw . '%';
      $parts[] = "p.documento LIKE :q_doc";

      if ($hasComunidades) {
        $params[':q_com'] = '%' . $qRaw . '%';
        $parts[] = "c.nome LIKE :q_com";
      }

      if ($qDigits !== '') {
        $params[':qd_contato'] = '%' . $qDigits . '%';
        $parts[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.contato,' ',''),'-',''),'(',''),')',''),'+','') LIKE :qd_contato";

        $params[':qd_doc'] = '%' . $qDigits . '%';
        $parts[] = "REPLACE(REPLACE(REPLACE(p.documento,'.',''),'-',''),' ','') LIKE :qd_doc";
      }

      $where[] = '(' . implode(' OR ', $parts) . ')';
    }
  } else {
    throw new RuntimeException('Modo de impressão inválido.');
  }

  $whereSql = ' WHERE ' . implode(' AND ', $where);

  $fotoField = $hasFoto ? 'p.foto' : "NULL AS foto";

  if ($hasComunidades) {
    $sql = "SELECT
              p.id,
              p.nome,
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              {$fotoField},
              c.nome AS comunidade
            FROM produtores p
            LEFT JOIN comunidades c
              ON c.id = p.comunidade_id AND c.feira_id = p.feira_id
            {$whereSql}
            ORDER BY p.nome ASC";
  } else {
    $sql = "SELECT
              p.id,
              p.nome,
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              {$fotoField},
              NULL AS comunidade
            FROM produtores p
            {$whereSql}
            ORDER BY p.nome ASC";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;

    if (strpos($k, ':id') === 0) {
      $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
    } else {
      $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
    }
  }

  $stmt->execute();
  $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$produtores) {
    throw new RuntimeException('Nenhum produtor encontrado para impressão.');
  }
} catch (Throwable $e) {
  $err = 'Não foi possível gerar a impressão agora.';
  $errDetail = $cleanErr($e->getMessage());
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Impressão da Lista de Produtores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: #f4f6f8;
      color: #111;
    }

    .screen-actions {
      max-width: 1100px;
      margin: 18px auto 0;
      padding: 0 16px;
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-block;
      border: 1px solid #cfd6dd;
      background: #fff;
      color: #111;
      text-decoration: none;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 14px;
      cursor: pointer;
    }

    .btn-primary {
      background: #111827;
      color: #fff;
      border-color: #111827;
    }

    .wrap {
      max-width: 1100px;
      margin: 16px auto 32px;
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 8px 30px rgba(0,0,0,.08);
    }

    .header {
      border-bottom: 2px solid #111;
      padding-bottom: 14px;
      margin-bottom: 20px;
    }

    .header h1 {
      margin: 0 0 6px;
      font-size: 24px;
    }

    .header .sub {
      font-size: 14px;
      color: #555;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    .card {
      border: 1px solid #d9dee5;
      border-radius: 14px;
      overflow: hidden;
      background: #fff;
      display: flex;
      min-height: 180px;
    }

    .foto-box {
      width: 150px;
      min-width: 150px;
      border-right: 1px solid #e5e7eb;
      background: #fafafa;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px;
    }

    .foto-box img {
      width: 100%;
      height: 160px;
      object-fit: cover;
      border-radius: 10px;
      display: block;
      border: 1px solid #ddd;
    }

    .sem-foto {
      width: 100%;
      height: 160px;
      border: 1px dashed #c7cdd4;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #777;
      font-size: 13px;
      padding: 10px;
      background: #fff;
    }

    .dados {
      flex: 1;
      padding: 14px 16px;
    }

    .nome {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 10px;
      line-height: 1.2;
    }

    .linha {
      margin-bottom: 8px;
      font-size: 14px;
      line-height: 1.35;
    }

    .label {
      font-weight: 700;
      display: inline-block;
      min-width: 98px;
    }

    .status {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 8px;
      border-radius: 999px;
      border: 1px solid #ccc;
    }

    .ativo {
      background: #ecfdf3;
      color: #166534;
      border-color: #bbf7d0;
    }

    .inativo {
      background: #f3f4f6;
      color: #374151;
      border-color: #d1d5db;
    }

    .erro {
      max-width: 900px;
      margin: 30px auto;
      background: #fff1f2;
      border: 1px solid #fecdd3;
      color: #881337;
      padding: 16px;
      border-radius: 12px;
      font-size: 14px;
    }

    .rodape {
      margin-top: 20px;
      font-size: 12px;
      color: #666;
      text-align: right;
    }

    @media (max-width: 900px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .card {
        flex-direction: column;
      }

      .foto-box {
        width: 100%;
        min-width: 100%;
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
      }

      .foto-box img,
      .sem-foto {
        max-width: 180px;
      }
    }

    @media print {
      @page {
        size: A4;
        margin: 10mm;
      }

      body {
        background: #fff;
      }

      .screen-actions {
        display: none !important;
      }

      .wrap {
        max-width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
      }

      .card {
        break-inside: avoid;
        page-break-inside: avoid;
      }

      .header {
        margin-bottom: 14px;
      }

      .grid {
        gap: 12px;
      }
    }
  </style>
</head>
<body>

<?php if ($err !== ''): ?>
  <div class="erro">
    <strong><?= h($err) ?></strong><br>
    <?= h($errDetail) ?>
  </div>
<?php else: ?>
  <div class="screen-actions">
    <div>
      <a href="./selecionarFeirante.php" class="btn">Voltar</a>
    </div>
    <div>
      <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
    </div>
  </div>

  <div class="wrap">
    <div class="header">
      <h1>Lista de Produtores</h1>
      <div class="sub">
        Quantidade de registros: <strong><?= count($produtores) ?></strong>
      </div>
    </div>

    <div class="grid">
      <?php foreach ($produtores as $p): ?>
        <?php
          $fotoSrc = buildFotoSrc($p['foto'] ?? '');
          $cpf = formatCpf((string)($p['documento'] ?? ''));
          $contato = trim((string)($p['contato'] ?? ''));
          $comunidade = trim((string)($p['comunidade'] ?? ''));
          $obs = trim((string)($p['observacao'] ?? ''));
          $ativo = (int)($p['ativo'] ?? 0) === 1;
        ?>
        <div class="card">
          <div class="foto-box">
            <?php if ($fotoSrc !== ''): ?>
              <img
                src="<?= h($fotoSrc) ?>"
                alt="Foto de <?= h((string)$p['nome']) ?>"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="sem-foto" style="display:none;">Sem foto</div>
            <?php else: ?>
              <div class="sem-foto">Sem foto</div>
            <?php endif; ?>
          </div>

          <div class="dados">
            <div class="nome"><?= h((string)$p['nome']) ?></div>

            <div class="linha">
              <span class="label">CPF:</span>
              <span><?= h($cpf) ?></span>
            </div>

            <div class="linha">
              <span class="label">Contato:</span>
              <span><?= h($contato !== '' ? $contato : 'Não informado') ?></span>
            </div>

            <div class="linha">
              <span class="label">Comunidade:</span>
              <span><?= h($comunidade !== '' ? $comunidade : 'Não informada') ?></span>
            </div>

            <div class="linha">
              <span class="label">Status:</span>
              <span class="status <?= $ativo ? 'ativo' : 'inativo' ?>">
                <?= $ativo ? 'Ativo' : 'Inativo' ?>
              </span>
            </div>

            <?php if ($obs !== ''): ?>
              <div class="linha">
                <span class="label">Obs.:</span>
                <span><?= h($obs) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="rodape">
      Emitido em <?= date('d/m/Y H:i') ?>
    </div>
  </div>

  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
      }, 300);
    });
  </script>
<?php endif; ?>

</body>
</html>