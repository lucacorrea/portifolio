<?php

declare(strict_types=1);
session_start();

/* ======================
   SEGURANÇA
====================== */

if (empty($_SESSION['usuario_logado'])) {
    header('Location: ../../../index.php');
    exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!in_array('ADMIN', $perfis, true)) {
    header('Location: ../../operador/index.php');
    exit;
}

require '../../../assets/php/conexao.php';

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits($s)
{
    return preg_replace('/\D+/', '', $s);
}

function cpf_format($cpf)
{
    $cpf = only_digits($cpf);

    if ($cpf === '' || strlen($cpf) !== 11) {
        return 'CPF não informado';
    }

    return substr($cpf, 0, 3) . '.' .
        substr($cpf, 3, 3) . '.' .
        substr($cpf, 6, 3) . '-' .
        substr($cpf, 9, 2);
}

function foto_path($foto)
{
    if (!$foto) return '';

    return '../../../' . ltrim($foto, '/');
}

/* ======================
   FEIRA
====================== */

$FEIRA_ID = 1;

$dir = strtolower(__DIR__);

if (strpos($dir, 'alternativa') !== false) {
    $FEIRA_ID = 2;
}

/* ======================
   CONEXÃO
====================== */

$pdo = db();

/* ======================
   FILTRO
====================== */

$tipo = $_GET['tipo'] ?? 'TODOS';

$where = "WHERE p.feira_id = :feira";
$params = [':feira' => $FEIRA_ID];

if ($tipo !== 'TODOS') {
    $where .= " AND p.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

/* ======================
   QUERY
====================== */

$sql = "

SELECT
p.id,
p.nome,
p.tipo,
p.contato,
p.documento,
p.foto,
p.ativo,
p.observacao,
c.nome comunidade

FROM produtores p

LEFT JOIN comunidades c
ON c.id = p.comunidade_id

$where

ORDER BY p.nome ASC

";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->execute();

$produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">

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
            padding: 10px;
            text-align: right;
        }

        .btn {
            padding: 8px 12px;
            background: #333;
            color: #fff;
            border: 0;
            cursor: pointer;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #fff;
            padding: 20px;
        }

        .cabecalho {
            text-align: center;
            margin-bottom: 15px;
        }

        .l1 {
            font-size: 14px;
            font-weight: bold;
        }

        .l2 {
            font-size: 16px;
            font-weight: bold;
        }

        .l3 {
            font-size: 14px;
            font-weight: bold;
        }

        .l4 {
            font-size: 12px;
        }

        .titulo {
            text-align: center;
            margin: 10px 0;
            font-size: 18px;
            font-weight: bold;
        }

        .filtro {
            margin-bottom: 15px;
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
            margin-bottom: 6px;
        }

        .linha {
            font-size: 14px;
            margin-bottom: 4px;
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

            .topo,
            .filtro {
                display: none;
            }

            body {
                background: #fff;
            }

        }
    </style>

</head>

<body>

    <div class="topo">
        <button onclick="window.print()" class="btn">Imprimir</button>
    </div>

    <div class="container">

        <div class="cabecalho">

            <div class="l1">ESTADO DO AMAZONAS</div>
            <div class="l2">PREFEITURA MUNICIPAL DE COARI</div>
            <div class="l3">SECRETARIA MUNICIPAL DE TERRAS E HABITAÇÃO</div>
            <div class="l4">Rua Rio Aroã, nº 127C – Bairro Santa Efigênia – Coari – AM – CEP: 69460-000</div>

        </div>

        <hr>

        <div class="titulo">
            LISTA DE PRODUTORES DA FEIRA
        </div>

       

        <p>Total: <strong><?= count($produtores) ?></strong></p>

        <div class="grid">

            <?php foreach ($produtores as $p): ?>

                <?php $foto = foto_path($p['foto'] ?? ''); ?>

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
                            <span class="label">Tipo:</span>
                            <?= h($p['tipo']) ?>
                        </div>

                        <div class="linha">
                            <span class="label">CPF:</span>
                            <?= h(cpf_format($p['documento'])) ?>
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

</body>

</html>