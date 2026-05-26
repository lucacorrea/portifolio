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

    if (str_starts_with($url, '/')) {
        return $url;
    }

    if (str_starts_with($url, 'assets/')) {
        return base_url() . $url;
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

function product_inventory_status(array $product): string
{
    $stock = max(0, (int) ($product['estoque'] ?? 0));
    $minStock = max(0, (int) ($product['estoque_minimo'] ?? 0));

    if ($stock <= 0) {
        return 'sem_estoque';
    }

    if ($minStock <= 0) {
        return 'normal';
    }

    if ($stock <= $minStock) {
        return 'baixo';
    }

    if ($stock <= ($minStock * 2)) {
        return 'medio';
    }

    return 'normal';
}

function product_inventory_label(string $status): string
{
    return match ($status) {
        'sem_estoque' => 'Sem estoque',
        'baixo' => 'Estoque baixo',
        'medio' => 'Estoque médio',
        'normal' => 'Estoque normal',
        default => 'Estoque',
    };
}

function product_inventory_badge_class(string $status): string
{
    return match ($status) {
        'sem_estoque' => 'admin-badge-danger',
        'baixo' => 'admin-badge-warn',
        'medio' => 'admin-badge-info',
        'normal' => 'admin-badge-ok',
        default => 'admin-badge-soft',
    };
}

function product_inventory_row_class(string $status): string
{
    return match ($status) {
        'sem_estoque' => 'inventory-row-empty',
        'baixo' => 'inventory-row-low',
        'medio' => 'inventory-row-medium',
        'normal' => 'inventory-row-normal',
        default => '',
    };
}

function product_inventory_percent(array $product): int
{
    $stock = max(0, (int) ($product['estoque'] ?? 0));
    $minStock = max(0, (int) ($product['estoque_minimo'] ?? 0));

    if ($stock <= 0) {
        return 0;
    }

    if ($minStock <= 0) {
        return 100;
    }

    $target = max(1, $minStock * 2);
    $percent = (int) round(($stock / $target) * 100);

    return min(100, max(8, $percent));
}

function product_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "disponivel" THEN 1 ELSE 0 END) AS disponiveis,
            SUM(CASE WHEN sob_encomenda = 1 OR status = "sob_encomenda" THEN 1 ELSE 0 END) AS encomendas,
            SUM(CASE WHEN destaque = 1 THEN 1 ELSE 0 END) AS destaques,
            SUM(CASE WHEN estoque <= 0 THEN 1 ELSE 0 END) AS sem_estoque,
            SUM(CASE WHEN estoque > 0 AND estoque <= estoque_minimo THEN 1 ELSE 0 END) AS estoque_baixo,
            SUM(CASE WHEN estoque_minimo > 0 AND estoque > estoque_minimo AND estoque <= (estoque_minimo * 2) THEN 1 ELSE 0 END) AS estoque_medio,
            SUM(CASE WHEN estoque > 0 AND (estoque_minimo <= 0 OR estoque > (estoque_minimo * 2)) THEN 1 ELSE 0 END) AS estoque_normal
         FROM produtos
         WHERE removido_em IS NULL'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'disponiveis' => (int) ($row['disponiveis'] ?? 0),
        'encomendas' => (int) ($row['encomendas'] ?? 0),
        'destaques' => (int) ($row['destaques'] ?? 0),
        'sem_estoque' => (int) ($row['sem_estoque'] ?? 0),
        'estoque_baixo' => (int) ($row['estoque_baixo'] ?? 0),
        'estoque_medio' => (int) ($row['estoque_medio'] ?? 0),
        'estoque_normal' => (int) ($row['estoque_normal'] ?? 0),
    ];
}

function product_list(array $filters = []): array
{
    $where = ['p.removido_em IS NULL'];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.nome LIKE :search_nome OR p.sku LIKE :search_sku OR p.slug LIKE :search_slug OR c.nome LIKE :search_categoria)';
        $params['search_nome'] = '%' . $search . '%';
        $params['search_sku'] = '%' . $search . '%';
        $params['search_slug'] = '%' . $search . '%';
        $params['search_categoria'] = '%' . $search . '%';
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
        $where[] = 'p.estoque <= 0';
    } elseif ($stock === 'medio') {
        $where[] = 'p.estoque_minimo > 0 AND p.estoque > p.estoque_minimo AND p.estoque <= (p.estoque_minimo * 2)';
    } elseif ($stock === 'normal') {
        $where[] = 'p.estoque > 0 AND (p.estoque_minimo <= 0 OR p.estoque > (p.estoque_minimo * 2))';
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

    return product_attach_tags($statement->fetchAll());
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

function product_tags_by_product_ids(array $productIds): array
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
        'SELECT pt.produto_id, t.id, t.nome, t.slug
         FROM produto_tags pt
         INNER JOIN tags t ON t.id = pt.tag_id
         WHERE pt.produto_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY t.nome ASC'
    );
    $statement->execute($params);

    $grouped = [];
    foreach ($statement->fetchAll() as $tag) {
        $productId = (int) $tag['produto_id'];
        $grouped[$productId][] = [
            'id' => (int) $tag['id'],
            'nome' => (string) $tag['nome'],
            'slug' => (string) $tag['slug'],
        ];
    }

    return $grouped;
}

function product_attach_tags(array $products): array
{
    if (empty($products)) {
        return [];
    }

    $tagsByProduct = product_tags_by_product_ids(array_column($products, 'id'));
    foreach ($products as $index => $product) {
        $productId = (int) ($product['id'] ?? 0);
        $tagRows = $tagsByProduct[$productId] ?? [];
        $products[$index]['tag_rows'] = $tagRows;
        $products[$index]['tags'] = array_column($tagRows, 'nome');
        $products[$index]['tags_text'] = implode(', ', array_column($tagRows, 'nome'));
    }

    return $products;
}

function product_tags(int $productId): array
{
    if ($productId <= 0) {
        return [];
    }

    $grouped = product_tags_by_product_ids([$productId]);

    return $grouped[$productId] ?? [];
}

function product_tags_text(int $productId): string
{
    return implode(', ', array_column(product_tags($productId), 'nome'));
}

function product_normalize_tags_text(string $tagsText): array
{
    $parts = preg_split('/,/', $tagsText) ?: [];
    $tags = [];

    foreach ($parts as $part) {
        $name = trim((string) preg_replace('/\s+/', ' ', $part));
        if ($name === '') {
            continue;
        }

        if (strlen($name) > 80) {
            $name = trim(substr($name, 0, 80));
        }

        $slug = product_slugify($name, 100);
        if ($slug === '') {
            continue;
        }

        $tags[$slug] = [
            'nome' => $name,
            'slug' => $slug,
        ];
    }

    return array_values($tags);
}

function product_save_tags(int $productId, string $tagsText): void
{
    if ($productId <= 0) {
        throw new InvalidArgumentException('Produto inválido para salvar tags.');
    }

    $tags = product_normalize_tags_text($tagsText);
    $delete = db()->prepare('DELETE FROM produto_tags WHERE produto_id = :produto_id');
    $delete->execute(['produto_id' => $productId]);

    if (empty($tags)) {
        return;
    }

    $findTag = db()->prepare('SELECT id FROM tags WHERE slug = :slug LIMIT 1');
    $insertTag = db()->prepare('INSERT INTO tags (nome, slug) VALUES (:nome, :slug)');
    $linkTag = db()->prepare(
        'INSERT IGNORE INTO produto_tags (produto_id, tag_id)
         VALUES (:produto_id, :tag_id)'
    );

    foreach ($tags as $tag) {
        $findTag->execute(['slug' => $tag['slug']]);
        $existing = $findTag->fetch();

        if ($existing) {
            $tagId = (int) $existing['id'];
        } else {
            $insertTag->execute([
                'nome' => $tag['nome'],
                'slug' => $tag['slug'],
            ]);
            $tagId = (int) db()->lastInsertId();
        }

        $linkTag->execute([
            'produto_id' => $productId,
            'tag_id' => $tagId,
        ]);
    }
}

function product_local_upload_path(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '' || preg_match('/^https?:\/\//i', $url)) {
        return null;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $relative = '';

    foreach (['assets/' . PRODUCT_UPLOAD_PUBLIC_PREFIX, PRODUCT_UPLOAD_PUBLIC_PREFIX] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $relative = substr($path, strlen($prefix));
            break;
        }
    }

    if ($relative === '') {
        $assetPrefix = 'assets/' . PRODUCT_UPLOAD_PUBLIC_PREFIX;
        $assetPosition = strpos($path, $assetPrefix);
        if ($assetPosition !== false) {
            $relative = substr($path, $assetPosition + strlen($assetPrefix));
        }
    }

    if ($relative === '' || str_contains($relative, '..')) {
        return null;
    }

    $root = realpath(PRODUCT_UPLOAD_DIR);
    if ($root === false) {
        return null;
    }

    $candidate = PRODUCT_UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $realPath = realpath($candidate);
    if ($realPath === false || !is_file($realPath)) {
        return null;
    }

    $normalizedRoot = strtolower(rtrim($root, '\\/') . DIRECTORY_SEPARATOR);
    $normalizedPath = strtolower($realPath);

    return str_starts_with($normalizedPath, $normalizedRoot) ? $realPath : null;
}

function product_remove_image(int $productId, int $imageId): void
{
    if ($productId <= 0 || $imageId <= 0) {
        throw new InvalidArgumentException('Imagem inválida.');
    }

    $fileToDelete = null;
    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT pi.id, pi.url, pi.principal
             FROM produto_imagens pi
             INNER JOIN produtos p ON p.id = pi.produto_id
             WHERE pi.id = :image_id
               AND pi.produto_id = :product_id
               AND p.removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'image_id' => $imageId,
            'product_id' => $productId,
        ]);
        $image = $statement->fetch();

        if (!$image) {
            throw new InvalidArgumentException('Imagem não encontrada para este produto.');
        }

        $fileToDelete = product_local_upload_path($image['url'] ?? '');

        db()->prepare('DELETE FROM produto_imagens WHERE id = :id AND produto_id = :produto_id')
            ->execute([
                'id' => $imageId,
                'produto_id' => $productId,
            ]);

        if (!empty($image['principal'])) {
            $next = db()->prepare(
                'SELECT id
                 FROM produto_imagens
                 WHERE produto_id = :produto_id
                 ORDER BY ordem ASC, id ASC
                 LIMIT 1'
            );
            $next->execute(['produto_id' => $productId]);
            $nextImage = $next->fetch();

            if ($nextImage) {
                db()->prepare('UPDATE produto_imagens SET principal = 1 WHERE id = :id')
                    ->execute(['id' => (int) $nextImage['id']]);
            }
        }

        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }

    if ($fileToDelete !== null && is_file($fileToDelete) && !@unlink($fileToDelete)) {
        error_log('[ArteFlor][product-image-delete-file] Não foi possível remover o arquivo: ' . $fileToDelete);
    }
}

function product_set_primary_image(int $productId, int $imageId): void
{
    if ($productId <= 0 || $imageId <= 0) {
        throw new InvalidArgumentException('Imagem inválida.');
    }

    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT pi.id
             FROM produto_imagens pi
             INNER JOIN produtos p ON p.id = pi.produto_id
             WHERE pi.id = :image_id
               AND pi.produto_id = :product_id
               AND p.removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'image_id' => $imageId,
            'product_id' => $productId,
        ]);

        if (!$statement->fetch()) {
            throw new InvalidArgumentException('Imagem não encontrada para este produto.');
        }

        db()->prepare('UPDATE produto_imagens SET principal = 0 WHERE produto_id = :produto_id')
            ->execute(['produto_id' => $productId]);
        db()->prepare('UPDATE produto_imagens SET principal = 1 WHERE id = :id AND produto_id = :produto_id')
            ->execute([
                'id' => $imageId,
                'produto_id' => $productId,
            ]);

        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}

function product_unique_generated_sku(string $name): string
{
    for ($attempt = 0; $attempt < 20; $attempt += 1) {
        $sku = product_generate_sku($name);
        try {
            product_assert_unique_sku($sku);
            return $sku;
        } catch (InvalidArgumentException) {
        }
    }

    return 'AF-COPIA-' . strtoupper(bin2hex(random_bytes(4)));
}

function product_duplicate(int $productId): int
{
    if ($productId <= 0) {
        throw new InvalidArgumentException('Produto inválido.');
    }

    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT *
             FROM produtos
             WHERE id = :id AND removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();

        if (!$product) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $name = 'Cópia de ' . (string) $product['nome'];
        $sku = product_unique_generated_sku($name);
        $slug = product_unique_slug($name);

        db()->prepare(
            'INSERT INTO produtos (
                categoria_id, sku, nome, slug, descricao_curta, descricao_completa,
                preco, preco_promocional, estoque, estoque_minimo, status,
                exibir_catalogo, permitir_venda_online, disponivel_pdv, destaque, sob_encomenda
             ) VALUES (
                :categoria_id, :sku, :nome, :slug, :descricao_curta, :descricao_completa,
                :preco, :preco_promocional, 0, :estoque_minimo, "inativo",
                :exibir_catalogo, :permitir_venda_online, :disponivel_pdv, 0, :sob_encomenda
             )'
        )->execute([
            'categoria_id' => $product['categoria_id'] !== null ? (int) $product['categoria_id'] : null,
            'sku' => $sku,
            'nome' => $name,
            'slug' => $slug,
            'descricao_curta' => $product['descricao_curta'],
            'descricao_completa' => $product['descricao_completa'],
            'preco' => (float) $product['preco'],
            'preco_promocional' => $product['preco_promocional'] !== null ? (float) $product['preco_promocional'] : null,
            'estoque_minimo' => (int) $product['estoque_minimo'],
            'exibir_catalogo' => (int) $product['exibir_catalogo'],
            'permitir_venda_online' => (int) $product['permitir_venda_online'],
            'disponivel_pdv' => (int) $product['disponivel_pdv'],
            'sob_encomenda' => (int) $product['sob_encomenda'],
        ]);
        $newProductId = (int) db()->lastInsertId();

        db()->prepare(
            'INSERT INTO produto_imagens (produto_id, url, texto_alternativo, ordem, principal)
             SELECT :novo_produto_id, url, texto_alternativo, ordem, principal
             FROM produto_imagens
             WHERE produto_id = :produto_id
             ORDER BY ordem ASC, id ASC'
        )->execute([
            'novo_produto_id' => $newProductId,
            'produto_id' => $productId,
        ]);

        db()->prepare(
            'INSERT IGNORE INTO produto_tags (produto_id, tag_id)
             SELECT :novo_produto_id, tag_id
             FROM produto_tags
             WHERE produto_id = :produto_id'
        )->execute([
            'novo_produto_id' => $newProductId,
            'produto_id' => $productId,
        ]);

        db()->commit();

        return $newProductId;
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}

function product_delete(int $productId): void
{
    if ($productId <= 0) {
        throw new InvalidArgumentException('Produto inválido.');
    }

    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT id
             FROM produtos
             WHERE id = :id AND removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $productId]);

        if (!$statement->fetch()) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        db()->prepare(
            'UPDATE produtos
             SET status = "inativo",
                 exibir_catalogo = 0,
                 permitir_venda_online = 0,
                 disponivel_pdv = 0,
                 destaque = 0,
                 atualizado_em = CURRENT_TIMESTAMP,
                 removido_em = CURRENT_TIMESTAMP
             WHERE id = :id AND removido_em IS NULL'
        )->execute(['id' => $productId]);

        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
}

function product_update_status(int $productId, string $action): void
{
    if ($productId <= 0 || !in_array($action, ['ativar', 'inativar'], true)) {
        throw new InvalidArgumentException('Ação inválida.');
    }

    $statement = db()->prepare(
        'SELECT id, estoque
         FROM produtos
         WHERE id = :id AND removido_em IS NULL
         LIMIT 1'
    );
    $statement->execute(['id' => $productId]);
    $product = $statement->fetch();

    if (!$product) {
        throw new InvalidArgumentException('Produto não encontrado.');
    }

    $nextStatus = $action === 'inativar'
        ? 'inativo'
        : ((int) $product['estoque'] > 0 ? 'disponivel' : 'sem_estoque');

    db()->prepare('UPDATE produtos SET status = :status WHERE id = :id')
        ->execute([
            'status' => $nextStatus,
            'id' => $productId,
        ]);
}

function product_update_stock(int $productId, string $tipo, int $quantidade, string $motivo = ''): void
{
    $allowedTypes = ['entrada', 'saida', 'ajuste', 'perda'];
    if ($productId <= 0 || !in_array($tipo, $allowedTypes, true)) {
        throw new InvalidArgumentException('Movimentação inválida.');
    }
    if ($quantidade < 0) {
        throw new InvalidArgumentException('A quantidade não pode ser negativa.');
    }
    if ($motivo !== '' && strlen($motivo) > 255) {
        throw new InvalidArgumentException('O motivo deve ter até 255 caracteres.');
    }

    $adminUser = admin_current_user() ?? [];
    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'SELECT id, estoque, status
             FROM produtos
             WHERE id = :id AND removido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();

        if (!$product) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $previousStock = (int) $product['estoque'];
        $newStock = match ($tipo) {
            'entrada' => $previousStock + $quantidade,
            'saida', 'perda' => $previousStock - $quantidade,
            'ajuste' => $quantidade,
        };

        if ($newStock < 0) {
            throw new InvalidArgumentException('A movimentação deixaria o estoque negativo.');
        }

        $movementAmount = $tipo === 'ajuste' ? abs($newStock - $previousStock) : $quantidade;
        if ($movementAmount <= 0) {
            throw new InvalidArgumentException('A movimentação precisa alterar o estoque.');
        }

        $currentStatus = (string) $product['status'];
        $nextStatus = $currentStatus;
        if ($currentStatus !== 'inativo') {
            if ($newStock <= 0) {
                $nextStatus = 'sem_estoque';
            } elseif ($currentStatus === 'sem_estoque') {
                $nextStatus = 'disponivel';
            }
        }

        db()->prepare(
            'UPDATE produtos
             SET estoque = :estoque,
                 status = :status
             WHERE id = :id'
        )->execute([
            'estoque' => $newStock,
            'status' => $nextStatus,
            'id' => $productId,
        ]);

        db()->prepare(
            'INSERT INTO estoque_movimentacoes (
                produto_id, usuario_admin_id, tipo, origem, quantidade,
                estoque_anterior, estoque_novo, responsavel_nome, motivo, status, movimentado_em
             ) VALUES (
                :produto_id, :usuario_admin_id, :tipo, :origem, :quantidade,
                :estoque_anterior, :estoque_novo, :responsavel_nome, :motivo, "concluido", CURRENT_TIMESTAMP
             )'
        )->execute([
            'produto_id' => $productId,
            'usuario_admin_id' => (int) ($adminUser['id'] ?? 0) ?: null,
            'tipo' => $tipo,
            'origem' => $tipo === 'entrada' ? 'compra' : 'correcao_interna',
            'quantidade' => $movementAmount,
            'estoque_anterior' => $previousStock,
            'estoque_novo' => $newStock,
            'responsavel_nome' => substr((string) ($adminUser['nome'] ?? 'Admin'), 0, 140),
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);

        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
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
        if ($id > 0) {
            $existingStatement = db()->prepare(
                'SELECT id
                 FROM produtos
                 WHERE id = :id AND removido_em IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $existingStatement->execute(['id' => $id]);

            if (!$existingStatement->fetch()) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }
        }

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
        product_save_tags($productId, (string) ($_POST['tags'] ?? ''));
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

function product_public_list(array $filters = []): array
{
    $where = [
        'p.removido_em IS NULL',
        'p.exibir_catalogo = 1',
        'p.status <> "inativo"',
    ];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.nome LIKE :search_nome OR p.sku LIKE :search_sku OR p.slug LIKE :search_slug OR c.nome LIKE :search_categoria OR EXISTS (
            SELECT 1
            FROM produto_tags pts
            INNER JOIN tags ts ON ts.id = pts.tag_id
            WHERE pts.produto_id = p.id AND ts.nome LIKE :search_tag
        ))';
        $params['search_nome'] = '%' . $search . '%';
        $params['search_sku'] = '%' . $search . '%';
        $params['search_slug'] = '%' . $search . '%';
        $params['search_categoria'] = '%' . $search . '%';
        $params['search_tag'] = '%' . $search . '%';
    }

    $id = (int) ($filters['id'] ?? 0);
    if ($id > 0) {
        $where[] = 'p.id = :id';
        $params['id'] = $id;
    }

    $categoryId = (int) ($filters['categoria_id'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.categoria_id = :categoria_id';
        $params['categoria_id'] = $categoryId;
    }

    $category = trim((string) ($filters['categoria'] ?? ''));
    if ($category !== '') {
        $where[] = '(c.slug = :categoria OR c.nome = :categoria_nome)';
        $params['categoria'] = product_slugify($category, 140);
        $params['categoria_nome'] = $category;
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status === 'disponiveis') {
        $status = 'disponivel';
    }
    if ($status === 'disponivel') {
        $where[] = 'p.status = "disponivel" AND p.estoque > 0';
    } elseif ($status === 'sob_encomenda') {
        $where[] = '(p.status = "sob_encomenda" OR p.sob_encomenda = 1)';
    } elseif ($status === 'sem_estoque') {
        $where[] = '(p.status = "sem_estoque" OR p.estoque <= 0)';
    }

    if (!empty($filters['destaque'])) {
        $where[] = 'p.destaque = 1';
    }

    $excludeId = (int) ($filters['exclude_id'] ?? 0);
    if ($excludeId > 0) {
        $where[] = 'p.id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }

    $limit = max(1, min(200, (int) ($filters['limit'] ?? 60)));
    $order = !empty($filters['destaque'])
        ? 'p.criado_em DESC, p.id DESC'
        : 'p.destaque DESC, p.criado_em DESC, p.nome ASC';

    $sql = 'SELECT
              p.*,
              c.nome AS categoria_nome,
              c.slug AS categoria_slug,
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
            ORDER BY ' . $order . '
            LIMIT ' . $limit;

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return product_attach_tags($statement->fetchAll());
}

function product_featured(int $limit = 6): array
{
    return product_public_list([
        'destaque' => 1,
        'limit' => $limit,
    ]);
}

function product_find_by_slug(string $slug): ?array
{
    $slug = product_slugify($slug, 200);
    if ($slug === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT
            p.*,
            c.nome AS categoria_nome,
            c.slug AS categoria_slug,
            (
              SELECT pi.url
              FROM produto_imagens pi
              WHERE pi.produto_id = p.id
              ORDER BY pi.principal DESC, pi.ordem ASC, pi.id ASC
              LIMIT 1
            ) AS imagem
         FROM produtos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.slug = :slug
           AND p.removido_em IS NULL
           AND p.exibir_catalogo = 1
           AND p.status <> "inativo"
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $product = $statement->fetch();

    return $product ? product_attach_tags([$product])[0] : null;
}

function product_public_find_by_id(int $productId): ?array
{
    if ($productId <= 0) {
        return null;
    }

    $products = product_public_list([
        'id' => $productId,
        'limit' => 1,
    ]);

    return $products[0] ?? null;
}

function product_related(int $productId, ?int $categoryId, int $limit = 4): array
{
    $productId = max(0, $productId);
    $categoryId = $categoryId !== null ? max(0, $categoryId) : 0;
    if ($productId <= 0 || $categoryId <= 0) {
        return [];
    }

    return product_public_list([
        'categoria_id' => $categoryId,
        'exclude_id' => $productId,
        'limit' => max(1, min(12, $limit)),
    ]);
}

function product_public_categories(): array
{
    $statement = db()->query(
        'SELECT
            c.id,
            c.nome,
            c.slug,
            c.descricao,
            c.cor_apoio,
            (
              SELECT COUNT(*)
              FROM produtos p
              WHERE p.categoria_id = c.id
                AND p.removido_em IS NULL
                AND p.exibir_catalogo = 1
                AND p.status <> "inativo"
            ) AS total_produtos,
            (
              SELECT pi.url
              FROM produtos p
              INNER JOIN produto_imagens pi ON pi.produto_id = p.id
              WHERE p.categoria_id = c.id
                AND p.removido_em IS NULL
                AND p.exibir_catalogo = 1
                AND p.status <> "inativo"
              ORDER BY p.destaque DESC, pi.principal DESC, pi.ordem ASC, pi.id ASC
              LIMIT 1
            ) AS imagem
         FROM categorias c
         WHERE c.removido_em IS NULL
           AND c.status = "ativa"
           AND c.exibir_catalogo = 1
         ORDER BY c.priorizar_listagem DESC, c.ordem ASC, c.nome ASC'
    );

    $categories = $statement->fetchAll();
    foreach ($categories as $index => $category) {
        $categories[$index]['imagem'] = product_public_image_url($category['imagem'] ?? '');
    }

    if (!empty($categories)) {
        return $categories;
    }

    return array_map(
        static fn(string $name): array => [
            'id' => 0,
            'nome' => $name,
            'slug' => product_slugify($name, 140),
            'descricao' => '',
            'cor_apoio' => '#4F8F6B',
            'total_produtos' => 0,
            'imagem' => '',
        ],
        product_default_category_names()
    );
}

function product_main_image(int $productId): ?array
{
    if ($productId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, url, texto_alternativo, principal
         FROM produto_imagens
         WHERE produto_id = :produto_id
         ORDER BY principal DESC, ordem ASC, id ASC
         LIMIT 1'
    );
    $statement->execute(['produto_id' => $productId]);
    $image = $statement->fetch();

    if (!$image) {
        return null;
    }

    $image['raw_url'] = $image['url'];
    $image['url'] = product_public_image_url($image['url']);

    return $image;
}

function product_images_public(int $productId): array
{
    $images = product_images($productId);
    foreach ($images as $index => $image) {
        $images[$index]['raw_url'] = $image['url'];
        $images[$index]['url'] = product_public_image_url($image['url']);
    }

    return $images;
}

function product_admin_message_from_query(): ?array
{
    $successMessages = [
        'produto_salvo' => ['admin-alert-success', 'Produto salvo', 'As informações do produto foram gravadas com segurança.'],
        'imagem_removida' => ['admin-alert-success', 'Imagem removida', 'A imagem foi desvinculada do produto.'],
        'imagem_principal' => ['admin-alert-success', 'Imagem principal atualizada', 'A vitrine passará a priorizar a imagem selecionada.'],
        'produto_duplicado' => ['admin-alert-success', 'Produto duplicado', 'A cópia foi criada inativa e com estoque zerado para revisão.'],
        'produto_inativado' => ['admin-alert-warning', 'Produto inativado', 'O produto foi retirado da venda pública sem ser excluído.'],
        'produto_ativado' => ['admin-alert-success', 'Produto ativado', 'O status comercial foi atualizado conforme o estoque atual.'],
        'produto_excluido' => ['admin-alert-warning', 'Produto excluído', 'O produto saiu do catálogo, do PDV e da listagem. O histórico foi preservado.'],
        'estoque_atualizado' => ['admin-alert-success', 'Estoque atualizado', 'A movimentação foi registrada e o saldo foi recalculado.'],
    ];

    $errorMessages = [
        'acao_invalida' => ['admin-alert-danger', 'Ação não concluída', 'Não foi possível concluir a ação solicitada. Verifique os dados e tente novamente.'],
        'csrf' => ['admin-alert-danger', 'Sessão expirada', 'Recarregue a página e tente novamente.'],
        'metodo_invalido' => ['admin-alert-danger', 'Método inválido', 'Essa ação precisa ser enviada pelo formulário seguro.'],
        'produto_nao_encontrado' => ['admin-alert-danger', 'Produto não encontrado', 'O produto informado não existe ou não está disponível para alteração.'],
        'imagem_nao_encontrada' => ['admin-alert-danger', 'Imagem não encontrada', 'A imagem informada não pertence ao produto selecionado.'],
        'estoque_negativo' => ['admin-alert-warning', 'Estoque insuficiente', 'A movimentação deixaria o estoque negativo.'],
        'estoque_sem_alteracao' => ['admin-alert-info', 'Sem alteração de estoque', 'A movimentação precisa alterar o saldo atual.'],
    ];

    $success = (string) ($_GET['success'] ?? '');
    if ($success !== '' && isset($successMessages[$success])) {
        [$class, $title, $body] = $successMessages[$success];
        return compact('class', 'title', 'body');
    }

    $error = (string) ($_GET['error'] ?? '');
    if ($error !== '' && isset($errorMessages[$error])) {
        [$class, $title, $body] = $errorMessages[$error];
        return compact('class', 'title', 'body');
    }

    return null;
}
