<?php

declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

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

    if (preg_match('#^https?://#i', $imagem) || str_starts_with($imagem, 'data:')) {
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

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        if (isset($json['url']) && is_string($json['url']) && trim($json['url']) !== '') {
            return normalizarImagemUrl($json['url']);
        }

        if (isset($json['imagem']) && is_string($json['imagem']) && trim($json['imagem']) !== '') {
            return normalizarImagemUrl($json['imagem']);
        }

        foreach ($json as $item) {
            if (is_string($item) && trim($item) !== '') {
                return normalizarImagemUrl($item);
            }

            if (is_array($item)) {
                if (!empty($item['url']) && is_string($item['url'])) {
                    return normalizarImagemUrl($item['url']);
                }

                if (!empty($item['imagem']) && is_string($item['imagem'])) {
                    return normalizarImagemUrl($item['imagem']);
                }

                if (!empty($item['path']) && is_string($item['path'])) {
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
                nome,
                unidade,
                descricao,
                imagens,
                categoria,
                preco_venda,
                quantidade
            FROM produtos
            WHERE
                REPLACE(REPLACE(TRIM(COALESCE(cean, '')), ' ', ''), '-', '') = :codigo
                OR REPLACE(REPLACE(TRIM(COALESCE(codigo, '')), ' ', ''), '-', '') = :codigo
            ORDER BY
                CASE
                    WHEN REPLACE(REPLACE(TRIM(COALESCE(cean, '')), ' ', ''), '-', '') = :codigo THEN 0
                    ELSE 1
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
        $erro = 'Erro ao consultar o produto.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produto Consultado</title>

    <style>
        :root {
            --azul: #294f87;
            --azul-escuro: #18365f;
            --azul-fundo: #b9c4d3;
            --amarelo: #f2c318;
            --branco: #ffffff;
            --texto: #243a5a;
            --texto-suave: #6a7890;
            --borda: #d8dfeb;
            --fundo-box: #f6f9fd;
            --verde: #1f9d55;
            --vermelho: #cf4242;
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
            background: var(--azul-fundo);
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
            max-width: 980px;
            background: #fff;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: var(--sombra);
        }

        .top {
            background: var(--azul);
            padding: 28px 20px 24px;
            text-align: center;
        }

        .logo-wrap {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-wrap img {
            max-width: 280px;
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .logo-fallback {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .logo-fallback strong {
            font-size: 1.7rem;
        }

        .logo-fallback span {
            margin-top: 8px;
            opacity: .92;
        }

        .bar {
            height: 6px;
            background: var(--amarelo);
        }

        .content {
            padding: 30px 28px 28px;
        }

        .error-box {
            padding: 34px 24px;
            border: 1px solid #ffd3d3;
            background: #fff4f4;
            border-radius: 20px;
            text-align: center;
        }

        .error-box h1 {
            font-size: 1.8rem;
            color: var(--vermelho);
            margin-bottom: 8px;
        }

        .error-box p {
            color: #8b4a4a;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 26px;
            align-items: stretch;
        }

        .image-card {
            background: #f8fbff;
            border: 1px solid #e2e9f2;
            border-radius: 22px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 420px;
        }

        .image-card img {
            max-width: 100%;
            max-height: 380px;
            object-fit: contain;
            display: block;
        }

        .info {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .badge {
            align-self: flex-start;
            background: #ebf7ef;
            color: var(--verde);
            border: 1px solid #cbe9d4;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: .84rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .product-name {
            font-size: 2.15rem;
            line-height: 1.18;
            font-weight: 900;
            color: var(--azul-escuro);
        }

        .price-highlight {
            background: linear-gradient(135deg, #294f87 0%, #18365f 100%);
            color: #fff;
            border-radius: 24px;
            padding: 24px 24px;
            box-shadow: 0 12px 28px rgba(24, 54, 95, .20);
        }

        .price-highlight small {
            display: block;
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: .88;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .price-highlight strong {
            display: block;
            font-size: 3.2rem;
            line-height: 1;
            font-weight: 900;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .meta {
            background: var(--fundo-box);
            border: 1px solid #e4ebf3;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .meta span {
            display: block;
            font-size: .78rem;
            color: var(--texto-suave);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 5px;
            font-weight: 800;
        }

        .meta strong {
            display: block;
            font-size: 1.05rem;
            color: var(--texto);
            font-weight: 800;
            word-break: break-word;
        }

        .desc-box {
            background: #fbfcfe;
            border: 1px solid #e5ebf3;
            border-radius: 18px;
            padding: 18px 18px;
        }

        .desc-box h3 {
            font-size: 1rem;
            color: var(--azul-escuro);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .desc-box p {
            line-height: 1.7;
            color: var(--texto-suave);
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 2px;
        }

        .btn {
            min-width: 220px;
            height: 56px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            cursor: pointer;
            transition: .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--azul);
            color: #fff;
        }

        .btn-primary:hover {
            background: #214273;
        }

        .btn-secondary {
            background: #e9eef5;
            color: var(--azul-escuro);
        }

        .btn-secondary:hover {
            background: #dce5f1;
        }

        .footer-note {
            margin-top: 22px;
            text-align: center;
            color: var(--texto-suave);
            font-size: .93rem;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .image-card {
                min-height: 300px;
            }

            .product-name {
                font-size: 1.8rem;
            }

            .price-highlight strong {
                font-size: 2.6rem;
            }
        }

        @media (max-width: 640px) {
            .content {
                padding: 24px 16px 20px;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                min-width: unset;
            }

            .price-highlight strong {
                font-size: 2.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <main class="card">
            <div class="top">
                <div class="logo-wrap">
                    <img
                        src="assets/img/logo-centro-eletricista.png"
                        alt="Centro do Eletricista"
                        onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                    <div class="logo-fallback" id="logoFallback">
                        <strong>CENTRO DO ELETRICISTA</strong>
                        <span>Consulta rápida de produtos</span>
                    </div>
                </div>
            </div>

            <div class="bar"></div>

            <div class="content">
                <?php if ($erro !== ''): ?>
                    <div class="error-box">
                        <h1>Produto não localizado</h1>
                        <p><?= h($erro) ?></p>
                        <a href="consulta_produto.php" class="btn btn-primary">Voltar para consulta</a>
                    </div>
                <?php else: ?>
                    <?php
                    $nome       = (string)($produto['nome'] ?? '');
                    $codigo     = (string)($produto['codigo'] ?? '');
                    $cean       = (string)($produto['cean'] ?? '');
                    $categoria  = (string)($produto['categoria'] ?? '');
                    $unidade    = (string)($produto['unidade'] ?? 'UN');
                    $descricao  = trim((string)($produto['descricao'] ?? ''));
                    $quantidade = (int)($produto['quantidade'] ?? 0);
                    $preco      = (float)($produto['preco_venda'] ?? 0);
                    $imagem     = primeiraImagemDoCampo((string)($produto['imagens'] ?? ''));
                    ?>

                    <div class="grid">
                        <div class="image-card">
                            <img src="<?= h($imagem) ?>" alt="<?= h($nome) ?>">
                        </div>

                        <div class="info">
                            <span class="badge">Produto consultado</span>

                            <h1 class="product-name"><?= h($nome) ?></h1>

                            <div class="price-highlight">
                                <small>Valor do produto</small>
                                <strong><?= h(moneyBR($preco)) ?></strong>
                            </div>

                            <div class="meta-grid">
                                <div class="meta">
                                    <span>Código interno</span>
                                    <strong><?= h($codigo !== '' ? $codigo : '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Código de barras</span>
                                    <strong><?= h($cean !== '' ? $cean : '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Categoria</span>
                                    <strong><?= h($categoria !== '' ? $categoria : '-') ?></strong>
                                </div>

                                <div class="meta">
                                    <span>Unidade</span>
                                    <strong><?= h($unidade !== '' ? $unidade : 'UN') ?></strong>
                                </div>
                            </div>

                            <div class="desc-box">
                                <h3>Descrição</h3>
                                <p><?= h($descricao !== '' ? $descricao : 'Sem descrição cadastrada para este produto.') ?></p>
                            </div>

                            <div class="actions">
                                <a href="consulta_produto.php" class="btn btn-primary">Consultar outro produto</a>
                                <a href="javascript:history.back()" class="btn btn-secondary">Voltar</a>
                            </div>
                        </div>
                    </div>

                    <div class="footer-note">
                        Estoque atual: <strong><?= (int)$quantidade ?></strong> unidade(s)
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>