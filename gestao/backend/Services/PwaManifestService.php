<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;

final class PwaManifestService
{
    private CompanyBrandService $brands;
    private SettingRepository $settings;
    private PwaIconService $icons;

    public function __construct(
        ?CompanyBrandService $brands = null,
        ?SettingRepository $settings = null,
        ?PwaIconService $icons = null
    ) {
        $this->brands = $brands ?? new CompanyBrandService();
        $this->settings = $settings ?? new SettingRepository();
        $this->icons = $icons ?? new PwaIconService();
    }

    public function manifestForCompany(int $empresaId): array
    {
        $brand = $this->brands->getForCompany($empresaId);
        $settings = $this->settings->getAll($empresaId);
        $appName = $this->appName($brand, $settings);
        $shortName = $this->shortName($appName, (string)($settings['app_short_name'] ?? ''));
        $icons = [];

        if (!empty($brand['has_logo'])) {
            try {
                $icons = $this->icons->iconsForBrand($brand);
            } catch (\Throwable $e) {
                log_app_exception($e);
                $icons = [];
            }
        }

        return [
            'id' => './app/empresa/' . $empresaId,
            'name' => $appName,
            'short_name' => $shortName,
            'start_url' => './index.php?source=pwa&empresa=' . $empresaId,
            'scope' => './',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#F1F5FC',
            'theme_color' => '#1657A7',
            'icons' => $icons,
        ];
    }

    public function appSettingsForCompany(int $empresaId): array
    {
        $brand = $this->brands->getForCompany($empresaId);
        $settings = $this->settings->getAll($empresaId);
        $appName = $this->appName($brand, $settings);

        return [
            'app_name' => $appName,
            'app_short_name' => $this->shortName($appName, (string)($settings['app_short_name'] ?? '')),
            'branding_updated_at' => (string)($settings['branding_updated_at'] ?? ''),
            'version' => $this->brands->versionFor($brand, $settings),
        ];
    }

    private function appName(array $brand, array $settings): string
    {
        $configured = trim((string)($settings['app_name'] ?? ''));
        if ($configured !== '') {
            return mb_substr($configured, 0, 180);
        }

        $name = trim((string)($brand['name'] ?? ''));
        return $name !== '' ? $name : 'Sistema de Gestão';
    }

    private function shortName(string $appName, string $configured): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            return mb_substr($configured, 0, 40);
        }

        return mb_substr($appName, 0, 40);
    }
}
