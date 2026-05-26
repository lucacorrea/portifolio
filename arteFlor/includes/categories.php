<?php
require_once __DIR__ . '/products.php';

function category_status_options(): array
{
    return [
        'ativa' => 'Ativa',
        'inativa' => 'Inativa',
    ];
}

function category_find(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT *
         FROM categorias
         WHERE id = :id AND removido_em IS NULL
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $category = $statement->fetch();

    return $category ?: null;
}

function category_unique_slug(string $base, ?int $ignoreId = null): string
{
    $base = product_slugify($base, 120);
    $slug = $base;
    $suffix = 2;

    do {
        $sql = 'SELECT id FROM categorias WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';

        $statement = db()->prepare($sql);
        $statement->execute($params);

        if (!$statement->fetch()) {
            return $slug;
        }

        $slug = trim(substr($base, 0, 114), '-') . '-' . $suffix;
        $suffix += 1;
    } while ($suffix < 200);

    return trim(substr($base, 0, 112), '-') . '-' . bin2hex(random_bytes(3));
}

function category_list(array $filters = []): array
{
    $where = ['c.removido_em IS NULL'];
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(c.nome LIKE :search OR c.slug LIKE :search OR c.descricao LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && array_key_exists($status, category_status_options())) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }

    $highlight = (string) ($filters['destaque'] ?? '');
    if ($highlight === 'home') {
        $where[] = 'c.exibir_home = 1';
    } elseif ($highlight === 'catalogo') {
        $where[] = 'c.exibir_catalogo = 1';
    } elseif ($highlight === 'priorizadas') {
        $where[] = 'c.priorizar_listagem = 1';
    }

    $order = match ((string) ($filters['ordem'] ?? 'manual')) {
        'alfabetica' => 'c.nome ASC',
        'mais_usadas' => 'total_produtos DESC, c.nome ASC',
        default => 'c.ordem ASC, c.nome ASC',
    };

    $statement = db()->prepare(
        'SELECT c.*,
                COUNT(p.id) AS total_produtos
         FROM categorias c
         LEFT JOIN produtos p ON p.categoria_id = c.id AND p.removido_em IS NULL
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY c.id
         ORDER BY ' . $order . '
         LIMIT 200'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function category_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "ativa") AS ativas,
            SUM(status = "inativa") AS inativas,
            SUM(exibir_home = 1 OR exibir_catalogo = 1 OR priorizar_listagem = 1) AS destaques
         FROM categorias
         WHERE removido_em IS NULL'
    )->fetch() ?: [];

    $mostUsed = db()->query(
        'SELECT c.nome, COUNT(p.id) AS total_produtos
         FROM categorias c
         LEFT JOIN produtos p ON p.categoria_id = c.id AND p.removido_em IS NULL
         WHERE c.removido_em IS NULL
         GROUP BY c.id
         ORDER BY total_produtos DESC, c.nome ASC
         LIMIT 1'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'ativas' => (int) ($row['ativas'] ?? 0),
        'inativas' => (int) ($row['inativas'] ?? 0),
        'destaques' => (int) ($row['destaques'] ?? 0),
        'mais_usada_nome' => (string) ($mostUsed['nome'] ?? 'Nenhuma'),
        'mais_usada_total' => (int) ($mostUsed['total_produtos'] ?? 0),
    ];
}

function category_save_from_request(): int
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['nome'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'ativa');
    $description = trim((string) ($_POST['descricao'] ?? ''));
    $icon = trim((string) ($_POST['icone_textual'] ?? ''));
    $color = strtoupper(trim((string) ($_POST['cor_apoio'] ?? '#4F8F6B')));
    $order = max(0, (int) ($_POST['ordem'] ?? 0));

    if ($name === '') {
        throw new InvalidArgumentException('Informe o nome da categoria.');
    }
    if (strlen($name) > 120) {
        throw new InvalidArgumentException('O nome da categoria deve ter até 120 caracteres.');
    }
    if (!array_key_exists($status, category_status_options())) {
        throw new InvalidArgumentException('Status inválido.');
    }
    if ($description !== '' && strlen($description) > 255) {
        throw new InvalidArgumentException('A descrição deve ter até 255 caracteres.');
    }
    if ($icon !== '' && strlen($icon) > 60) {
        throw new InvalidArgumentException('O ícone textual deve ter até 60 caracteres.');
    }
    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
        throw new InvalidArgumentException('Informe uma cor hexadecimal válida, como #4F8F6B.');
    }

    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $slug = category_unique_slug($slugInput !== '' ? $slugInput : $name, $id > 0 ? $id : null);
    $payload = [
        'nome' => $name,
        'slug' => $slug,
        'descricao' => $description !== '' ? $description : null,
        'icone_textual' => $icon !== '' ? $icon : null,
        'cor_apoio' => $color,
        'ordem' => $order,
        'exibir_home' => isset($_POST['exibir_home']) ? 1 : 0,
        'exibir_catalogo' => isset($_POST['exibir_catalogo']) ? 1 : 0,
        'priorizar_listagem' => isset($_POST['priorizar_listagem']) ? 1 : 0,
        'status' => $status,
    ];

    if ($id > 0) {
        $payload['id'] = $id;
        db()->prepare(
            'UPDATE categorias
             SET nome = :nome,
                 slug = :slug,
                 descricao = :descricao,
                 icone_textual = :icone_textual,
                 cor_apoio = :cor_apoio,
                 ordem = :ordem,
                 exibir_home = :exibir_home,
                 exibir_catalogo = :exibir_catalogo,
                 priorizar_listagem = :priorizar_listagem,
                 status = :status
             WHERE id = :id AND removido_em IS NULL'
        )->execute($payload);

        return $id;
    }

    db()->prepare(
        'INSERT INTO categorias (
            nome, slug, descricao, icone_textual, cor_apoio, ordem,
            exibir_home, exibir_catalogo, priorizar_listagem, status
         ) VALUES (
            :nome, :slug, :descricao, :icone_textual, :cor_apoio, :ordem,
            :exibir_home, :exibir_catalogo, :priorizar_listagem, :status
         )'
    )->execute($payload);

    return (int) db()->lastInsertId();
}

function category_update_status(int $id, string $status): void
{
    if ($id <= 0 || !array_key_exists($status, category_status_options())) {
        throw new InvalidArgumentException('Categoria ou status inválido.');
    }

    db()->prepare(
        'UPDATE categorias
         SET status = :status
         WHERE id = :id AND removido_em IS NULL'
    )->execute([
        'id' => $id,
        'status' => $status,
    ]);
}
