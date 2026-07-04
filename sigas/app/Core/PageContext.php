<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;

final class PageContext
{
    /**
     * @return array{user: array{name: string, initials: string, jobTitle: string, sector: string}, urls: array{dashboard: string, logout: string}, csrf: array{logout: string}}
     */
    public static function requireAuthenticatedFrontendContext(): array
    {
        $pdo = Database::connection();
        $userRepository = new UserRepository($pdo);
        $sessionRepository = new UserSessionRepository($pdo);
        $accessLevelRepository = new AccessLevelRepository($pdo);
        $auditService = new AuditService(new AuditLogRepository($pdo));
        $authService = new AuthService($userRepository, $sessionRepository, $accessLevelRepository, $auditService);
        $user = $authService->requireUser();
        $level = $user->nivelId === null ? null : $accessLevelRepository->findById($user->nivelId);
        $sector = $user->setorId === null ? null : (new SectorRepository($pdo))->findById($user->setorId);

        return [
            'user' => [
                'name' => $user->nome,
                'initials' => self::initials($user->nome),
                'jobTitle' => $user->cargo ?: ($level?->nome ?? 'Usuario'),
                'sector' => $sector?->nome ?: 'Sem setor',
            ],
            'urls' => [
                'dashboard' => 'dashboard.php',
                'logout' => 'sair.php',
            ],
            'csrf' => [
                'logout' => Csrf::token('logout'),
            ],
        ];
    }

    public static function script(array $context): string
    {
        $json = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if (!is_string($json)) {
            throw new \RuntimeException('Nao foi possivel serializar o contexto da pagina.');
        }

        return '<script>window.SIGAS_CONTEXT = ' . $json . ';</script>';
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= mb_substr($part, 0, 1);
            }

            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return mb_strtoupper($letters !== '' ? $letters : 'U');
    }
}
