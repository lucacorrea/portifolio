<?php

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| MÉTODO
|--------------------------------------------------------------------------
*/

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
*/

require_once __DIR__ . '/../src/App/Config/Database.php';

/*
|--------------------------------------------------------------------------
| AJUSTE CONFORME SUA CONEXÃO
|--------------------------------------------------------------------------
| Caso seu Database.php use:
| $conn
| $db
| Database::connect()
| troque abaixo.
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Conexão PDO não encontrada.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| CÓDIGO
|--------------------------------------------------------------------------
*/

$codigo = isset($_POST['codigo'])
    ? trim((string)$_POST['codigo'])
    : '';

if ($codigo === '') {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => 'Código não informado.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function startsWith($texto, $inicio)
{
    return substr($texto, 0, strlen($inicio)) === $inicio;
}

function appBasePath()
{
    $scriptName = isset($_SERVER['SCRIPT_NAME'])
        ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
        : '';

    $dir = rtrim(dirname($scriptName), '/');

    if ($dir === '' || $dir === '.') {
        return '';
    }

    return preg_replace('#/api$#', '', $dir);
}

function baseUrl()
{
    $https = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    $scheme = $https ? 'https' : 'http';

    $host = isset($_SERVER['HTTP_HOST'])
        ? $_SERVER['HTTP_HOST']
        : 'localhost';

    return $scheme . '://' . $host;
}

function formatarMoeda($valor)
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function placeholderImagem()
{
    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600">
        <rect width="100%" height="100%" fill="#eef3f9"/>
        <rect x="80" y="80" width="440" height="440" rx="28" fill="#dbe4ef"/>
        <text x="50%" y="46%" text-anchor="middle"
        font-family="Arial"
        font-size="34"
        fill="#294f87"
        font-weight="700">
        SEM IMAGEM
        </text>

        <text x="50%" y="54%" text-anchor="middle"
        font-family="Arial"
        font-size="22"
        fill="#62708a">
        Produto sem foto cadastrada
        </text>
    </svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function normalizarImagemUrl($imagem)
{
    $imagem = trim((string)$imagem);

    if ($imagem === '') {
        return placeholderImagem();
    }

    if (
        preg_match('#^https?://#i', $imagem) ||
        startsWith($imagem, 'data:')
    ) {
        return $imagem;
    }

    if (startsWith($imagem, '//')) {

        $https = (
            !empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );

        return ($https ? 'https:' : 'http:') . $imagem;
    }

    if (startsWith($imagem, '/')) {
        return baseUrl() . $imagem;
    }

    $imagem = ltrim($imagem, './');

    return baseUrl() . appBasePath() . '/' . $imagem;
}

function primeiraImagemDoCampo($imagens)
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
            trim($json['url']) !== ''
        ) {
            return normalizarImagemUrl($json['url']);
        }

        if (
            isset($json['imagem']) &&
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
                    isset($item['url']) &&
                    trim($item['url']) !== ''
                ) {
                    return normalizarImagemUrl($item['url']);
                }

                if (
                    isset($item['imagem']) &&
                    trim($item['imagem']) !== ''
                ) {
                    return normalizarImagemUrl($item['imagem']);
                }

                if (
                    isset($item['path']) &&
                    trim($item['path']) !== ''
                ) {
                    return normalizarImagemUrl($item['path']);
                }
            }
        }
    }

    $partes = preg_split('/[\r\n,;|]+/', $imagens);

    if (is_array($partes)) {

        foreach ($partes as $parte) {

            $parte = trim($parte);

            if ($parte !== '') {
                return normalizarImagemUrl($parte);
            }
        }
    }

    return normalizarImagemUrl($imagens);
}

/*
|--------------------------------------------------------------------------
| CONSULTA
|--------------------------------------------------------------------------
*/

try {

    $codigoBusca = preg_replace('/[\s\-]+/', '', $codigo);

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

            REPLACE(REPLACE(TRIM(IFNULL(cean, '')), ' ', ''), '-', '') = :codigo

            OR REPLACE(REPLACE(TRIM(IFNULL(codigo, '')), ' ', ''), '-', '') = :codigo

            OR REPLACE(REPLACE(TRIM(IFNULL(qrcode, '')), ' ', ''), '-', '') = :codigo

        ORDER BY id DESC

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
            'message' => 'Produto não encontrado.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PREÇOS
    |--------------------------------------------------------------------------
    */

    $preco1 = (float)$produto['preco_venda'];
    $preco2 = (float)$produto['preco_venda_2'];
    $preco3 = (float)$produto['preco_venda_3'];
    $precoAtacado = (float)$produto['preco_venda_atacado'];

    /*
    |--------------------------------------------------------------------------
    | SAÍDA
    |--------------------------------------------------------------------------
    */

    $saida = [

        'id' => (int)$produto['id'],

        'filial_id' => isset($produto['filial_id'])
            ? (int)$produto['filial_id']
            : null,

        'codigo' => (string)$produto['codigo'],

        'cean' => (string)$produto['cean'],

        'qrcode' => (string)$produto['qrcode'],

        'nome' => (string)$produto['nome'],

        'unidade' => (string)$produto['unidade'],

        'descricao' => (string)$produto['descricao'],

        'categoria' => (string)$produto['categoria'],

        'ncm' => (string)$produto['ncm'],

        'cest' => (string)$produto['cest'],

        'estoque' => (int)$produto['quantidade'],

        'estoque_minimo' => (int)$produto['estoque_minimo'],

        /*
        |--------------------------------------------------------------------------
        | PREÇO PRINCIPAL
        |--------------------------------------------------------------------------
        */

        'preco_destaque' => [

            'titulo' => 'Preço Principal',

            'valor' => $preco1,

            'formatado' => formatarMoeda($preco1)
        ],

        /*
        |--------------------------------------------------------------------------
        | TODOS OS PREÇOS
        |--------------------------------------------------------------------------
        */

        'precos' => [

            [
                'tipo' => 'Preço 1',
                'principal' => true,
                'valor' => $preco1,
                'formatado' => formatarMoeda($preco1)
            ],

            [
                'tipo' => 'Preço 2',
                'principal' => false,
                'valor' => $preco2,
                'formatado' => formatarMoeda($preco2)
            ],

            [
                'tipo' => 'Preço 3',
                'principal' => false,
                'valor' => $preco3,
                'formatado' => formatarMoeda($preco3)
            ],

            [
                'tipo' => 'Atacado',
                'principal' => false,
                'valor' => $precoAtacado,
                'formatado' => formatarMoeda($precoAtacado)
            ]
        ],

        /*
        |--------------------------------------------------------------------------
        | IMAGEM
        |--------------------------------------------------------------------------
        */

        'imagem' => primeiraImagemDoCampo(
            $produto['imagens']
        )
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

        'message' => 'Erro interno.',

        'erro' => $e->getMessage(),

        'linha' => $e->getLine(),

        'arquivo' => $e->getFile()

    ], JSON_UNESCAPED_UNICODE);

    exit;
}
?>