<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Core\Env;
use App\Repositories\ProductRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\BarcodeProductLookupService;

header('Content-Type: application/json; charset=utf-8');

function barcodeLookupJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function barcodeLookupInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : $_POST;
}

function barcodeLookupNormalize(string $barcode): string
{
    $barcode = preg_replace('/[\x00-\x1F\x7F\s-]+/u', '', $barcode) ?? '';
    $barcode = trim($barcode);

    return function_exists('mb_substr') ? mb_substr($barcode, 0, 80) : substr($barcode, 0, 80);
}

function barcodeLookupInternalAllowed(): bool
{
    $limit = max(0, Env::int('PRODUCT_LOOKUP_SESSION_LIMIT', 0));
    if ($limit === 0) {
        return true;
    }

    $windowSeconds = max(1, Env::int('PRODUCT_LOOKUP_WINDOW_SECONDS', 300));
    $now = time();
    $bucket = $_SESSION['product_barcode_lookup'] ?? ['start' => $now, 'count' => 0];

    if (!is_array($bucket) || ($now - (int)($bucket['start'] ?? 0)) > $windowSeconds) {
        $bucket = ['start' => $now, 'count' => 0];
    }

    if ((int)($bucket['count'] ?? 0) >= $limit) {
        $_SESSION['product_barcode_lookup'] = $bucket;
        return false;
    }

    $bucket['count'] = (int)$bucket['count'] + 1;
    $_SESSION['product_barcode_lookup'] = $bucket;

    return true;
}

function barcodeLookupProvidersChecked(array $attempts): array
{
    $providers = [];

    foreach ($attempts as $attempt) {
        $provider = preg_replace('/[^0-9A-Za-z_.:-]/', '', (string)($attempt['provider'] ?? '')) ?? '';
        if ($provider !== '') {
            $providers[] = $provider;
        }
    }

    return array_values(array_unique($providers));
}

function barcodeLookupAttemptReasons(array $attempts): array
{
    $reasons = [];

    foreach ($attempts as $attempt) {
        $reason = preg_replace('/[^0-9A-Za-z_.:-]/', '', (string)($attempt['reason'] ?? '')) ?? '';
        if ($reason !== '') {
            $reasons[] = $reason;
        }
    }

    return array_values(array_unique($reasons));
}

$empresaId = 0;
$barcode = '';

try {
    if (!Auth::check()) {
        barcodeLookupJson([
            'success' => false,
            'code' => 'unauthenticated',
            'message' => 'Sessão expirada. Faça login novamente.',
        ], 401);
    }

    Auth::requireLogin();

    $user = Auth::user();
    $empresaId = (int)($user['empresa_id'] ?? 0);
    $currentNivel = (string)($user['nivel'] ?? '');

    if (!in_array($currentNivel, ['admin', 'gerente', 'estoquista'], true)) {
        barcodeLookupJson([
            'success' => false,
            'code' => 'forbidden',
            'message' => 'Acesso negado.',
        ], 403);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'method_not_allowed',
            'message' => 'Método não permitido.',
        ], 405);
    }

    $payload = barcodeLookupInput();
    if (!Csrf::validate((string)($payload['csrf_token'] ?? ''))) {
        barcodeLookupJson([
            'success' => false,
            'code' => 'csrf_invalid',
            'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
        ], 419);
    }

    $barcode = barcodeLookupNormalize((string)($payload['barcode'] ?? ''));
    $format = (string)($payload['format'] ?? '');

    $local = $barcode !== '' ? (new ProductRepository())->findByBarcode($empresaId, $barcode) : null;
    if ($local !== null) {
        barcodeLookupJson([
            'success' => true,
            'source' => 'local',
            'exists' => true,
            'message' => 'Produto já cadastrado.',
            'product' => $local,
            'edit_url' => 'produto-form.php?id=' . (int)$local['id'],
        ]);
    }

    if (!barcodeLookupInternalAllowed()) {
        barcodeLookupJson([
            'success' => false,
            'code' => 'lookup_session_limit',
            'message' => 'O limite interno de consultas foi atingido. Continue o cadastro manualmente.',
        ], 429);
    }

    $service = new BarcodeProductLookupService();
    $result = $service->lookup($empresaId, $barcode, $format);

    if (($result['source'] ?? '') === 'none') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'product_not_found',
            'message' => 'Produto não encontrado nas bases consultadas. Complete o cadastro manualmente.',
            'barcode' => $result['barcode'] ?? $barcode,
            'providers_checked' => barcodeLookupProvidersChecked($result['attempts'] ?? []),
        ]);
    }

    if (($result['source'] ?? '') === 'rate_limit') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'rate_limit',
            'message' => 'O serviço externo atingiu o limite de consultas. Tente novamente depois ou cadastre manualmente.',
            'barcode' => $result['barcode'] ?? $barcode,
            'providers_checked' => barcodeLookupProvidersChecked($result['attempts'] ?? []),
            'retry_after' => $result['retry_after'] ?? null,
        ], 429);
    }

    if (($result['source'] ?? '') === 'unavailable') {
        $reasons = barcodeLookupAttemptReasons($result['attempts'] ?? []);
        $code = 'providers_unavailable';
        $message = 'Não foi possível consultar as bases externas agora. Continue o cadastro manualmente.';
        $status = 503;

        if ($reasons && count(array_diff($reasons, ['provider_not_configured'])) === 0) {
            $code = 'provider_not_configured';
            $message = 'A integração externa ainda não foi configurada. Continue o cadastro manualmente.';
        } elseif (in_array('curl_unavailable', $reasons, true)) {
            $code = 'curl_unavailable';
            $message = 'O servidor não possui suporte para consulta externa. Continue o cadastro manualmente.';
        } elseif (in_array('timeout', $reasons, true)) {
            $code = 'provider_timeout';
            $message = 'A consulta externa demorou demais. Tente novamente ou continue manualmente.';
            $status = 504;
        }

        barcodeLookupJson([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'barcode' => $result['barcode'] ?? $barcode,
            'providers_checked' => barcodeLookupProvidersChecked($result['attempts'] ?? []),
        ], $status);
    }

    barcodeLookupJson([
        'success' => true,
        'source' => $result['source'],
        'exists' => (bool)($result['exists'] ?? false),
        'message' => $result['message'],
        'product' => $result['product'],
        'providers_checked' => barcodeLookupProvidersChecked($result['attempts'] ?? []),
    ]);
} catch (InvalidArgumentException $e) {
    barcodeLookupJson([
        'success' => false,
        'code' => 'invalid_barcode',
        'message' => $e->getMessage() ?: 'Código de barras inválido.',
    ], 422);
} catch (RuntimeException $e) {
    $reason = $e->getMessage();
    $safeBarcode = preg_replace('/[^0-9A-Za-z_-]/', '', $barcode) ?? '';

    log_app_message(sprintf(
        "[%s] Product lookup failure: reason=%s empresa_id=%d barcode=%s\n",
        date('Y-m-d H:i:s'),
        preg_replace('/[^0-9A-Za-z_-]/', '', $reason) ?? 'unknown',
        $empresaId,
        $safeBarcode
    ));

    if ($reason === 'provider_not_configured') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'provider_not_configured',
            'message' => 'A integração externa ainda não foi configurada. Continue o cadastro manualmente.',
        ], 503);
    }

    if ($reason === 'curl_unavailable') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'curl_unavailable',
            'message' => 'O servidor não possui suporte para consulta externa. Continue o cadastro manualmente.',
        ], 503);
    }

    if ($reason === 'timeout') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'provider_timeout',
            'message' => 'A consulta externa demorou demais. Tente novamente ou continue manualmente.',
        ], 504);
    }

    if ($reason === 'rate_limit') {
        barcodeLookupJson([
            'success' => false,
            'code' => 'rate_limit',
            'message' => 'O serviço externo atingiu o limite de consultas. Tente novamente depois ou cadastre manualmente.',
        ], 429);
    }

    barcodeLookupJson([
        'success' => false,
        'code' => 'provider_unavailable',
        'message' => 'A consulta externa está indisponível. Continue o cadastro manualmente.',
    ], 503);
} catch (Throwable $e) {
    log_app_exception($e);
    barcodeLookupJson([
        'success' => false,
        'code' => 'internal_error',
        'message' => 'Não foi possível consultar o produto agora.',
    ], 500);
}
