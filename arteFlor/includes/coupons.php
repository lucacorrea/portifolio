<?php
require_once __DIR__ . '/auth.php';

function coupon_type_options(): array
{
    return [
        'percentual' => 'Percentual',
        'valor_fixo' => 'Valor fixo',
        'frete' => 'Frete',
    ];
}

function coupon_status_options(): array
{
    return [
        'ativo' => 'Ativo',
        'pausado' => 'Pausado',
        'encerrado' => 'Encerrado',
        'expirado' => 'Expirado',
    ];
}

function coupon_channel_options(): array
{
    return [
        'catalogo' => 'Catálogo',
        'pdv' => 'PDV',
        'atendimento' => 'Atendimento',
        'todos' => 'Todos',
    ];
}

function coupon_clean_text(mixed $value, int $limit): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function coupon_normalize_code(string $code): string
{
    $code = strtoupper(coupon_clean_text($code, 40));
    $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?? '';

    return substr($code, 0, 40);
}

function coupon_normalize_decimal(mixed $value): float
{
    $value = str_replace(',', '.', coupon_clean_text($value, 20));

    return max(0, round((float) $value, 2));
}

function coupon_normalize_datetime(mixed $value, bool $endOfDay = false): ?string
{
    $value = coupon_clean_text($value, 16);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));
    if (!$date) {
        return null;
    }

    return $date->format('Y-m-d') . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
}

function coupon_find(int $couponId): ?array
{
    if ($couponId <= 0) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM cupons WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $couponId]);
    $coupon = $statement->fetch();

    return $coupon ?: null;
}

function coupon_list(array $filters = []): array
{
    $where = ['1 = 1'];
    $params = [];

    $search = coupon_clean_text($filters['search'] ?? '', 120);
    if ($search !== '') {
        $where[] = '(c.codigo LIKE :search_codigo OR c.campanha LIKE :search_campanha)';
        $params['search_codigo'] = '%' . $search . '%';
        $params['search_campanha'] = '%' . $search . '%';
    }

    $status = coupon_clean_text($filters['status'] ?? '', 40);
    if ($status !== '' && isset(coupon_status_options()[$status])) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }

    $type = coupon_clean_text($filters['tipo'] ?? '', 40);
    if ($type !== '' && isset(coupon_type_options()[$type])) {
        $where[] = 'c.tipo_desconto = :tipo';
        $params['tipo'] = $type;
    }

    $channel = coupon_clean_text($filters['canal'] ?? '', 40);
    if ($channel !== '' && isset(coupon_channel_options()[$channel])) {
        $where[] = 'c.canal = :canal';
        $params['canal'] = $channel;
    }

    $statement = db()->prepare(
        'SELECT c.*, COALESCE(SUM(u.valor_desconto), 0) AS desconto_total_usado
         FROM cupons c
         LEFT JOIN cupom_usos u ON u.cupom_id = c.id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY c.id
         ORDER BY c.status = "ativo" DESC, c.validade_em IS NULL DESC, c.validade_em ASC, c.codigo ASC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function coupon_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "ativo" THEN 1 ELSE 0 END) AS ativos,
            SUM(usos_realizados) AS usos,
            COALESCE(AVG(CASE WHEN tipo_desconto = "percentual" THEN valor_desconto ELSE NULL END), 0) AS desconto_medio_percentual
         FROM cupons'
    )->fetch() ?: [];

    $top = db()->query(
        'SELECT codigo
         FROM cupons
         ORDER BY usos_realizados DESC, atualizado_em DESC
         LIMIT 1'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'ativos' => (int) ($row['ativos'] ?? 0),
        'usos' => (int) ($row['usos'] ?? 0),
        'desconto_medio_percentual' => (float) ($row['desconto_medio_percentual'] ?? 0),
        'maior_campanha' => (string) ($top['codigo'] ?? 'Sem uso'),
    ];
}

function coupon_save_from_request(array $request): int
{
    $couponId = filter_var($request['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $code = coupon_normalize_code((string) ($request['codigo'] ?? ''));
    $campaign = coupon_clean_text($request['campanha'] ?? '', 140);
    $type = coupon_clean_text($request['tipo_desconto'] ?? '', 40);
    $value = coupon_normalize_decimal($request['valor_desconto'] ?? 0);
    $status = coupon_clean_text($request['status'] ?? 'ativo', 40);
    $channel = coupon_clean_text($request['canal'] ?? 'todos', 40);
    $maxUses = filter_var($request['uso_maximo'] ?? null, FILTER_VALIDATE_INT);
    $minValue = coupon_normalize_decimal($request['valor_minimo_pedido'] ?? 0);

    if ($code === '') {
        throw new InvalidArgumentException('Informe um código de cupom válido.');
    }
    if ($campaign === '') {
        throw new InvalidArgumentException('Informe o nome da campanha.');
    }
    if (!isset(coupon_type_options()[$type])) {
        throw new InvalidArgumentException('Tipo de desconto inválido.');
    }
    if (!isset(coupon_status_options()[$status])) {
        throw new InvalidArgumentException('Status de cupom inválido.');
    }
    if (!isset(coupon_channel_options()[$channel])) {
        throw new InvalidArgumentException('Canal do cupom inválido.');
    }
    if ($type === 'percentual' && ($value <= 0 || $value > 100)) {
        throw new InvalidArgumentException('Cupom percentual deve ter valor entre 0,01 e 100.');
    }
    if ($type !== 'percentual' && $value <= 0) {
        throw new InvalidArgumentException('Informe um valor de desconto maior que zero.');
    }
    if ($maxUses !== false && $maxUses !== null && $maxUses < 0) {
        throw new InvalidArgumentException('Uso máximo não pode ser negativo.');
    }

    $existingSql = 'SELECT id FROM cupons WHERE codigo = :codigo';
    $existingParams = ['codigo' => $code];
    if ($couponId > 0) {
        $existingSql .= ' AND id <> :id';
        $existingParams['id'] = $couponId;
    }
    $existingSql .= ' LIMIT 1';
    $statement = db()->prepare($existingSql);
    $statement->execute($existingParams);
    if ($statement->fetch()) {
        throw new InvalidArgumentException('Já existe um cupom com esse código.');
    }

    $params = [
        'codigo' => $code,
        'campanha' => $campaign,
        'tipo_desconto' => $type,
        'valor_desconto' => $value,
        'inicio_em' => coupon_normalize_datetime($request['inicio_em'] ?? null),
        'validade_em' => coupon_normalize_datetime($request['validade_em'] ?? null, true),
        'uso_maximo' => $maxUses !== false && $maxUses !== null ? $maxUses : null,
        'valor_minimo_pedido' => $minValue,
        'status' => $status,
        'canal' => $channel,
        'exibir_checkout' => !empty($request['exibir_checkout']) ? 1 : 0,
        'aplicar_catalogo' => !empty($request['aplicar_catalogo']) ? 1 : 0,
        'limitar_por_categoria' => !empty($request['limitar_por_categoria']) ? 1 : 0,
    ];

    if ($couponId > 0) {
        if (!coupon_find($couponId)) {
            throw new InvalidArgumentException('Cupom não encontrado.');
        }

        $params['id'] = $couponId;
        db()->prepare(
            'UPDATE cupons
             SET codigo = :codigo, campanha = :campanha, tipo_desconto = :tipo_desconto,
                 valor_desconto = :valor_desconto, inicio_em = :inicio_em, validade_em = :validade_em,
                 uso_maximo = :uso_maximo, valor_minimo_pedido = :valor_minimo_pedido,
                 status = :status, canal = :canal, exibir_checkout = :exibir_checkout,
                 aplicar_catalogo = :aplicar_catalogo, limitar_por_categoria = :limitar_por_categoria,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute($params);

        return $couponId;
    }

    db()->prepare(
        'INSERT INTO cupons (
            codigo, campanha, tipo_desconto, valor_desconto, inicio_em, validade_em,
            uso_maximo, valor_minimo_pedido, status, canal, exibir_checkout,
            aplicar_catalogo, limitar_por_categoria
         ) VALUES (
            :codigo, :campanha, :tipo_desconto, :valor_desconto, :inicio_em, :validade_em,
            :uso_maximo, :valor_minimo_pedido, :status, :canal, :exibir_checkout,
            :aplicar_catalogo, :limitar_por_categoria
         )'
    )->execute($params);

    return (int) db()->lastInsertId();
}

function coupon_update_status(int $couponId, string $status): void
{
    if ($couponId <= 0 || !isset(coupon_status_options()[$status])) {
        throw new InvalidArgumentException('Status de cupom inválido.');
    }
    if (!coupon_find($couponId)) {
        throw new InvalidArgumentException('Cupom não encontrado.');
    }

    db()->prepare('UPDATE cupons SET status = :status, atualizado_em = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute(['id' => $couponId, 'status' => $status]);
}

function coupon_badge_class(string $status): string
{
    return match ($status) {
        'ativo' => 'admin-badge-ok',
        'pausado' => 'admin-badge-warn',
        'encerrado', 'expirado' => 'admin-badge-danger',
        default => 'admin-badge-info',
    };
}

function coupon_value_label(array $coupon): string
{
    $value = (float) ($coupon['valor_desconto'] ?? 0);
    if (($coupon['tipo_desconto'] ?? '') === 'percentual') {
        return number_format($value, 0, ',', '.') . '%';
    }

    if (($coupon['tipo_desconto'] ?? '') === 'frete') {
        return 'Frete';
    }

    return money_br($value);
}
