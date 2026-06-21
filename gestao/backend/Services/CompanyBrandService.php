<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanyRepository;

final class CompanyBrandService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private CompanyRepository $companies;

    private array $cache = [];

    public function __construct(?CompanyRepository $companies = null)
    {
        $this->companies = $companies ?? new CompanyRepository();
    }

    public function getForCompany(int $empresaId, string $urlPrefix = ''): array
    {
        if ($empresaId <= 0) {
            return $this->emptyBrand($empresaId);
        }

        if (!isset($this->cache[$empresaId])) {
            $company = $this->companies->findById($empresaId) ?? [];
            $displayName = $this->displayName($company);
            $logo = $this->resolveLogo($empresaId, (string)($company['logo'] ?? ''));

            $this->cache[$empresaId] = [
                'company_id' => $empresaId,
                'name' => $displayName,
                'legal_name' => trim((string)($company['nome'] ?? '')),
                'fantasy_name' => trim((string)($company['nome_fantasia'] ?? '')),
                'logo_path' => $logo['path'],
                'logo_mime' => $logo['mime'],
                'logo_mtime' => $logo['mtime'],
                'initials' => $this->initials($displayName),
                'has_logo' => $logo['path'] !== '',
                'updated_at' => (string)($company['atualizado_em'] ?? ''),
            ];
        }

        $brand = $this->cache[$empresaId];
        $brand['logo_url'] = $brand['logo_path'] !== ''
            ? $this->prefixUrl($urlPrefix, $brand['logo_path'])
            : '';

        return $brand;
    }

    public function versionFor(array $brand, array $settings = []): string
    {
        $parts = [
            (string)($brand['company_id'] ?? ''),
            (string)($brand['name'] ?? ''),
            (string)($settings['app_name'] ?? ''),
            (string)($settings['app_short_name'] ?? ''),
            (string)($brand['logo_path'] ?? ''),
            (string)($brand['updated_at'] ?? ''),
            (string)($brand['logo_mtime'] ?? ''),
            (string)($settings['branding_updated_at'] ?? ''),
        ];

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    private function emptyBrand(int $empresaId): array
    {
        return [
            'company_id' => $empresaId,
            'name' => '',
            'legal_name' => '',
            'fantasy_name' => '',
            'logo_path' => '',
            'logo_url' => '',
            'logo_mime' => '',
            'logo_mtime' => '',
            'initials' => '',
            'has_logo' => false,
            'updated_at' => '',
        ];
    }

    private function displayName(array $company): string
    {
        $fantasyName = trim((string)($company['nome_fantasia'] ?? ''));
        $legalName = trim((string)($company['nome'] ?? ''));

        return $fantasyName !== '' ? $fantasyName : $legalName;
    }

    private function resolveLogo(int $empresaId, string $storedLogo): array
    {
        $path = ltrim(str_replace('\\', '/', trim($storedLogo)), '/');
        $allowedDirectory = sprintf('uploads/empresas/%d/', $empresaId);

        if (
            $path === ''
            || !str_starts_with($path, $allowedDirectory)
            || str_contains($path, '../')
            || str_contains($path, '..\\')
            || preg_match('/^[a-zA-Z]:[\/\\\\]/', $path)
        ) {
            return ['path' => '', 'mime' => '', 'mtime' => ''];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['path' => '', 'mime' => '', 'mtime' => ''];
        }

        $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($absolutePath)) {
            return ['path' => '', 'mime' => '', 'mtime' => ''];
        }

        $mimeType = $this->detectMimeType($absolutePath);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return ['path' => '', 'mime' => '', 'mtime' => ''];
        }

        return [
            'path' => $path,
            'mime' => $mimeType,
            'mtime' => (string)(filemtime($absolutePath) ?: ''),
        ];
    }

    private function detectMimeType(string $absolutePath): string
    {
        if (!class_exists(\finfo::class)) {
            return '';
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($absolutePath);

        return is_string($mimeType) ? strtolower(trim($mimeType)) : '';
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return '';
        }

        $first = (string)$parts[0];
        $last = count($parts) > 1 ? (string)$parts[count($parts) - 1] : '';

        $initials = $this->firstChar($first) . ($last !== '' ? $this->firstChar($last) : '');

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($initials, 'UTF-8')
            : strtoupper($initials);
    }

    private function firstChar(string $value): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, 1, 'UTF-8')
            : substr($value, 0, 1);
    }

    private function prefixUrl(string $prefix, string $path): string
    {
        return rtrim($prefix, '/') === ''
            ? $path
            : rtrim($prefix, '/') . '/' . $path;
    }
}
