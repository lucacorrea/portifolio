<?php

declare(strict_types=1);

/**
 * Endpoint:
 *
 * POST /SO/api/integracoes/fluxempresa/aquisicoes.php
 *
 * Cria uma aquisição no SO a partir de:
 *
 * - orçamento aprovado no Flux Empresas;
 * - ordem de serviço direta aprovada no Flux Empresas.
 *
 * Compatível com PHP 7.2.
 */

require __DIR__ . '/bootstrap.php';

require_once dirname(
    __DIR__,
    3
) . '/config/functions.php';


/**
 * Valida inteiro positivo.
 *
 * @param mixed  $value
 * @param string $field
 * @param bool   $nullable
 * @return int|null
 */
function flux_aq_positive_integer(
    $value,
    $field,
    $nullable
) {
    if (
        $nullable
        && (
            $value === null
            || $value === ''
        )
    ) {
        return null;
    }

    $validated = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        array(
            'options' => array(
                'min_range' => 1,
            ),
        )
    );

    if ($validated === false) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Campo inválido.',
            array(
                'field' => $field,
            )
        );
    }

    return (int) $validated;
}

/**
 * Valida string.
 *
 * @param mixed  $value
 * @param string $field
 * @param int    $maxLength
 * @param bool   $required
 * @return string
 */
function flux_aq_string(
    $value,
    $field,
    $maxLength,
    $required
) {
    if (!is_scalar($value) && $value !== null) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Campo inválido.',
            array(
                'field' => $field,
            )
        );
    }

    $value = trim(
        (string) $value
    );

    if (strpos($value, "\0") !== false) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Campo inválido.',
            array(
                'field' => $field,
            )
        );
    }

    if ($required && $value === '') {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Campo obrigatório.',
            array(
                'field' => $field,
            )
        );
    }

    if (strlen($value) > $maxLength) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Campo excede o tamanho permitido.',
            array(
                'field' => $field,
                'max_length' => $maxLength,
            )
        );
    }

    return $value;
}

/**
 * Converte decimal em inteiro escalado.
 *
 * Exemplos:
 *
 * 10,25 com escala 2 → 1025
 * 2 com escala 2     → 200
 *
 * @param mixed  $value
 * @param int    $scale
 * @param string $field
 * @param int    $maxIntegerDigits
 * @return int
 */
function flux_aq_decimal_scaled(
    $value,
    $scale,
    $field,
    $maxIntegerDigits
) {
    if (is_float($value)) {
        $value = number_format(
            $value,
            $scale,
            '.',
            ''
        );
    }

    if (
        !is_string($value)
        && !is_int($value)
    ) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Valor decimal inválido.',
            array(
                'field' => $field,
            )
        );
    }

    $normalized = trim(
        str_replace(
            ',',
            '.',
            (string) $value
        )
    );

    $pattern = '/^\d{1,'
        . (int) $maxIntegerDigits
        . '}(?:\.\d{1,'
        . (int) $scale
        . '})?$/';

    if (
        preg_match(
            $pattern,
            $normalized
        ) !== 1
    ) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Valor decimal inválido.',
            array(
                'field' => $field,
            )
        );
    }

    $parts = explode(
        '.',
        $normalized,
        2
    );

    $integerPart = $parts[0];

    $decimalPart = isset($parts[1])
        ? $parts[1]
        : '';

    $decimalPart = str_pad(
        $decimalPart,
        $scale,
        '0',
        STR_PAD_RIGHT
    );

    $multiplier = (int) pow(
        10,
        $scale
    );

    $scaled = (
        ((int) $integerPart) * $multiplier
    ) + (int) $decimalPart;

    if ($scaled <= 0) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'O valor deve ser maior que zero.',
            array(
                'field' => $field,
            )
        );
    }

    return $scaled;
}

/**
 * Formata centavos como decimal.
 *
 * @param int $cents
 * @return string
 */
function flux_aq_money_from_cents($cents)
{
    $integer = intdiv(
        (int) $cents,
        100
    );

    $decimal = (int) $cents % 100;

    return $integer
        . '.'
        . str_pad(
            (string) $decimal,
            2,
            '0',
            STR_PAD_LEFT
        );
}

/**
 * Formata quantidade em escala 2.
 *
 * @param int $scaled
 * @return string
 */
function flux_aq_quantity_from_scaled($scaled)
{
    $integer = intdiv(
        (int) $scaled,
        100
    );

    $decimal = (int) $scaled % 100;

    return $integer
        . '.'
        . str_pad(
            (string) $decimal,
            2,
            '0',
            STR_PAD_LEFT
        );
}

/**
 * Obtém trava MySQL.
 *
 * @param PDO    $pdo
 * @param string $name
 * @param int    $timeout
 * @return void
 */
function flux_aq_acquire_lock(
    PDO $pdo,
    $name,
    $timeout
) {
    $statement = $pdo->prepare(
        'SELECT GET_LOCK(
            :lock_name,
            :timeout_seconds
        )'
    );

    $statement->bindValue(
        ':lock_name',
        substr(
            $name,
            0,
            64
        ),
        PDO::PARAM_STR
    );

    $statement->bindValue(
        ':timeout_seconds',
        (int) $timeout,
        PDO::PARAM_INT
    );

    $statement->execute();

    if ((int) $statement->fetchColumn() !== 1) {
        flux_api_error(
            503,
            'RESOURCE_BUSY',
            'A integração está ocupada. Tente novamente.'
        );
    }
}

/**
 * Libera trava MySQL.
 *
 * @param PDO    $pdo
 * @param string $name
 * @return void
 */
function flux_aq_release_lock(
    PDO $pdo,
    $name
) {
    try {
        $statement = $pdo->prepare(
            'SELECT RELEASE_LOCK(
                :lock_name
            )'
        );

        $statement->execute(
            array(
                'lock_name' => substr(
                    $name,
                    0,
                    64
                ),
            )
        );
    } catch (Throwable $exception) {
        flux_api_log_exception(
            'release_lock_failed',
            $exception
        );
    }
}

/**
 * Busca integração existente pela idempotência.
 *
 * @param PDO    $pdo
 * @param string $idempotencyKey
 * @return array|null
 */
function flux_aq_find_existing(
    PDO $pdo,
    $idempotencyKey
) {
    $statement = $pdo->prepare(
        'SELECT
            i.id AS integracao_id,
            i.aquisicao_id,
            i.payload_hash,
            i.status_integracao,
            a.numero_aq,
            a.codigo_entrega,
            a.status,
            a.valor_total,
            a.fornecedor_id,
            a.criado_em
         FROM integracao_fluxempresa_aquisicoes AS i
         INNER JOIN aquisicoes AS a
            ON a.id = i.aquisicao_id
         WHERE i.chave_idempotencia = :chave
         LIMIT 1'
    );

    $statement->execute(
        array(
            'chave' => $idempotencyKey,
        )
    );

    $row = $statement->fetch();

    return is_array($row)
        ? $row
        : null;
}

/**
 * Procura integração existente pela origem.
 *
 * @param PDO      $pdo
 * @param int      $companyId
 * @param int|null $budgetId
 * @param int|null $serviceOrderId
 * @return array|null
 */
function flux_aq_find_existing_origin(
    PDO $pdo,
    $companyId,
    $budgetId,
    $serviceOrderId
) {
    if ($budgetId !== null) {
        $statement = $pdo->prepare(
            'SELECT
                i.aquisicao_id,
                i.chave_idempotencia,
                i.payload_hash,
                a.numero_aq,
                a.codigo_entrega,
                a.status
             FROM integracao_fluxempresa_aquisicoes AS i
             INNER JOIN aquisicoes AS a
                ON a.id = i.aquisicao_id
             WHERE i.empresa_flux_id = :empresa_id
               AND i.orcamento_flux_id = :origem_id
             LIMIT 1'
        );

        $statement->execute(
            array(
                'empresa_id' => $companyId,
                'origem_id' => $budgetId,
            )
        );

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    if ($serviceOrderId !== null) {
        $statement = $pdo->prepare(
            'SELECT
                i.aquisicao_id,
                i.chave_idempotencia,
                i.payload_hash,
                a.numero_aq,
                a.codigo_entrega,
                a.status
             FROM integracao_fluxempresa_aquisicoes AS i
             INNER JOIN aquisicoes AS a
                ON a.id = i.aquisicao_id
             WHERE i.empresa_flux_id = :empresa_id
               AND i.ordem_servico_flux_id = :origem_id
             LIMIT 1'
        );

        $statement->execute(
            array(
                'empresa_id' => $companyId,
                'origem_id' => $serviceOrderId,
            )
        );

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    return null;
}

/**
 * Monta resposta da aquisição existente.
 *
 * @param array $existing
 * @param bool  $idempotent
 * @return array
 */
function flux_aq_existing_response(
    array $existing,
    $idempotent
) {
    return array(
        'success' => true,
        'idempotent' => (bool) $idempotent,
        'aquisicao' => array(
            'id' => (int) $existing['aquisicao_id'],
            'numero' => (string) $existing['numero_aq'],
            'codigo_entrega' => (string) $existing['codigo_entrega'],
            'status' => (string) $existing['status'],
            'valor_total' => isset(
                $existing['valor_total']
            )
                ? (string) $existing['valor_total']
                : null,
            'fornecedor_id' => isset(
                $existing['fornecedor_id']
            )
                ? (int) $existing['fornecedor_id']
                : null,
            'criada_em' => isset(
                $existing['criado_em']
            )
                ? (string) $existing['criado_em']
                : null,
        ),
    );
}


/* =========================================================
 * 1. VALIDAÇÃO DO PAYLOAD
 * ========================================================= */

$idempotencyKey = strtolower(
    flux_aq_string(
        isset($fluxApiPayload['idempotency_key'])
            ? $fluxApiPayload['idempotency_key']
            : '',
        'idempotency_key',
        64,
        true
    )
);

if (
    preg_match(
        '/^[a-f0-9]{64}$/D',
        $idempotencyKey
    ) !== 1
) {
    flux_api_error(
        422,
        'INVALID_IDEMPOTENCY_KEY',
        'A chave de idempotência deve ser um SHA-256 hexadecimal.'
    );
}

$companyId = flux_aq_positive_integer(
    isset($fluxApiPayload['empresa_flux_id'])
        ? $fluxApiPayload['empresa_flux_id']
        : null,
    'empresa_flux_id',
    false
);

$companyUuid = flux_aq_string(
    isset($fluxApiPayload['empresa_flux_uuid'])
        ? $fluxApiPayload['empresa_flux_uuid']
        : '',
    'empresa_flux_uuid',
    36,
    false
);

if (
    $companyUuid !== ''
    && preg_match(
        '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',
        $companyUuid
    ) !== 1
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'UUID da empresa inválido.',
        array(
            'field' => 'empresa_flux_uuid',
        )
    );
}

$origin = flux_aq_string(
    isset($fluxApiPayload['origem'])
        ? $fluxApiPayload['origem']
        : '',
    'origem',
    30,
    true
);

if (
    !in_array(
        $origin,
        array(
            'orcamento',
            'ordem_servico',
        ),
        true
    )
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'Origem da integração inválida.',
        array(
            'field' => 'origem',
        )
    );
}

$budgetId = flux_aq_positive_integer(
    isset($fluxApiPayload['orcamento_flux_id'])
        ? $fluxApiPayload['orcamento_flux_id']
        : null,
    'orcamento_flux_id',
    true
);

$serviceOrderId = flux_aq_positive_integer(
    isset($fluxApiPayload['ordem_servico_flux_id'])
        ? $fluxApiPayload['ordem_servico_flux_id']
        : null,
    'ordem_servico_flux_id',
    true
);

if (
    $origin === 'orcamento'
    && (
        $budgetId === null
        || $serviceOrderId !== null
    )
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'A origem orçamento exige somente orcamento_flux_id.'
    );
}

if (
    $origin === 'ordem_servico'
    && (
        $serviceOrderId === null
        || $budgetId !== null
    )
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'A origem ordem de serviço exige somente ordem_servico_flux_id.'
    );
}

$userId = flux_aq_positive_integer(
    isset($fluxApiPayload['usuario_flux_id'])
        ? $fluxApiPayload['usuario_flux_id']
        : null,
    'usuario_flux_id',
    true
);

$supplierId = flux_aq_positive_integer(
    isset($fluxApiPayload['fornecedor_so_id'])
        ? $fluxApiPayload['fornecedor_so_id']
        : null,
    'fornecedor_so_id',
    false
);

$description = flux_aq_string(
    isset($fluxApiPayload['descricao'])
        ? $fluxApiPayload['descricao']
        : '',
    'descricao',
    2000,
    false
);

$client = isset($fluxApiPayload['cliente'])
    && is_array($fluxApiPayload['cliente'])
        ? $fluxApiPayload['cliente']
        : array();

$clientName = flux_aq_string(
    isset($client['nome'])
        ? $client['nome']
        : '',
    'cliente.nome',
    255,
    true
);

$clientDocument = preg_replace(
    '/\D+/',
    '',
    flux_aq_string(
        isset($client['documento'])
            ? $client['documento']
            : '',
        'cliente.documento',
        30,
        false
    )
);

if ($clientDocument === null) {
    $clientDocument = '';
}

if (
    $clientDocument !== ''
    && !in_array(
        strlen($clientDocument),
        array(
            11,
            14,
        ),
        true
    )
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'Documento do cliente inválido.',
        array(
            'field' => 'cliente.documento',
        )
    );
}

$items = isset($fluxApiPayload['itens'])
    && is_array($fluxApiPayload['itens'])
        ? $fluxApiPayload['itens']
        : array();

if (
    count($items) < 1
    || count($items) > 200
) {
    flux_api_error(
        422,
        'VALIDATION_ERROR',
        'A aquisição deve possuir entre 1 e 200 itens.',
        array(
            'field' => 'itens',
        )
    );
}

$normalizedItems = array();
$calculatedTotalCents = 0;

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'Item inválido.',
            array(
                'field' => 'itens.' . $index,
            )
        );
    }

    $itemDescription = flux_aq_string(
        isset($item['descricao'])
            ? $item['descricao']
            : (
                isset($item['produto'])
                    ? $item['produto']
                    : ''
            ),
        'itens.' . $index . '.descricao',
        255,
        true
    );

    $quantityScaled = flux_aq_decimal_scaled(
        isset($item['quantidade'])
            ? $item['quantidade']
            : null,
        2,
        'itens.' . $index . '.quantidade',
        8
    );

    $unitPriceCents = flux_aq_decimal_scaled(
        isset($item['valor_unitario'])
            ? $item['valor_unitario']
            : null,
        2,
        'itens.' . $index . '.valor_unitario',
        13
    );

    $lineTotalCents = (int) round(
        (
            $quantityScaled
            * $unitPriceCents
        ) / 100
    );

    $calculatedTotalCents += $lineTotalCents;

    if (
        $calculatedTotalCents
        > 999999999999999
    ) {
        flux_api_error(
            422,
            'VALIDATION_ERROR',
            'O valor total excede o limite permitido.'
        );
    }

    $normalizedItems[] = array(
        'descricao' => $itemDescription,
        'quantidade' => flux_aq_quantity_from_scaled(
            $quantityScaled
        ),
        'valor_unitario' => flux_aq_money_from_cents(
            $unitPriceCents
        ),
    );
}

$informedTotalCents = flux_aq_decimal_scaled(
    isset($fluxApiPayload['valor_total'])
        ? $fluxApiPayload['valor_total']
        : null,
    2,
    'valor_total',
    13
);

if (
    abs(
        $informedTotalCents
        - $calculatedTotalCents
    ) > 1
) {
    flux_api_error(
        422,
        'TOTAL_MISMATCH',
        'O valor total não corresponde à soma dos itens.',
        array(
            'valor_informado' => flux_aq_money_from_cents(
                $informedTotalCents
            ),
            'valor_calculado' => flux_aq_money_from_cents(
                $calculatedTotalCents
            ),
        )
    );
}

$normalizedPayload = array(
    'empresa_flux_id' => $companyId,
    'empresa_flux_uuid' => $companyUuid,
    'origem' => $origin,
    'orcamento_flux_id' => $budgetId,
    'ordem_servico_flux_id' => $serviceOrderId,
    'usuario_flux_id' => $userId,
    'fornecedor_so_id' => $supplierId,
    'cliente' => array(
        'nome' => $clientName,
        'documento' => $clientDocument,
    ),
    'descricao' => $description,
    'valor_total' => flux_aq_money_from_cents(
        $calculatedTotalCents
    ),
    'itens' => $normalizedItems,
);

$payloadSnapshot = json_encode(
    $normalizedPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
);

if ($payloadSnapshot === false) {
    flux_api_error(
        500,
        'JSON_ENCODING_ERROR',
        'Não foi possível preparar os dados da aquisição.'
    );
}

$payloadHash = hash(
    'sha256',
    $payloadSnapshot
);


/* =========================================================
 * 2. PROCESSAMENTO
 * ========================================================= */

$idempotencyLock = 'flux_aq_'
    . substr(
        $idempotencyKey,
        0,
        48
    );

$numberLock = 'so_aquisicao_numero_'
    . date('Y');

$idempotencyLockAcquired = false;
$numberLockAcquired = false;

try {
    flux_aq_acquire_lock(
        $pdo,
        $idempotencyLock,
        10
    );

    $idempotencyLockAcquired = true;

    $existing = flux_aq_find_existing(
        $pdo,
        $idempotencyKey
    );

    if ($existing !== null) {
        if (
            !hash_equals(
                (string) $existing['payload_hash'],
                $payloadHash
            )
        ) {
            flux_api_error(
                409,
                'IDEMPOTENCY_CONFLICT',
                'A chave de idempotência já foi utilizada com outros dados.'
            );
        }

        flux_api_response(
            200,
            flux_aq_existing_response(
                $existing,
                true
            )
        );
    }

    $existingOrigin = flux_aq_find_existing_origin(
        $pdo,
        $companyId,
        $budgetId,
        $serviceOrderId
    );

    if ($existingOrigin !== null) {
        flux_api_error(
            409,
            'ORIGIN_ALREADY_LINKED',
            'Este orçamento ou ordem de serviço já possui aquisição no SO.',
            array(
                'aquisicao_id' => (
                    int
                ) $existingOrigin['aquisicao_id'],
                'numero' => (
                    string
                ) $existingOrigin['numero_aq'],
                'status' => (
                    string
                ) $existingOrigin['status'],
            )
        );
    }

    $supplierStatement = $pdo->prepare(
        'SELECT
            id,
            nome,
            cnpj
         FROM fornecedores
         WHERE id = :id
         LIMIT 1'
    );

    $supplierStatement->execute(
        array(
            'id' => $supplierId,
        )
    );

    $supplier = $supplierStatement->fetch();

    if (!is_array($supplier)) {
        flux_api_error(
            422,
            'SUPPLIER_NOT_FOUND',
            'Fornecedor não encontrado no SO.'
        );
    }

    $pdo->beginTransaction();

    flux_aq_acquire_lock(
        $pdo,
        $numberLock,
        10
    );

    $numberLockAcquired = true;

    $acquisitionStatement = $pdo->prepare(
        "INSERT INTO aquisicoes (
            numero_aq,
            codigo_entrega,
            oficio_id,
            fornecedor_id,
            origem,
            valor_total,
            responsavel_entrega,
            status,
            criado_em
        ) VALUES (
            :numero_aq,
            :codigo_entrega,
            NULL,
            :fornecedor_id,
            'FLUX_EMPRESAS',
            :valor_total,
            NULL,
            'ESPERANDO OFICIO',
            NOW()
        )"
    );

    $acquisitionId = 0;
    $acquisitionNumber = '';
    $deliveryCode = '';

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $acquisitionNumber = generate_aquisicao_number(
            $pdo
        );

        $deliveryCode = generate_unique_code(
            $pdo
        );

        try {
            $acquisitionStatement->execute(
                array(
                    'numero_aq' => $acquisitionNumber,
                    'codigo_entrega' => $deliveryCode,
                    'fornecedor_id' => $supplierId,
                    'valor_total' => flux_aq_money_from_cents(
                        $calculatedTotalCents
                    ),
                )
            );

            $acquisitionId = (int) $pdo
                ->lastInsertId();

            break;
        } catch (PDOException $exception) {
            $nativeCode = isset(
                $exception->errorInfo[1]
            )
                ? (int) $exception->errorInfo[1]
                : 0;

            if (
                $nativeCode !== 1062
                || $attempt >= 5
            ) {
                throw $exception;
            }
        }
    }

    if ($acquisitionId <= 0) {
        throw new RuntimeException(
            'Não foi possível gerar a aquisição.'
        );
    }

    $itemStatement = $pdo->prepare(
        'INSERT INTO itens_aquisicao (
            aquisicao_id,
            oficio_item_id,
            produto,
            quantidade,
            valor_unitario
        ) VALUES (
            :aquisicao_id,
            NULL,
            :produto,
            :quantidade,
            :valor_unitario
        )'
    );

    foreach ($normalizedItems as $item) {
        $itemStatement->execute(
            array(
                'aquisicao_id' => $acquisitionId,
                'produto' => $item['descricao'],
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'],
            )
        );
    }

    $response = array(
        'success' => true,
        'idempotent' => false,
        'aquisicao' => array(
            'id' => $acquisitionId,
            'numero' => $acquisitionNumber,
            'codigo_entrega' => $deliveryCode,
            'status' => 'ESPERANDO OFICIO',
            'valor_total' => flux_aq_money_from_cents(
                $calculatedTotalCents
            ),
            'fornecedor_id' => $supplierId,
            'fornecedor_nome' => (
                string
            ) $supplier['nome'],
            'criada_em' => date(
                'Y-m-d H:i:s'
            ),
        ),
    );

    $responseSnapshot = json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if ($responseSnapshot === false) {
        throw new RuntimeException(
            'Não foi possível preparar a resposta da aquisição.'
        );
    }

    $integrationStatement = $pdo->prepare(
        "INSERT INTO integracao_fluxempresa_aquisicoes (
            aquisicao_id,
            empresa_flux_id,
            empresa_flux_uuid,
            orcamento_flux_id,
            ordem_servico_flux_id,
            usuario_flux_id,
            origem_flux,
            chave_idempotencia,
            payload_hash,
            payload_snapshot,
            resposta_snapshot,
            status_integracao,
            tentativas,
            criado_em,
            atualizado_em,
            processado_em
        ) VALUES (
            :aquisicao_id,
            :empresa_flux_id,
            :empresa_flux_uuid,
            :orcamento_flux_id,
            :ordem_servico_flux_id,
            :usuario_flux_id,
            :origem_flux,
            :chave_idempotencia,
            :payload_hash,
            :payload_snapshot,
            :resposta_snapshot,
            'concluida',
            1,
            NOW(),
            NOW(),
            NOW()
        )"
    );

    $integrationStatement->execute(
        array(
            'aquisicao_id' => $acquisitionId,
            'empresa_flux_id' => $companyId,
            'empresa_flux_uuid' => $companyUuid !== ''
                ? $companyUuid
                : null,
            'orcamento_flux_id' => $budgetId,
            'ordem_servico_flux_id' => $serviceOrderId,
            'usuario_flux_id' => $userId,
            'origem_flux' => $origin,
            'chave_idempotencia' => $idempotencyKey,
            'payload_hash' => $payloadHash,
            'payload_snapshot' => $payloadSnapshot,
            'resposta_snapshot' => $responseSnapshot,
        )
    );

    $logStatement = $pdo->prepare(
        'INSERT INTO logs (
            usuario_id,
            secretaria_id,
            acao,
            detalhes,
            ip,
            criado_em
        ) VALUES (
            NULL,
            NULL,
            :acao,
            :detalhes,
            :ip,
            NOW()
        )'
    );

    $logStatement->execute(
        array(
            'acao' => 'FLUXEMPRESA_CRIAR_AQUISICAO',
            'detalhes' => json_encode(
                array(
                    'aquisicao_id' => $acquisitionId,
                    'numero_aquisicao' => $acquisitionNumber,
                    'empresa_flux_id' => $companyId,
                    'orcamento_flux_id' => $budgetId,
                    'ordem_servico_flux_id' => $serviceOrderId,
                    'fornecedor_id' => $supplierId,
                ),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            'ip' => substr(
                (string) (
                    isset($_SERVER['REMOTE_ADDR'])
                        ? $_SERVER['REMOTE_ADDR']
                        : ''
                ),
                0,
                45
            ),
        )
    );

    $pdo->commit();

    flux_aq_release_lock(
        $pdo,
        $numberLock
    );

    $numberLockAcquired = false;

    flux_aq_release_lock(
        $pdo,
        $idempotencyLock
    );

    $idempotencyLockAcquired = false;

    flux_api_response(
        201,
        $response
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($numberLockAcquired) {
        flux_aq_release_lock(
            $pdo,
            $numberLock
        );
    }

    if ($idempotencyLockAcquired) {
        flux_aq_release_lock(
            $pdo,
            $idempotencyLock
        );
    }

    flux_api_log_exception(
        'create_acquisition_failed',
        $exception
    );

    flux_api_error(
        500,
        'ACQUISITION_CREATE_FAILED',
        'Não foi possível criar a aquisição.'
    );
}