<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com banco não disponível.');
}

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalizarCodigoBusca(string $codigo): string
{
    return preg_replace('/[\s\-]+/', '', trim($codigo));
}

function moneyBR(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function appBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($scriptName), '/');

    if ($dir === '' || $dir === '.') {
        return '';
    }

    return $dir;
}

function placeholderImagem(): string
{
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900">
    <rect width="100%" height="100%" fill="#eef3f9"/>
    <rect x="100" y="100" width="700" height="700" rx="30" fill="#dbe4ef"/>
    <text x="50%" y="47%" text-anchor="middle" font-family="Arial, sans-serif" font-size="52" fill="#294f87" font-weight="700">SEM IMAGEM</text>
    <text x="50%" y="55%" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" fill="#62708a">Produto sem foto cadastrada</text>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

<?php
function normalizarImagemUrl(?string $imagem): string
{
    $imagem = trim((string)$imagem);

    if ($imagem === '') {
        return placeholderImagem();
    }

    // Absolute URLs or data URIs are returned as is
    if (
        preg_match('#^https?://#i', $imagem) ||
        str_starts_with($imagem, 'data:')
    ) {
        return $imagem;
    }

    // Protocol-relative URLs
    if (str_starts_with($imagem, '//')) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        return ($https ? 'https:' : 'http:') . $imagem;
    }

    // If HTTP_HOST is not set (e.g., when running via file://), use a relative path
    if (empty($_SERVER['HTTP_HOST'] ?? null)) {
        // Ensure the path starts with a slash for proper relative linking
        return str_starts_with($imagem, '/') ? $imagem : '/' . $imagem;
    }

    // Root-relative paths
    if (str_starts_with($imagem, '/')) {
        return baseUrl() . $imagem;
    }

    // Remove leading './' for relative paths
    $imagem = ltrim($imagem, './');

    return baseUrl() . appBasePath() . '/' . $imagem;
}
?>

function primeiraImagemDoCampo(?string $imagens): string
{
    $imagens = trim((string)$imagens);

    if ($imagens === '') {
        return placeholderImagem();
    }

    $json = json_decode($imagens, true);

    if (
        json_last_error() === JSON_ERROR_NONE &&
        is_array($json)
    ) {

        if (
            isset($json['url']) &&
            is_string($json['url']) &&
            trim($json['url']) !== ''
        ) {
            return normalizarImagemUrl($json['url']);
        }

        if (
            isset($json['imagem']) &&
            is_string($json['imagem']) &&
            trim($json['imagem']) !== ''
        ) {
            return normalizarImagemUrl($json['imagem']);
        }

        foreach ($json as $item) {

            if (
                is_string($item) &&
                trim($item) !== ''
            ) {
                return normalizarImagemUrl($item);
            }

            if (is_array($item)) {

                if (
                    !empty($item['url']) &&
                    is_string($item['url'])
                ) {
                    return normalizarImagemUrl($item['url']);
                }

                if (
                    !empty($item['imagem']) &&
                    is_string($item['imagem'])
                ) {
                    return normalizarImagemUrl($item['imagem']);
                }

                if (
                    !empty($item['path']) &&
                    is_string($item['path'])
                ) {
                    return normalizarImagemUrl($item['path']);
                }
            }
        }
    }

    $partes = preg_split('/[\r\n,;|]+/', $imagens);

    if (is_array($partes)) {

        foreach ($partes as $parte) {

            $parte = trim((string)$parte);

            if ($parte !== '') {
                return normalizarImagemUrl($parte);
            }
        }
    }

    return normalizarImagemUrl($imagens);
}

$codigoInformado = trim((string)($_GET['codigo'] ?? ''));
$codigoBusca = normalizarCodigoBusca($codigoInformado);

$produto = null;
$erro = '';

if ($codigoBusca === '') {

    $erro = 'Nenhum código foi informado.';
} else {

    try {

        $sql = "
            SELECT
                id,
                filial_id,
                codigo,
                cean,
                qrcode,
                nome,
                unidade,
                descricao,
                imagens,
                categoria,

                preco_venda,
                preco_venda_2,
                preco_venda_3,
                preco_venda_atacado,

                quantidade,
                estoque_minimo,

                ncm,
                cest

            FROM produtos

            WHERE

                REPLACE(REPLACE(TRIM(COALESCE(cean, '')), ' ', ''), '-', '') = :codigo

                OR REPLACE(REPLACE(TRIM(COALESCE(codigo, '')), ' ', ''), '-', '') = :codigo

                OR REPLACE(REPLACE(TRIM(COALESCE(qrcode, '')), ' ', ''), '-', '') = :codigo

            ORDER BY

                CASE

                    WHEN REPLACE(REPLACE(TRIM(COALESCE(cean, '')), ' ', ''), '-', '') = :codigo THEN 0

                    WHEN REPLACE(REPLACE(TRIM(COALESCE(codigo, '')), ' ', ''), '-', '') = :codigo THEN 1

                    ELSE 2

                END,

                id DESC

            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':codigo', $codigoBusca, PDO::PARAM_STR);

        $stmt->execute();

        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            $erro = 'Produto não encontrado para o código informado.';
        }
    } catch (Throwable $e) {

        $erro = 'Erro ao consultar o produto: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Consulta de Produto</title>

    <style>
        :root {
            --azul: #294f87;
            --azul-escuro: #18365f;
            --amarelo: #f2c318;
            --fundo: #bcc6d5;
            --texto: #243a5a;
            --texto-suave: #6d7b90;
            --verde: #1f9b5f;
            --branco: #ffffff;
            --borda: #d9e2ee;
            --box: #f7faff;
            --sombra: 0 18px 45px rgba(17, 38, 70, .18);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--fundo);
            color: var(--texto);
        }

        body {
            padding: 20px 14px;
        }

        .page {
            min-height: calc(100vh - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 1200px;
            background: #fff;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .top {
            background: linear-gradient(180deg, #264b81 0%, #1b3d6c 100%);
            padding: 34px 20px 30px;
            text-align: center;
            position: relative;
        }

        .top::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 6px;
            background: var(--amarelo);
        }

        .logo-title {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .logo-subtitle {
            color: rgba(255, 255, 255, .92);
            margin-top: 8px;
            font-size: 1rem;
        }

        .content {
            padding: 34px;
        }

        .layout {
            display: grid;
            grid-template-columns: 430px 1fr;
            gap: 30px;
        }

        .image-card {
            background: #f8fbff;
            border: 1px solid var(--borda);
            border-radius: 26px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 520px;
        }

        .image-card img {
            width: 100%;
            max-height: 460px;
            object-fit: contain;
        }

        .info-side {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .badge {
            align-self: flex-start;
            background: #ebf7ef;
            color: var(--verde);
            border: 1px solid #cce8d6;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: .82rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .product-title {
            font-size: 2.4rem;
            line-height: 1.15;
            color: var(--azul-escuro);
            font-weight: 900;
        }

        .stock-highlight {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            background: #eef7ff;
            border: 1px solid #d5e5f7;
            color: var(--azul-escuro);
            border-radius: 18px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 800;
        }

        .stock-highlight strong {
            font-size: 1.15rem;
            color: var(--verde);
        }

        .price-main {
            background: linear-gradient(135deg, #294f87 0%, #18365f 100%);
            color: #fff;
            border-radius: 26px;
            padding: 28px;
            box-shadow: 0 16px 35px rgba(24, 54, 95, .25);
        }

        .price-main small {
            display: block;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: .9rem;
            opacity: .9;
            font-weight: 700;
        }

        .price-main strong {
            display: block;
            font-size: 3.6rem;
            line-height: 1;
            font-weight: 900;
        }

        .prices-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .price-box {
            background: var(--box);
            border: 1px solid var(--borda);
            border-radius: 18px;
            padding: 18px;
        }

        .price-box span {
            display: block;
            font-size: .8rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: var(--texto-suave);
            font-weight: 800;
        }

        .price-box strong {
            font-size: 1.3rem;
            color: var(--azul-escuro);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .meta {
            background: #f8fbff;
            border: 1px solid var(--borda);
            border-radius: 18px;
            padding: 18px;
        }

        .meta span {
            display: block;
            font-size: .78rem;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: var(--texto-suave);
            font-weight: 800;
        }

        .meta strong {
            display: block;
            font-size: 1.05rem;
            color: var(--texto);
            word-break: break-word;
        }

        .description {
            background: #fbfcfe;
            border: 1px solid var(--borda);
            border-radius: 22px;
            padding: 22px;
        }

        .description h3 {
            color: var(--azul-escuro);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 1rem;
        }

        .description p {
            color: var(--texto-suave);
            line-height: 1.7;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-top: 6px;
        }

        .btn {
            height: 58px;
            border-radius: 16px;
            border: none;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .98rem;
            font-weight: 800;
            text-transform: uppercase;
            transition: .2s ease;
        }

        .btn-primary {
            background: var(--azul);
            color: #fff;
        }

        .btn-primary:hover {
            background: #1f416f;
        }

        .btn-secondary {
            background: #e8eef6;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dce5f1;
        }

        .error-box {
            background: #fff5f5;
            border: 1px solid #ffd3d3;
            border-radius: 22px;
            padding: 34px;
            text-align: center;
        }

        .error-box h1 {
            color: #d13e3e;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .error-box p {
            color: #8b4b4b;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        @media (max-width: 980px) {

            .layout {
                grid-template-columns: 1fr;
            }

            .image-card {
                min-height: 320px;
            }

            .product-title {
                font-size: 1.9rem;
            }

            .price-main strong {
                font-size: 2.8rem;
            }

            .prices-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {

            body {
                padding: 10px;
            }

            .content {
                padding: 18px;
            }

            .logo-title {
                font-size: 2rem;
            }

            .product-title {
                font-size: 1.6rem;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .price-main {
                padding: 22px;
            }

            .price-main strong {
                font-size: 2.3rem;
            }

            .image-card {
                padding: 16px;
                min-height: 260px;
            }

            .image-card img {
                max-height: 260px;
            }
        }
    </style>

</head>

<body>

    <div class="page">

        <main class="card">

            <div class="top">

                <div class="logo-title">
                    CENTRO DO ELETRICISTA
                </div>

                <div class="logo-subtitle">
                    Consulta rápida de produtos
                </div>

            </div>

            <div class="content">

                <?php if ($erro !== ''): ?>

                    <div class="error-box">

                        <h1>Produto não localizado</h1>

                        <p><?= h($erro) ?></p>

                        <a
                            href="consulta_produto.php"
                            class="btn btn-primary">

                            Voltar para consulta

                        </a>

                    </div>

                <?php else: ?>

                    <?php

                    $nome = (string)($produto['nome'] ?? '');
                    $codigo = (string)($produto['codigo'] ?? '');
                    $cean = (string)($produto['cean'] ?? '');
                    $categoria = (string)($produto['categoria'] ?? '');
                    $unidade = (string)($produto['unidade'] ?? 'UN');
                    $descricao = trim((string)($produto['descricao'] ?? ''));
                    $quantidade = (float)($produto['quantidade'] ?? 0);

                    $imagem = primeiraImagemDoCampo(
                        (string)($produto['imagens'] ?? '')
                    );

                    $preco1 = (float)($produto['preco_venda'] ?? 0);
                    $preco2 = (float)($produto['preco_venda_2'] ?? 0);
                    $preco3 = (float)($produto['preco_venda_3'] ?? 0);
                    $precoAtacado = (float)($produto['preco_venda_atacado'] ?? 0);

                    ?>

                    <div class="layout">

                        <div class="image-card">

                            <img
                                src="<?= h($imagem) ?>"
                                alt="<?= h($nome) ?>">

                        </div>

                        <div class="info-side">

                            <span class="badge">
                                Produto encontrado
                            </span>

                            <h1 class="product-title">
                                <?= h($nome) ?>
                            </h1>

                            <div class="stock-highlight">

                                Quantidade em estoque:

                                <strong>
                                    <?= number_format($quantidade, 0, ',', '.') ?>
                                </strong>

                            </div>

                            <div class="price-main">

                                <small>
                                    Preço principal
                                </small>

                                <strong>
                                    <?= h(moneyBR($preco1)) ?>
                                </strong>

                            </div>

                            <div class="prices-grid">

                                <div class="price-box">

                                    <span>Preço 2</span>

                                    <strong>
                                        <?= h(moneyBR($preco2)) ?>
                                    </strong>

                                </div>

                                <div class="price-box">

                                    <span>Preço 3</span>

                                    <strong>
                                        <?= h(moneyBR($preco3)) ?>
                                    </strong>

                                </div>

                                <div class="price-box">

                                    <span>Atacado</span>

                                    <strong>
                                        <?= h(moneyBR($precoAtacado)) ?>
                                    </strong>

                                </div>

                            </div>

                            <div class="meta-grid">

                                <div class="meta">

                                    <span>Código interno</span>

                                    <strong>
                                        <?= h($codigo ?: '-') ?>
                                    </strong>

                                </div>

                                <div class="meta">

                                    <span>Código de barras</span>

                                    <strong>
                                        <?= h($cean ?: '-') ?>
                                    </strong>

                                </div>

                                <div class="meta">

                                    <span>Categoria</span>

                                    <strong>
                                        <?= h($categoria ?: '-') ?>
                                    </strong>

                                </div>

                                <div class="meta">

                                    <span>Unidade</span>

                                    <strong>
                                        <?= h($unidade ?: 'UN') ?>
                                    </strong>

                                </div>

                            </div>

                            <div class="description">

                                <h3>
                                    Descrição do produto
                                </h3>

                                <p>

                                    <?= h(
                                        $descricao !== ''
                                            ? $descricao
                                            : 'Sem descrição cadastrada.'
                                    ) ?>

                                </p>

                            </div>

                            <div class="actions">

                                <a
                                    href="consulta_produto.php"
                                    class="btn btn-primary">

                                    Consultar outro produto

                                </a>

                                <a
                                    href="consulta_produto.php"
                                    class="btn btn-secondary">

                                    Voltar

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </main>

    </div>

</body>

</html>