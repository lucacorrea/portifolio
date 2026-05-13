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

function normalizarImagemUrl(?string $imagem): string
{
    $imagem = trim((string)$imagem);

    if ($imagem === '') {
        return placeholderImagem();
    }

    if (
        preg_match('#^https?://#i', $imagem) ||
        str_starts_with($imagem, 'data:')
    ) {
        return $imagem;
    }

    if (str_starts_with($imagem, '//')) {

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        return ($https ? 'https:' : 'http:') . $imagem;
    }

    if (str_starts_with($imagem, '/')) {
        return baseUrl() . $imagem;
    }

    $imagem = ltrim($imagem, './');

    return baseUrl() . appBasePath() . '/' . $imagem;
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Produto</title>

    <style>
        :root {
            --azul: #294f87;
            --azul-escuro: #18365f;
            --amarelo: #f2c318;
            --fundo: #c1cad8;
            --texto: #243a5a;
            --texto-suave: #687892;
            --branco: #ffffff;
            --verde: #1fa463;
            --cinza-box: #f5f8fc;
            --borda: #dbe3ee;
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
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .top {
            background: var(--azul);
            padding: 30px 20px;
            text-align: center;
        }

        .top img {
            max-width: 280px;
            width: 100%;
            object-fit: contain;
        }

        .bar {
            height: 6px;
            background: var(--amarelo);
        }

        .content {
            padding: 32px;
        }

        .error-box {
            background: #fff5f5;
            border: 1px solid #ffd2d2;
            border-radius: 22px;
            padding: 34px;
            text-align: center;
        }

        .error-box h1 {
            color: #d14040;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .error-box p {
            color: #8b4b4b;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .layout {
            display: grid;
            grid-template-columns: 430px 1fr;
            gap: 28px;
        }

        .image-card {
            background: #f8fbff;
            border: 1px solid var(--borda);
            border-radius: 24px;
            padding: 24px;
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-card img {
            width: 100%;
            max-height: 440px;
            object-fit: contain;
        }

        .info-side {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .badge {
            align-self: flex-start;
            background: #eaf7ef;
            color: var(--verde);
            border: 1px solid #cae8d5;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: .82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .product-title {
            font-size: 2.3rem;
            line-height: 1.2;
            font-weight: 900;
            color: var(--azul-escuro);
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
            font-size: .95rem;
            text-transform: uppercase;
            opacity: .9;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .price-main strong {
            display: block;
            font-size: 3.5rem;
            line-height: 1;
            font-weight: 900;
        }

        .prices-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .price-box {
            background: var(--cinza-box);
            border: 1px solid var(--borda);
            border-radius: 18px;
            padding: 18px;
        }

        .price-box span {
            display: block;
            font-size: .82rem;
            text-transform: uppercase;
            color: var(--texto-suave);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .price-box strong {
            font-size: 1.35rem;
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
            padding: 16px;
        }

        .meta span {
            display: block;
            font-size: .78rem;
            text-transform: uppercase;
            color: var(--texto-suave);
            margin-bottom: 6px;
            font-weight: 800;
        }

        .meta strong {
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
            margin-bottom: 10px;
            color: var(--azul-escuro);
            text-transform: uppercase;
            font-size: 1rem;
        }

        .description p {
            color: var(--texto-suave);
            line-height: 1.7;
        }

        .footer-stock {
            margin-top: 24px;
            background: #eef6ff;
            border: 1px solid #d5e4f5;
            border-radius: 18px;
            padding: 18px;
            text-align: center;
            font-size: 1rem;
        }

        .footer-stock strong {
            color: var(--azul-escuro);
        }

        .actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            min-width: 220px;
            height: 58px;
            border-radius: 16px;
            border: none;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: #e9eef6;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dce5f1;
        }

        @media (max-width: 980px) {

            .layout {
                grid-template-columns: 1fr;
            }

            .image-card {
                min-height: 340px;
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

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                min-width: unset;
            }

            .price-main {
                padding: 22px;
            }

            .price-main strong {
                font-size: 2.3rem;
            }

            .product-title {
                font-size: 1.55rem;
            }
        }
    </style>
</head>

<body>

    <div class="page">

        <main class="card">

            <div class="top">
                <img
                    src="assets/img/logo-centro-eletricista.png"
                    alt="Centro do Eletricista">
            </div>

            <div class="bar"></div>

            <div class="content">

                <?php if ($erro !== ''): ?>

                    <div class="error-box">

                        <h1>Produto não localizado</h1>

                        <p><?= h($erro) ?></p>

                        <a href="consulta_produto.php" class="btn btn-primary">
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

                    $quantidade = (int)($produto['quantidade'] ?? 0);

                    $imagem = primeiraImagemDoCampo((string)($produto['imagens'] ?? ''));

                    $preco1 = (float)($produto['preco_venda'] ?? 0);

                    $preco2 = (float)($produto['preco_venda_2'] ?? 0);

                    $preco3 = (float)($produto['preco_venda_3'] ?? 0);

                    $precoAtacado = (float)($produto['preco_venda_atacado'] ?? 0);

                    ?>

                    <div class="layout">

                        <div class="image-card">
                            <img src="<?= h($imagem) ?>" alt="<?= h($nome) ?>">
                        </div>

                        <div class="info-side">

                            <span class="badge">
                                Produto encontrado
                            </span>

                            <h1 class="product-title">
                                <?= h($nome) ?>
                            </h1>

                            <div class="price-main">

                                <small>Preço principal</small>

                                <strong>
                                    <?= h(moneyBR($preco1)) ?>
                                </strong>

                            </div>

                            <div class="prices-grid">

                                <div class="price-box">
                                    <span>Preço 2</span>
                                    <strong><?= h(moneyBR($preco2)) ?></strong>
                                </div>

                                <div class="price-box">
                                    <span>Preço 3</span>
                                    <strong><?= h(moneyBR($preco3)) ?></strong>
                                </div>

                                <div class="price-box">
                                    <span>Atacado</span>
                                    <strong><?= h(moneyBR($precoAtacado)) ?></strong>
                                </div>

                            </div>

                            <div class="meta-grid">

                                <div class="meta">
                                    <span>Código interno</span>
                                    <strong><?= h($codigo ?: '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Código de barras</span>
                                    <strong><?= h($cean ?: '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Categoria</span>
                                    <strong><?= h($categoria ?: '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Unidade</span>
                                    <strong><?= h($unidade ?: 'UN') ?></strong>
                                </div>

                            </div>

                            <div class="description">

                                <h3>Descrição do produto</h3>

                                <p>
                                    <?= h($descricao !== '' ? $descricao : 'Sem descrição cadastrada.') ?>
                                </p>

                            </div>

                            <div class="actions">

                                <a href="consulta_produto.php" class="btn btn-primary">
                                    Consultar outro produto
                                </a>

                                <a href="javascript:history.back()" class="btn btn-secondary">
                                    Voltar
                                </a>

                            </div>

                        </div>

                    </div>

                    <div class="footer-stock">

                        Estoque disponível:
                        <strong><?= (int)$quantidade ?></strong>
                        unidade(s)

                    </div>

                <?php endif; ?>

            </div>

        </main>

    </div>

</body>

</html>