<?php

declare(strict_types=1);

namespace App\Core;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Service\FiscalAuthorizationService;
use App\Fiscal\Service\FiscalConfigurationService;
use App\Fiscal\Service\FiscalDocumentPrintService;
use App\Fiscal\Service\FiscalDocumentService;
use App\Fiscal\Service\FiscalDocumentXmlBuilder;
use App\Fiscal\Service\FiscalToolsFactory;
use App\Fiscal\Service\FiscalRuntimeReadiness;
use App\Fiscal\Service\FiscalSefazConnectionService;
use App\Fiscal\Storage\FiscalCertificateStorage;
use App\Fiscal\Storage\FiscalDocumentStorage;
use App\Nfse\Repository\NfseDocumentRepository;
use App\Nfse\Service\NfseDocumentService;
use App\Nfse\Service\NfseProviderFactory;
use App\Nfse\Storage\NfseDocumentStorage;

trait FiscalApplicationServices
{
    private ?FiscalConfigurationService $fiscalConfigurationService = null;
    private ?FiscalRuntimeReadiness $fiscalRuntimeReadiness = null;
    private ?FiscalSefazConnectionService $fiscalSefazConnectionService = null;
    private ?FiscalDocumentService $fiscalDocumentService = null;
    private ?FiscalDocumentPrintService $fiscalDocumentPrintService = null;
    private ?FiscalAuthorizationService $fiscalAuthorizationService = null;
    private ?NfseDocumentService $nfseDocumentService = null;

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
                FiscalCertificateStorage::forProjectRoot($projectRoot),
                $this->fiscalRuntimeReadiness()
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

    public function fiscalSefazConnection(): FiscalSefazConnectionService
    {
        if ($this->fiscalSefazConnectionService === null) {
            $connection = $this->database->connection();
            $projectRoot = (string) ($this->settings['project_root'] ?? dirname(__DIR__, 2));
            $this->fiscalSefazConnectionService = new FiscalSefazConnectionService(
                new FiscalConfigurationRepository($connection),
                FiscalSecretVault::fromEnvironment(),
                FiscalCertificateStorage::forProjectRoot($projectRoot),
                $this->fiscalRuntimeReadiness()
            );
        }

        return $this->fiscalSefazConnectionService;
    }
    public function fiscalDocuments(): FiscalDocumentService
    {
        if ($this->fiscalDocumentService === null) {
            $this->fiscalDocumentService = new FiscalDocumentService(
                new FiscalDocumentRepository($this->database->connection()),
                $this->fiscalConfiguration(),
                $this->fiscalRuntimeReadiness()
            );
        }

        return $this->fiscalDocumentService;
    }

    public function fiscalAuthorization(): FiscalAuthorizationService
    {
        if ($this->fiscalAuthorizationService === null) {
            $connection = $this->database->connection();
            $projectRoot = (string) ($this->settings['project_root'] ?? dirname(__DIR__, 2));
            $configurationRepository = new FiscalConfigurationRepository($connection);
            $this->fiscalAuthorizationService = new FiscalAuthorizationService(
                new FiscalDocumentRepository($connection),
                new FiscalDocumentXmlBuilder(),
                new FiscalToolsFactory(
                    $configurationRepository,
                    FiscalSecretVault::fromEnvironment(),
                    FiscalCertificateStorage::forProjectRoot($projectRoot),
                    $this->fiscalRuntimeReadiness()
                ),
                FiscalDocumentStorage::forProjectRoot($projectRoot)
            );
        }

        return $this->fiscalAuthorizationService;
    }
    public function fiscalDocumentPrinter(): FiscalDocumentPrintService
    {
        if ($this->fiscalDocumentPrintService === null) {
            $projectRoot = (string) ($this->settings['project_root'] ?? dirname(__DIR__, 2));
            $this->fiscalDocumentPrintService = new FiscalDocumentPrintService(
                new FiscalDocumentRepository($this->database->connection()),
                FiscalDocumentStorage::forProjectRoot($projectRoot)
            );
        }

        return $this->fiscalDocumentPrintService;
    }

    public function nfseDocuments(): NfseDocumentService
    {
        if ($this->nfseDocumentService === null) {
            $connection = $this->database->connection();
            $projectRoot = (string)($this->settings['project_root'] ?? dirname(__DIR__, 2));
            $this->nfseDocumentService = new NfseDocumentService(
                new NfseDocumentRepository($connection),
                new FiscalDocumentRepository($connection),
                new NfseProviderFactory(
                    FiscalSecretVault::fromEnvironment(),
                    FiscalCertificateStorage::forProjectRoot($projectRoot)
                ),
                NfseDocumentStorage::forProjectRoot($projectRoot),
                $this->fiscalRuntimeReadiness()
            );
        }
        return $this->nfseDocumentService;
    }
}
