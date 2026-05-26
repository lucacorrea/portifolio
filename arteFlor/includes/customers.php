<?php
require_once __DIR__ . '/auth.php';

function customer_profile_options(): array
{
    return [
        'novo' => 'Novo',
        'recorrente' => 'Recorrente',
        'especial' => 'Especial',
    ];
}

function customer_channel_options(): array
{
    return [
        'telefone' => 'Telefone',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
    ];
}

function customer_clean_text(mixed $value, int $limit): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function customer_normalize_date(mixed $value): ?string
{
    $value = customer_clean_text($value, 10);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function customer_find(int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM clientes
         WHERE id = :id AND removido_em IS NULL
         LIMIT 1'
    );
    $statement->execute(['id' => $customerId]);
    $customer = $statement->fetch();

    return $customer ?: null;
}

function customer_main_address(int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM cliente_enderecos
         WHERE cliente_id = :cliente_id
         ORDER BY principal DESC, id DESC
         LIMIT 1'
    );
    $statement->execute(['cliente_id' => $customerId]);
    $address = $statement->fetch();

    return $address ?: null;
}

function customer_list(array $filters = []): array
{
    $where = ['c.removido_em IS NULL'];
    $params = [];

    $search = customer_clean_text($filters['search'] ?? '', 120);
    if ($search !== '') {
        $where[] = '(c.nome LIKE :search_nome OR c.telefone LIKE :search_telefone OR c.whatsapp LIKE :search_whatsapp OR c.email LIKE :search_email OR c.bairro LIKE :search_bairro)';
        $params['search_nome'] = '%' . $search . '%';
        $params['search_telefone'] = '%' . $search . '%';
        $params['search_whatsapp'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
        $params['search_bairro'] = '%' . $search . '%';
    }

    $profile = customer_clean_text($filters['perfil'] ?? '', 40);
    if ($profile !== '' && isset(customer_profile_options()[$profile])) {
        $where[] = 'c.perfil = :perfil';
        $params['perfil'] = $profile;
    }

    $district = customer_clean_text($filters['bairro'] ?? '', 120);
    if ($district !== '') {
        $where[] = 'c.bairro = :bairro';
        $params['bairro'] = $district;
    }

    $statement = db()->prepare(
        'SELECT
            c.*,
            SUM(CASE WHEN p.status <> "cancelado" THEN 1 ELSE 0 END) AS compras,
            COALESCE(AVG(CASE WHEN p.status <> "cancelado" THEN p.total ELSE NULL END), 0) AS ticket_medio,
            MAX(CASE WHEN p.status <> "cancelado" THEN p.criado_em ELSE NULL END) AS ultima_compra
         FROM clientes c
         LEFT JOIN pedidos p ON p.cliente_id = c.id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY c.id
         ORDER BY ultima_compra DESC, c.nome ASC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function customer_districts(): array
{
    $statement = db()->query(
        'SELECT DISTINCT bairro
         FROM clientes
         WHERE removido_em IS NULL AND bairro IS NOT NULL AND bairro <> ""
         ORDER BY bairro ASC'
    );

    return array_map(static fn (array $row): string => (string) $row['bairro'], $statement->fetchAll());
}

function customer_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN perfil = "recorrente" THEN 1 ELSE 0 END) AS recorrentes,
            SUM(CASE WHEN perfil = "especial" THEN 1 ELSE 0 END) AS especiais
         FROM clientes
         WHERE removido_em IS NULL'
    )->fetch() ?: [];

    $ticket = db()->query(
        'SELECT COALESCE(AVG(total), 0) AS ticket_medio
         FROM pedidos
         WHERE status <> "cancelado" AND cliente_id IS NOT NULL'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'recorrentes' => (int) ($row['recorrentes'] ?? 0),
        'especiais' => (int) ($row['especiais'] ?? 0),
        'ticket_medio' => (float) ($ticket['ticket_medio'] ?? 0),
    ];
}

function customer_save_from_request(array $request): int
{
    $customerId = filter_var($request['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $name = customer_clean_text($request['nome'] ?? '', 160);
    $email = customer_clean_text($request['email'] ?? '', 180);
    $phone = customer_clean_text($request['telefone'] ?? '', 40);
    $whatsapp = customer_clean_text($request['whatsapp'] ?? '', 40);
    $district = customer_clean_text($request['bairro'] ?? '', 120);
    $profile = customer_clean_text($request['perfil'] ?? 'novo', 40);
    $channel = customer_clean_text($request['canal_preferido'] ?? '', 40);
    $flowers = customer_clean_text($request['flores_preferidas'] ?? '', 255);
    $notes = customer_clean_text($request['observacoes'] ?? '', 2000);
    $address = customer_clean_text($request['endereco'] ?? '', 180);
    $reference = customer_clean_text($request['referencia'] ?? '', 255);

    if ($name === '' || strlen($name) < 3) {
        throw new InvalidArgumentException('Informe o nome do cliente.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um e-mail válido ou deixe em branco.');
    }
    if (!isset(customer_profile_options()[$profile])) {
        throw new InvalidArgumentException('Perfil de cliente inválido.');
    }
    if ($channel !== '' && !isset(customer_channel_options()[$channel])) {
        throw new InvalidArgumentException('Canal preferido inválido.');
    }
    if ($address !== '' && $district === '') {
        throw new InvalidArgumentException('Informe o bairro para salvar o endereço.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $params = [
            'nome' => $name,
            'email' => $email !== '' ? $email : null,
            'telefone' => $phone !== '' ? $phone : null,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'bairro' => $district !== '' ? $district : null,
            'perfil' => $profile,
            'canal_preferido' => $channel !== '' ? $channel : null,
            'flores_preferidas' => $flowers !== '' ? $flowers : null,
            'aniversario' => customer_normalize_date($request['aniversario'] ?? null),
            'data_importante' => customer_normalize_date($request['data_importante'] ?? null),
            'observacoes' => $notes !== '' ? $notes : null,
        ];

        if ($customerId > 0) {
            $existing = customer_find($customerId);
            if (!$existing) {
                throw new InvalidArgumentException('Cliente não encontrado.');
            }

            $params['id'] = $customerId;
            $pdo->prepare(
                'UPDATE clientes
                 SET nome = :nome, email = :email, telefone = :telefone, whatsapp = :whatsapp,
                     bairro = :bairro, perfil = :perfil, canal_preferido = :canal_preferido,
                     flores_preferidas = :flores_preferidas, aniversario = :aniversario,
                     data_importante = :data_importante, observacoes = :observacoes,
                     atualizado_em = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute($params);
        } else {
            $pdo->prepare(
                'INSERT INTO clientes (
                    nome, email, telefone, whatsapp, bairro, perfil, canal_preferido,
                    flores_preferidas, aniversario, data_importante, observacoes
                 ) VALUES (
                    :nome, :email, :telefone, :whatsapp, :bairro, :perfil, :canal_preferido,
                    :flores_preferidas, :aniversario, :data_importante, :observacoes
                 )'
            )->execute($params);
            $customerId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM cliente_enderecos WHERE cliente_id = :cliente_id AND principal = 1')
            ->execute(['cliente_id' => $customerId]);

        if ($address !== '' || $district !== '') {
            $pdo->prepare(
                'INSERT INTO cliente_enderecos (cliente_id, apelido, rua, bairro, referencia, principal)
                 VALUES (:cliente_id, "Principal", :rua, :bairro, :referencia, 1)'
            )->execute([
                'cliente_id' => $customerId,
                'rua' => $address !== '' ? $address : null,
                'bairro' => $district !== '' ? $district : 'Não informado',
                'referencia' => $reference !== '' ? $reference : null,
            ]);
        }

        $pdo->commit();

        return $customerId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function customer_badge_class(string $profile): string
{
    return match ($profile) {
        'especial' => 'admin-badge-soft',
        'recorrente' => 'admin-badge-ok',
        default => 'admin-badge-info',
    };
}
