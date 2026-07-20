<?php

declare(strict_types=1);

namespace App\Core;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Service\FiscalConfigurationService;
use App\Fiscal\Service\FiscalRuntimeReadiness;
use App\Fiscal\Storage\FiscalCertificateStorage;

trait FiscalApplicationServices
{
    private ?FiscalConfigurationService $fiscalConfigurationService = null;
    private ?FiscalRuntimeReadiness $fiscalRuntimeReadiness = null;

    public function fiscalConfiguration(): FiscalConfigurationService
    {
        if ($this->fiscalConfigurationService === null) {
            $masterKey = getenv('FISCAL_MASTER_KEY');
            $vault = null;
            if (is_string($masterKey) && trim($masterKey) !== '') {
                try {
                    $vault = new FiscalSecretVault($masterKey);
                } catch (\InvalidArgumentException) {
                    $vault = null;
                }
            }
            $connection = $this->database->connection();
            $projectRoot = (string) ($this->settings['project_root'] ?? dirname(__DIR__, 2));
            $this->fiscalConfigurationService = new FiscalConfigurationService(
                new FiscalConfigurationRepository($connection),
                $vault,
                FiscalCertificateStorage::forProjectRoot($projectRoot)
            );
        }

        return $this->fiscalConfigurationService;
    }

    public function fiscalRuntimeReadiness(): FiscalRuntimeReadiness
    {
        if ($this->fiscalRuntimeReadiness === null) {
            $this->fiscalRuntimeReadiness = FiscalRuntimeReadiness::fromRuntime(
                (bool) ($this->settings['fiscal_integration_enabled'] ?? false),
                (bool) ($this->settings['fiscal_production_enabled'] ?? false)
            );
        }

        return $this->fiscalRuntimeReadiness;
    }
}
