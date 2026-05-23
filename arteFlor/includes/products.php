<?php
require_once __DIR__ . '/auth.php';

const PRODUCT_UPLOAD_DIR = __DIR__ . '/../assets/uploads/produtos';
const PRODUCT_UPLOAD_PUBLIC_PREFIX = 'uploads/produtos/';
const PRODUCT_MAX_UPLOAD_BYTES = 5242880;
const PRODUCT_MAX_UPLOAD_FILES = 8;

function product_status_options(): array
{
    return [
        'disponivel' => 'Disponível',
        'sob_encomenda' => 'Sob encomenda',
        'inativo' => 'Inativo',
        'sem_estoque' => 'Sem estoque',
    ];
}

function product_default_category_names(): array
{
    return ['Buquês', 'Arranjos', 'Vasos', 'Plantas', 'Presentes', 'Datas especiais', 'Encomendas'];
}

function product_slugify(string $value, int $maxLength = 180): string
{
    $value = trim($value);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    if (strlen($value) > $maxLength) {
        $value = trim(substr($value, 0, $maxLength), '-');
    }

    return $value !== '' ? $value : 'item';
}

function product_public_image_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    return asset($url);
}

function product_categories(): array
{
    $rows = db()->query(
        "SELECT id, nome, slug, status
         FROM categorias
         WHERE removido_em IS NULL
         ORDER BY ordem ASC, nome ASC"
    )->fetchAll();

    if (!empty($rows)) {
        return $rows;
    }

    return array_map(
        fn(string $name): array => ['id' => 0, 'nome' => $name, 'slug' => product_slugify($name, 120), 'status' => 'ativa'],
        product_default_category_names()
    );
}

function product_ensure_category(string $name): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Informe uma categoria.');
    }
    if (strlen($name) > 120) {
        throw new InvalidArgumentException('A categoria deve ter até 120 caracteres.');
    }

    $slug = product_slugify($name, 120);
    $statement = db()->prepare('SELECT id FROM categorias WHERE slug = :slug LIMIT 1');
    $statement->execute(['slug' => $slug]);
    $existing = $statement->fetch();

    if ($existing) {
        return (int) $existing['id'];
    }

    db()->prepare(
        'INSERT INTO categorias (nome, slug, descricao, status, exibir_catalogo, exibir_home)
         VALUES (:nome, :slug, :descricao, "ativa", 1, 1)'
    )->execute([
        'nome' => $name,
        'slug' => $slug,
        'descricao' => 'Categoria criada pelo cadastro de produto.',
    ]);

    return (int) db()->lastInsertId();
}

function product_unique_slug(string $base, ?int $ignoreId = null): string
{
    $base = product_slugify($base, 180);
    $slug = $base;
    $suffix = 2;

    do {
        $sql = 'SELECT id FROM produtos WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = db()->prepare($sql);
        $statement->execute($params);
        $exists = (bool) $statement->fetch();

        if (!$exists) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix += 1;
    } while ($suffix < 200);

    return $base . '-' . bin2hex(random_bytes(3));
}

function product_generate_sku(string $name): string
{
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', product_slugify($name)), 0, 3)) ?: 'AF';

    return 'AF-' . $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function product_normalize_money(string|int|float|null $value): float
{
    $value = trim((string) $value);
    if (str_contains($value, ',')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return max(0, (float) $value);
}

function product_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function product_assert_unique_sku(string $sku, ?int $ignoreId = null): void
{
    $sql = 'SELECT id FROM produtos WHERE sku = :sku';
    $params = ['sku' => $sku];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    if ($statement->fetch()) {
        throw new InvalidArgumentException('Este SKU já está em uso em outro produto.');
    }
}

function product_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "disponivel") AS disponiveis,
            SUM(sob_encomenda = 1 OR status = "sob_encomenda") AS encomendas,
            SUM(destaque = 1) AS destaques,
            SUM(estoque <= estoque_minimo) AS estoque_baixo
         FROM produtos
         WHERE removido_em IS NULL'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'disponiveis' => (int) ($row['disponiveis'] ?? 0),
        'encomendas' => (int) ($row['encomendas'] ?? 0),
        'destaques' => (int) ($row['destaques'] ?? 0),
        'estoque_baixo' => (int) ($row['estoque_baixo'] ?? 0),
    ];
}

function product_list(array $filters = []): array
{
    $where = ['p.removido_em IS NULL'];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.nome LIKE :search OR p.sku LIKE :search OR p.slug LIKE :search OR c.nome LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    $categoryId = (int) ($filters['categoria_id'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.categoria_id = :categoria_id';
        $params['categoria_id'] = $categoryId;
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && array_key_exists($status, product_status_options())) {
        $where[] = 'p.status = :status';
        $params['status'] = $status;
    }

    $stock = (string) ($filters['estoque'] ?? '');
    if ($stock === 'baixo') {
        $where[] = 'p.estoque > 0 AND p.estoque <= p.estoque_minimo';
    } elseif ($stock === 'sem') {
        $where[] = 'p.estoque = 0';
    } elseif ($stock === 'com') {
        $where[] = 'p.estoque > 0';
    }

    $sql = 'SELECT
              p.*,
              c.nome AS categoria_nome,
              (
                SELECT pi.url
                FROM produto_imagens pi
                WHERE pi.produto_id = p.id
                ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
                LIMIT 1
              ) AS imagem
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.criado_em DESC, p.id DESC
            LIMIT 200';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function product_find(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT p.*, c.nome AS categoria_nome
         FROM produtos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id = :id AND p.removido_em IS NULL
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();

    return $product ?: null;
}

function product_images(int $productId): array
{
    $statement = db()->prepare(
        'SELECT id, url, texto_alternativo, principal
         FROM produto_imagens
         WHERE produto_id = :produto_id
         ORDER BY principal DESC, ordem ASC, id ASC'
    );
    $statement->execute(['produto_id' => $productId]);

    return $statement->fetchAll();
}

function product_images_by_product_ids(array $productIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $id): bool => $id > 0)));
    if (empty($ids)) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $key = 'id' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $statement = db()->prepare(
        'SELECT produto_id, url, texto_alternativo, principal
         FROM produto_imagens
         WHERE produto_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY produto_id ASC, principal DESC, ordem ASC, id ASC'
    );
    $statement->execute($params);

    $grouped = [];
    foreach ($statement->fetchAll() as $image) {
        $productId = (int) $image['produto_id'];
        $grouped[$productId][] = $image;
    }

    return $grouped;
}

function product_upload_files(int $productId, string $altText): array
{
    if (empty($_FILES['imagens']) || !is_array($_FILES['imagens']['name'] ?? null)) {
        return [];
    }

    if (!is_dir(PRODUCT_UPLOAD_DIR) && !mkdir(PRODUCT_UPLOAD_DIR, 0755, true) && !is_dir(PRODUCT_UPLOAD_DIR)) {
        throw new RuntimeException('Não foi possível criar a pasta de upload dos produtos.');
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];
    $names = $_FILES['imagens']['name'];
    $errors = is_array($_FILES['imagens']['error'] ?? null) ? $_FILES['imagens']['error'] : [];
    $submittedFiles = 0;
    foreach ($errors as $error) {
        if ((int) $error !== UPLOAD_ERR_NO_FILE) {
            $submittedFiles += 1;
        }
    }

    if ($submittedFiles > PRODUCT_MAX_UPLOAD_FILES) {
        throw new RuntimeException('Envie no máximo 8 imagens por vez.');
    }

    $uploaded = 0;
    $savedFiles = [];
    $count = count($names);

    $currentCountStatement = db()->prepare('SELECT COUNT(*) AS total FROM produto_imagens WHERE produto_id = :produto_id');
    $currentCountStatement->execute(['produto_id' => $productId]);
    $currentCount = (int) ($currentCountStatement->fetch()['total'] ?? 0);

    try {
        for ($index = 0; $index < $count; $index += 1) {
            $error = (int) ($_FILES['imagens']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Uma das imagens não foi enviada corretamente.');
            }

            $tmpName = (string) $_FILES['imagens']['tmp_name'][$index];
            $size = (int) ($_FILES['imagens']['size'][$index] ?? 0);

            if ($size <= 0 || $size > PRODUCT_MAX_UPLOAD_BYTES) {
                throw new RuntimeException('Cada imagem deve ter até 5 MB.');
            }

            $imageInfo = @getimagesize($tmpName);
            if ($imageInfo === false) {
                throw new RuntimeException('Envie apenas arquivos de imagem válidos.');
            }

            $mime = (string) ($imageInfo['mime'] ?? '');
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($tmpName);
            }
            if (!isset($allowedMimes[$mime])) {
                throw new RuntimeException('Formato não permitido. Use JPG, PNG, WEBP, GIF ou AVIF.');
            }

            $extension = $allowedMimes[$mime];
            $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $destination = PRODUCT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('Não foi possível salvar uma das imagens.');
            }

            $savedFiles[] = $destination;
            $uploaded += 1;
            $order = $currentCount + $uploaded;

            db()->prepare(
                'INSERT INTO produto_imagens (produto_id, url, texto_alternativo, ordem, principal)
                 VALUES (:produto_id, :url, :texto_alternativo, :ordem, :principal)'
            )->execute([
                'produto_id' => $productId,
                'url' => PRODUCT_UPLOAD_PUBLIC_PREFIX . $filename,
                'texto_alternativo' => $altText,
                'ordem' => $order,
                'principal' => ($currentCount === 0 && $uploaded === 1) ? 1 : 0,
            ]);
        }
    } catch (Throwable $error) {
        foreach ($savedFiles as $savedFile) {
            if (is_file($savedFile)) {
                @unlink($savedFile);
            }
        }
        throw $error;
    }

    return $savedFiles;
}

function product_save_from_request(): int
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['nome'] ?? ''));
    $categoryName = trim((string) ($_POST['categoria_nome'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'disponivel');

    if ($name === '') {
        throw new InvalidArgumentException('Informe o nome do produto.');
    }
    if (strlen($name) > 180) {
        throw new InvalidArgumentException('O nome do produto deve ter até 180 caracteres.');
    }

    if (!array_key_exists($status, product_status_options())) {
        throw new InvalidArgumentException('Status inválido.');
    }

    $sku = trim((string) ($_POST['sku'] ?? ''));
    $sku = $sku !== '' ? strtoupper($sku) : product_generate_sku($name);
    if (strlen($sku) > 60) {
        throw new InvalidArgumentException('O SKU deve ter até 60 caracteres.');
    }
    $price = product_normalize_money($_POST['preco'] ?? 0);
    $promoPrice = product_normalize_money($_POST['preco_promocional'] ?? 0);
    if ($promoPrice > 0 && $price > 0 && $promoPrice >= $price) {
        throw new InvalidArgumentException('O preço promocional deve ser menor que o preço principal.');
    }
    $stock = max(0, (int) ($_POST['estoque'] ?? 0));
    $minStock = max(0, (int) ($_POST['estoque_minimo'] ?? 0));
    $shortDescription = trim((string) ($_POST['descricao_curta'] ?? ''));
    $fullDescription = trim((string) ($_POST['descricao_completa'] ?? ''));
    $savedFiles = [];

    db()->beginTransaction();

    try {
        $categoryId = product_ensure_category($categoryName);
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $slug = product_unique_slug($slug !== '' ? $slug : $name, $id > 0 ? $id : null);
        product_assert_unique_sku($sku, $id > 0 ? $id : null);

        if ($id > 0) {
            db()->prepare(
                'UPDATE produtos
                 SET categoria_id = :categoria_id,
                     sku = :sku,
                     nome = :nome,
                     slug = :slug,
                     descricao_curta = :descricao_curta,
                     descricao_completa = :descricao_completa,
                     preco = :preco,
                     preco_promocional = :preco_promocional,
                     estoque = :estoque,
                     estoque_minimo = :estoque_minimo,
                     status = :status,
                     exibir_catalogo = :exibir_catalogo,
                     permitir_venda_online = :permitir_venda_online,
                     disponivel_pdv = :disponivel_pdv,
                     destaque = :destaque,
                     sob_encomenda = :sob_encomenda
                 WHERE id = :id'
            )->execute([
                'categoria_id' => $categoryId,
                'sku' => $sku,
                'nome' => $name,
                'slug' => $slug,
                'descricao_curta' => $shortDescription,
                'descricao_completa' => $fullDescription,
                'preco' => $price,
                'preco_promocional' => $promoPrice > 0 ? $promoPrice : null,
                'estoque' => $stock,
                'estoque_minimo' => $minStock,
                'status' => $status,
                'exibir_catalogo' => product_checkbox('exibir_catalogo'),
                'permitir_venda_online' => product_checkbox('permitir_venda_online'),
                'disponivel_pdv' => product_checkbox('disponivel_pdv'),
                'destaque' => product_checkbox('destaque'),
                'sob_encomenda' => product_checkbox('sob_encomenda'),
                'id' => $id,
            ]);
            $productId = $id;
        } else {
            db()->prepare(
                'INSERT INTO produtos (
                    categoria_id, sku, nome, slug, descricao_curta, descricao_completa,
                    preco, preco_promocional, estoque, estoque_minimo, status,
                    exibir_catalogo, permitir_venda_online, disponivel_pdv, destaque, sob_encomenda
                 ) VALUES (
                    :categoria_id, :sku, :nome, :slug, :descricao_curta, :descricao_completa,
                    :preco, :preco_promocional, :estoque, :estoque_minimo, :status,
                    :exibir_catalogo, :permitir_venda_online, :disponivel_pdv, :destaque, :sob_encomenda
                 )'
            )->execute([
                'categoria_id' => $categoryId,
                'sku' => $sku,
                'nome' => $name,
                'slug' => $slug,
                'descricao_curta' => $shortDescription,
                'descricao_completa' => $fullDescription,
                'preco' => $price,
                'preco_promocional' => $promoPrice > 0 ? $promoPrice : null,
                'estoque' => $stock,
                'estoque_minimo' => $minStock,
                'status' => $status,
                'exibir_catalogo' => product_checkbox('exibir_catalogo'),
                'permitir_venda_online' => product_checkbox('permitir_venda_online'),
                'disponivel_pdv' => product_checkbox('disponivel_pdv'),
                'destaque' => product_checkbox('destaque'),
                'sob_encomenda' => product_checkbox('sob_encomenda'),
            ]);
            $productId = (int) db()->lastInsertId();
        }

        $savedFiles = product_upload_files($productId, $name);
        db()->commit();

        return $productId;
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        foreach ($savedFiles as $savedFile) {
            if (is_file($savedFile)) {
                @unlink($savedFile);
            }
        }
        throw $error;
    }
}
