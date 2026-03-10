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
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
    header('Location: ../../operador/index.php');
    exit;
}

require '../../../assets/php/conexao.php';

/* ======================
   HELPERS
====================== */

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits(string $s): string
{
    $out = preg_replace('/\D+/', '', $s);
    return $out !== null ? $out : '';
}

function cpf_format(?string $cpf): string
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

function foto_path(?string $foto): string
{
    $foto = trim((string)$foto);

    if ($foto === '') {
        return '';
    }

    if (
        stripos($foto, 'http://') === 0 ||
        stripos($foto, 'https://') === 0 ||
        stripos($foto, 'data:image/') === 0
    ) {
        return $foto;
    }

    return '../../../' . ltrim($foto, '/');
}

/* ======================
   FEIRA
====================== */

$FEIRA_ID = 1;

$dir = strtolower((string)__DIR__);

if (strpos($dir, 'alternativa') !== false) {
    $FEIRA_ID = 2;
}
if (strpos($dir, 'produtor') !== false) {
    $FEIRA_ID = 1;
}

/* ======================
   CSRF
====================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* ======================
   CONEXÃO / ENTRADA
====================== */

$pdo = db();

$produtores = [];
$err = '';
$errDetail = '';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Acesso inválido para impressão.');
    }

    $tokenPost = (string)($_POST['csrf_token'] ?? '');
    if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
        throw new RuntimeException('Falha de segurança (CSRF).');
    }

    $modo = trim((string)($_POST['modo'] ?? ''));
    $qRaw = trim((string)($_POST['q'] ?? ''));
    $qDigits = only_digits($qRaw);

    $tipo = trim((string)($_POST['tipo'] ?? 'TODOS'));
    $tiposValidos = ['TODOS', 'PRODUTOR RURAL', 'FEIRANTE', 'MARRETEIRO'];
    if (!in_array($tipo, $tiposValidos, true)) {
        $tipo = 'TODOS';
    }

    $tblP = $pdo->query("SHOW TABLES LIKE 'produtores'")->fetchColumn();
    if (!$tblP) {
        throw new RuntimeException("Tabela 'produtores' não existe neste banco.");
    }

    $tblC = $pdo->query("SHOW TABLES LIKE 'comunidades'")->fetchColumn();
    $hasComunidades = (bool)$tblC;

    $colTipo = $pdo->query("SHOW COLUMNS FROM produtores LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
    $hasTipo = (bool)$colTipo;

    $where = ["p.feira_id = :feira"];
    $params = [':feira' => $FEIRA_ID];

    if ($hasTipo && $tipo !== 'TODOS') {
        $where[] = "p.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }

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
        if ($qRaw !== '') {
            $parts = [];

            $params[':q_nome'] = '%' . $qRaw . '%';
            $parts[] = "p.nome LIKE :q_nome";

            if ($hasTipo) {
                $params[':q_tipo_busca'] = '%' . $qRaw . '%';
                $parts[] = "p.tipo LIKE :q_tipo_busca";
            }

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
    $campoTipo = $hasTipo ? "p.tipo" : "'PRODUTOR RURAL' AS tipo";

    if ($hasComunidades) {
        $sql = "SELECT
                    p.id,
                    p.nome,
                    {$campoTipo},
                    p.contato,
                    p.documento,
                    p.foto,
                    p.ativo,
                    p.observacao,
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
                    {$campoTipo},
                    p.contato,
                    p.documento,
                    p.foto,
                    p.ativo,
                    p.observacao,
                    NULL AS comunidade
                FROM produtores p
                {$whereSql}
                ORDER BY p.nome ASC";
    }

    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        if ($k === ':feira') {
            $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
        } elseif (strpos($k, ':id') === 0) {
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
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            background: #f4f6f8;
            color: #111;
        }

        .topo {
            padding: 10px 16px;
            text-align: right;
        }

        .btn {
            padding: 8px 12px;
            background: #333;
            color: #fff;
            border: 0;
            cursor: pointer;
            border-radius: 6px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #fff;
            padding: 20px;
        }

        .cabecalho {
            margin-bottom: 12px;
        }

        .cabecalho-grid {
            display: grid;
            grid-template-columns: 110px 1fr 110px;
            align-items: center;
            gap: 12px;
        }

        .logo-box {
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 12px;
            padding: 8px;
        }

        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .cabecalho-texto {
            text-align: center;
        }

        .l1 {
            font-size: 14px;
            font-weight: bold;
        }

        .l2 {
            font-size: 16px;
            font-weight: bold;
            margin-top: 2px;
        }

        .l3 {
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
        }

        .l4 {
            font-size: 12px;
            margin-top: 4px;
        }

        .titulo {
            text-align: center;
            margin: 12px 0 8px;
            font-size: 18px;
            font-weight: bold;
        }

        .subtitulo {
            text-align: center;
            font-size: 13px;
            margin-bottom: 14px;
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
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .foto {
            width: 140px;
            min-width: 140px;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #ddd;
            padding: 10px;
        }

        .foto img {
            width: 120px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .semfoto {
            width: 120px;
            height: 150px;
            border: 1px dashed #c7cdd4;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 12px;
            color: #777;
            background: #fff;
            border-radius: 8px;
            padding: 8px;
        }

        .info {
            padding: 10px 14px;
            flex: 1;
        }

        .nome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            line-height: 1.25;
        }

        .linha {
            font-size: 14px;
            margin-bottom: 5px;
            line-height: 1.35;
        }

        .label {
            font-weight: bold;
        }

        .status {
            font-size: 12px;
            padding: 3px 7px;
            border-radius: 4px;
            display: inline-block;
        }

        .ativo {
            background: #d1fae5;
        }

        .inativo {
            background: #e5e7eb;
        }

        .erro {
            max-width: 900px;
            margin: 30px auto;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #881337;
            padding: 16px;
            border-radius: 10px;
        }

        hr {
            border: 0;
            border-top: 1px solid #bbb;
            margin: 14px 0 10px;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .cabecalho-grid {
                grid-template-columns: 1fr;
            }

            .logo-box {
                margin: 0 auto;
            }

            .card {
                flex-direction: column;
            }

            .foto {
                width: 100%;
                min-width: 100%;
                border-right: 0;
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

            .topo {
                display: none;
            }

            .container {
                max-width: 50%;
                margin: 0;
                padding: 0;
            }

            .logo-box {
                
                color: transparent;
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

    <div class="topo">
        <button onclick="window.print()" class="btn">Imprimir</button>
    </div>

    <div class="container">

        <div class="cabecalho">
            <div class="cabecalho-grid">
                <div class="logo-box">
                   <img src="../../../images/prefeitura.png" alt="Logo da Prefeitura">
                </div>

                <div class="cabecalho-texto">
                    <div class="l1">ESTADO DO AMAZONAS</div>
                    <div class="l2">PREFEITURA MUNICIPAL DE COARI</div>
                    <div class="l3">SECRETARIA MUNICIPAL DE DES. RURAL E ECONOMICO</div>
                    <div class="l3">SECRETARIA ADJUNTA DE FEIRAS E NERCADO</div>
                    <div class="l4">Rua Indepedência, S/N – Bairro Centro – Coari – AM – CEP: 69460-000</div>
                </div>

                <div class="logo-box">
                
                </div>
            </div>
        </div>

        <hr>

        <div class="titulo">LISTA DE PRODUTORES DA FEIRA</div>
        <div class="subtitulo">
            Total: <strong><?= count($produtores) ?></strong>
        </div>

        <div class="grid">
            <?php foreach ($produtores as $p): ?>
                <?php $foto = foto_path($p['foto'] ?? ''); ?>

                <div class="card">
                    <div class="foto">
                        <?php if ($foto !== ''): ?>
                            <img src="<?= h($foto) ?>" alt="Foto de <?= h((string)$p['nome']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="semfoto" style="display:none;">Sem foto</div>
                        <?php else: ?>
                            <div class="semfoto">Sem foto</div>
                        <?php endif; ?>
                    </div>

                    <div class="info">
                        <div class="nome"><?= h((string)$p['nome']) ?></div>

                        <div class="linha">
                            <span class="label">Tipo:</span>
                            <?= h((string)($p['tipo'] ?? 'PRODUTOR RURAL')) ?>
                        </div>

                        <div class="linha">
                            <span class="label">CPF:</span>
                            <?= h(cpf_format((string)($p['documento'] ?? ''))) ?>
                        </div>

                        <div class="linha">
                            <span class="label">Contato:</span>
                            <?= h(trim((string)($p['contato'] ?? '')) !== '' ? (string)$p['contato'] : 'Não informado') ?>
                        </div>

                        <div class="linha">
                            <span class="label">Comunidade:</span>
                            <?= h(trim((string)($p['comunidade'] ?? '')) !== '' ? (string)$p['comunidade'] : 'Não informada') ?>
                        </div>

                        <div class="linha">
                            <span class="label">Status:</span>
                            <?php if ((int)($p['ativo'] ?? 0) === 1): ?>
                                <span class="status ativo">Ativo</span>
                            <?php else: ?>
                                <span class="status inativo">Inativo</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($p['observacao'])): ?>
                            <div class="linha">
                                <span class="label">Obs:</span>
                                <?= h((string)$p['observacao']) ?>
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
        };
    </script>

<?php endif; ?>

</body>
</html>