<?php

namespace FluxEmpresa\Core;

use Throwable;

class Audit
{
    public static function record(string $action, ?array $user = null, array|string|null $details = null): void
    {
        try {
            $pdo = Database::connection();

            $stmt = $pdo->prepare(
                'INSERT INTO logs (empresa_id, usuario_id, acao, detalhes, ip, user_agent, criado_em)
                 VALUES (:empresa_id, :usuario_id, :acao, :detalhes, :ip, :user_agent, :criado_em)'
            );

            $stmt->execute([
                'empresa_id' => self::valueFromUser($user, 'empresa_id', Auth::empresaId()),
                'usuario_id' => self::valueFromUser($user, 'id', Auth::id()),
                'acao' => $action,
                'detalhes' => self::formatDetails($details),
                'ip' => self::clientIp(),
                'user_agent' => self::userAgent(),
                'criado_em' => now(),
            ]);
        } catch (Throwable $exception) {
            error_log('FluxEmpresa audit log failed: ' . $exception->getMessage());
        }
    }

    private static function valueFromUser(?array $user, string $key, mixed $fallback): mixed
    {
        if ($user !== null && array_key_exists($key, $user)) {
            return $user[$key] !== null ? $user[$key] : null;
        }

        return $fallback;
    }

    private static function formatDetails(array|string|null $details): ?string
    {
        if ($details === null) {
            return null;
        }

        if (is_string($details)) {
            return $details;
        }

        $encoded = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }

    private static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
    }

    private static function userAgent(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($userAgent) && $userAgent !== '' ? substr($userAgent, 0, 255) : null;
    }
}
