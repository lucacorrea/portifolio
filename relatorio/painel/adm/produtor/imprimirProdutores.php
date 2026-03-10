<?php

declare(strict_types=1);
session_start();

/* =========================
   SEGURANÇA
========================= */
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

require '../../../assets/php/conexao.php';

/* =========================
   HELPERS
========================= */

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

    if ($cpf === '' || strlen($cpf) !== 11) {
        return 'CPF não informado';
    }

    return substr($cpf, 0, 3) . '.' .
        substr($cpf, 3, 3) . '.' .
        substr($cpf, 6, 3) . '-' .
        substr($cpf, 9, 2);
}

function fotoSrc(?string $foto): string
{
    $foto = trim((string)$foto);

    if ($foto === '') {
        return '';
    }

    if (
        stripos($foto, 'http://') === 0 ||
        stripos($foto, 'https://') === 0
    ) {
        return $foto;
    }

    return '../../../' . ltrim($foto, '/');
}

/* =========================
   FEIRA
========================= */

$FEIRA_ID = 1;

$dirLower = strtolower((string)__DIR__);

if (strpos($dirLower, 'alternativa') !== false) {
    $FEIRA_ID = 2;
}

if (strpos($dirLower, 'produtor') !== false) {
    $FEIRA_ID = 1;
}

/* =========================
   CSRF
========================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['csrf_token'];

/* =========================
   DADOS
========================= */

$produtores = [];
$err = '';
$errDetail = '';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Acesso inválido.');
    }

    $tokenPost = (string)($_POST['csrf_token'] ?? '');

    if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
        throw new RuntimeException('Falha de segurança (CSRF).');
    }

    $pdo = db();

    $modo = (string)($_POST['modo'] ?? '');

    $where = ["p.feira_id = :feira"];
    $params = [':feira' => $FEIRA_ID];

    /* =========================
     SELECIONADOS
  ========================= */

    if ($modo === 'selecionados') {

        $ids = $_POST['produtores'] ?? [];

        if (!is_array($ids) || !$ids) {
            throw new RuntimeException('Selecione pelo menos um produtor.');
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        $in = [];

        foreach ($ids as $i => $id) {
            $ph = ':id' . $i;
            $in[] = $ph;
            $params[$ph] = $id;
        }

        $where[] = 'p.id IN (' . implode(',', $in) . ')';
    }

    /* =========================
     TODOS
  ========================= */

    if ($modo === 'todos') {

        $q = trim((string)($_POST['q'] ?? ''));

        if ($q !== '') {

            $params[':q'] = '%' . $q . '%';

            $where[] = '(p.nome LIKE :q)';
        }
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    /* =========================
     CONSULTA
  ========================= */

    $sql = "

  SELECT
      p.id,
      p.nome,
      p.contato,
      p.documento,
      p.foto,
      p.ativo,
      p.observacao,
      c.nome AS comunidade

  FROM produtores p

  LEFT JOIN comunidades c
  ON c.id = p.comunidade_id

  $whereSql

  ORDER BY p.nome ASC

  ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {

        if (strpos($k, ':id') === 0) {
            $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v);
        }
    }

    $stmt->execute();

    $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$produtores) {
        throw new RuntimeException('Nenhum produtor encontrado.');
    }
} catch (Throwable $e) {

    $err = 'Erro ao gerar impressão.';
    $errDetail = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <title>Lista de Produtores</title>

    <style>
        body {
            font-family: Arial;
            margin: 0;
            background: #f4f6f8;
        }

        .topo {
            padding: 15px;
            text-align: right;
        }

        .btn {
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #333;
            background: #333;
            color: #fff;
            cursor: pointer;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #fff;
            padding: 20px;
        }

        h1 {
            margin: 0 0 10px 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            display: flex;
            overflow: hidden;
        }

        .foto {
            width: 140px;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #ddd;
        }

        .foto img {
            width: 120px;
            height: 150px;
            object-fit: cover;
        }

        .semfoto {
            font-size: 12px;
            color: #777;
        }

        .info {
            padding: 10px 14px;
            flex: 1;
        }

        .nome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .linha {
            font-size: 14px;
            margin-bottom: 6px;
        }

        .label {
            font-weight: bold;
        }

        .status {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .ativo {
            background: #d1fae5;
        }

        .inativo {
            background: #e5e7eb;
        }

        @media print {

            .topo {
                display: none;
            }

            body {
                background: #fff;
            }

        }
    </style>

</head>

<body>

    <?php if ($err): ?>

        <div style="padding:20px;color:red">

            <strong><?= h($err) ?></strong><br>
            <?= h($errDetail) ?>

        </div>

    <?php else: ?>

        <div class="topo">

            <button onclick="window.print()" class="btn">
                Imprimir
            </button>

        </div>

        <div class="container">

            <h1>Lista de Produtores</h1>

            <p>Total: <strong><?= count($produtores) ?></strong></p>

            <div class="grid">

                <?php foreach ($produtores as $p): ?>

                    <?php
                    $foto = fotoSrc($p['foto'] ?? '');
                    ?>

                    <div class="card">

                        <div class="foto">

                            <?php if ($foto): ?>

                                <img src="<?= h($foto) ?>">

                            <?php else: ?>

                                <div class="semfoto">Sem foto</div>

                            <?php endif; ?>

                        </div>

                        <div class="info">

                            <div class="nome">
                                <?= h($p['nome']) ?>
                            </div>

                            <div class="linha">
                                <span class="label">CPF:</span>
                                <?= h(formatCpf($p['documento'])) ?>
                            </div>

                            <div class="linha">
                                <span class="label">Contato:</span>
                                <?= h($p['contato'] ?: 'Não informado') ?>
                            </div>

                            <div class="linha">
                                <span class="label">Comunidade:</span>
                                <?= h($p['comunidade'] ?: 'Não informada') ?>
                            </div>

                            <div class="linha">
                                <span class="label">Status:</span>

                                <?php if ($p['ativo']): ?>
                                    <span class="status ativo">Ativo</span>
                                <?php else: ?>
                                    <span class="status inativo">Inativo</span>
                                <?php endif; ?>

                            </div>

                            <?php if (!empty($p['observacao'])): ?>

                                <div class="linha">
                                    <span class="label">Obs:</span>
                                    <?= h($p['observacao']) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            }
        </script>

    <?php endif; ?>

</body>

</html>