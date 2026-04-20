<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Método não permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| CONEXÃO
|--------------------------------------------------------------------------
| Ajuste o caminho abaixo conforme seu projeto.
| Exemplo mais comum:
| require_once __DIR__ . '/../conexao.php';
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../src/App/Config/Database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Conexão com o banco não disponível.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = trim((string)($_POST['codigo'] ?? ''));

if ($codigo === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Código não informado.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function appBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($scriptName), '/');

    if ($dir === '' || $dir === '.') {
        return '';
    }

    return preg_replace('#/api$#', '', $dir) ?: '';
}

function baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function placeholderImagem(): string
{
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600">
    <rect width="100%" height="100%" fill="#eef3f9"/>
    <rect x="80" y="80" width="440" height="440" rx="28" fill="#dbe4ef"/>
    <text x="50%" y="46%" text-anchor="middle" font-family="Arial, sans-serif" font-size="34" fill="#294f87" font-weight="700">SEM IMAGEM</text>
    <text x="50%" y="54%" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" fill="#62708a">Produto sem foto cadastrada</text>
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

try {
    /*
    |--------------------------------------------------------------------------
    | BUSCA
    |--------------------------------------------------------------------------
    | Procura primeiro por CEAN e também aceita CODIGO.
    | Remove espaços e hífens para não falhar quando o leitor vier formatado.
    |--------------------------------------------------------------------------
    */
    $codigoBusca = preg_replace('/[\s\-]+/', '', $codigo);

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
            preco_venda
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
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Produto não encontrado para este código.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $precoVenda = (float)($produto['preco_venda'] ?? 0);

    $saida = [
        'id'                     => (int)$produto['id'],
        'filial_id'              => isset($produto['filial_id']) ? (int)$produto['filial_id'] : null,
        'codigo'                 => (string)($produto['codigo'] ?? ''),
        'cean'                   => (string)($produto['cean'] ?? ''),
        'nome'                   => (string)($produto['nome'] ?? ''),
        'unidade'                => (string)($produto['unidade'] ?? 'UN'),
        'descricao'              => (string)($produto['descricao'] ?? ''),
        'categoria'              => (string)($produto['categoria'] ?? ''),
        'preco_venda'            => $precoVenda,
        'preco_venda_formatado'  => 'R$ ' . number_format($precoVenda, 2, ',', '.'),
        'imagem'                 => primeiraImagemDoCampo((string)($produto['imagens'] ?? '')),
    ];

    echo json_encode([
        'ok' => true,
        'produto' => $saida
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Erro interno ao consultar o produto.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

?>