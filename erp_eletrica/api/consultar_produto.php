<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/App/Config/Helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido.', [], 405);
}

$codigo = trim((string)($_POST['codigo'] ?? ''));

if ($codigo === '') {
    jsonResponse(false, 'Código não informado.', [], 422);
}

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $codigoBusca = preg_replace('/[\s\-]+/', '', $codigo);

    // Busca o produto e consolida o estoque se houver colunas
    $sql = "
        SELECT 
            p.*,
            (SELECT SUM(quantidade) FROM estoque_filiais WHERE produto_id = p.id) as estoque_total
        FROM produtos p
        WHERE 
            REPLACE(REPLACE(TRIM(COALESCE(p.cean, '')), ' ', ''), '-', '') = :codigo
            OR REPLACE(REPLACE(TRIM(COALESCE(p.codigo, '')), ' ', ''), '-', '') = :codigo
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':codigo', $codigoBusca, \PDO::PARAM_STR);
    $stmt->execute();

    $produto = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$produto) {
        jsonResponse(false, 'Produto não encontrado para este código.', [], 404);
    }

    $precoVenda = (float)($produto['preco_venda'] ?? 0);
    
    // Tratamento de imagem (pega a primeira do JSON se existir)
    $imagem = 'public/img/no-image.png';
    if (!empty($produto['imagens'])) {
        $imgs = json_decode($produto['imagens'], true);
        if (is_array($imgs) && !empty($imgs)) {
            $imagem = $imgs[0];
        } else if (is_string($produto['imagens'])) {
            $imagem = $produto['imagens'];
        }
    }

    $saida = [
        'id'                     => (int)$produto['id'],
        'codigo'                 => (string)($produto['codigo'] ?? ''),
        'cean'                   => (string)($produto['cean'] ?? ''),
        'nome'                   => (string)($produto['nome'] ?? ''),
        'unidade'                => (string)($produto['unidade'] ?? 'UN'),
        'categoria'              => (string)($produto['categoria'] ?? ''),
        'preco_venda'            => $precoVenda,
        'preco_venda_formatado'  => 'R$ ' . number_format($precoVenda, 2, ',', '.'),
        'estoque'                => (float)($produto['estoque_total'] ?? 0),
        'imagem'                 => $imagem
    ];

    jsonResponse(true, 'Produto localizado.', ['produto' => $saida]);

} catch (\Exception $e) {
    error_log("Consultar Produto Error: " . $e->getMessage());
    jsonResponse(false, 'Erro interno ao consultar produto. Contate o suporte.', ['debug' => $e->getMessage()], 500);
}
