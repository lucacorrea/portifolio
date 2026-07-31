<?php
declare(strict_types=1);
namespace App\Integration\SO;
final class SoApiClient {
    public function __construct(private readonly SoConfiguration $config) {}
    public function createAcquisition(string $eventUuid, array $payload): SoAcquisitionResponse {
        if (!function_exists('curl_init')) throw new SoIntegrationException('Cliente HTTP indisponível.');
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $url = $this->config->url(); $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/'); $timestamp = (string) time(); $nonce = $this->uuid();
        $canonical = implode("\n", [$timestamp, $nonce, 'POST', $path, hash('sha256', $json)]);
        $signature = hash_hmac('sha256', $canonical, $this->config->secret());
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout(), CURLOPT_TIMEOUT => $this->config->timeout(), CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => $this->config->verifyTls(), CURLOPT_SSL_VERIFYHOST => $this->config->verifyTls() ? 2 : 0, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Client-Id: ' . $this->config->clientId(), 'X-Timestamp: ' . $timestamp, 'X-Nonce: ' . $nonce, 'X-Idempotency-Key: ' . $eventUuid, 'X-Signature: ' . $signature]]);
        $response = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        if (!is_string($response) || $error !== '' || $status < 200 || $status >= 300 || strlen($response) > 1048576) throw new SoIntegrationException('Não foi possível comunicar com o SO.');
        try { $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR); return SoAcquisitionResponse::fromArray(is_array($decoded) ? $decoded : []); } catch (\Throwable $e) { throw new SoIntegrationException('Resposta inválida do SO.', 0, $e); }
    }
    private function uuid(): string { $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 15) | 64); $b[8] = chr((ord($b[8]) & 63) | 128); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); }
}
