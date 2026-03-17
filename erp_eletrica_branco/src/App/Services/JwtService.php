<?php
namespace App\Services;

class JwtService {
    private static $secret = 'ERP_ELECTRICA_SECRET_KEY_2026'; // In production, move to env/config

    public static function generate(array $payload, int $expiry = 3600): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + $expiry;
        $payload = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validate(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($header, $payload, $signature) = $parts;

        $validSignature = self::base64UrlEncode(hash_hmac('sha256', $header . "." . $payload, self::$secret, true));

        if ($signature !== $validSignature) return null;

        $payloadData = json_decode(self::base64UrlDecode($payload), true);
        if (($payloadData['exp'] ?? 0) < time()) return null;

        return $payloadData;
    }

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
