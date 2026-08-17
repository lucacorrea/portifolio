<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;

final class FiscalProductionGate
{
    public function __construct(
        private readonly FiscalConfigurationRepository $repository,
        private readonly FiscalRuntimeReadiness $runtime
    ) {
    }

    /**
     * @param array<string,mixed>|null $configuration
     * @return array{allowed:bool,errors:array<int,string>,checks:array<string,bool>}
     */
    public function inspect(string $model, ?array $configuration): array
    {
        $runtime = $this->runtime->inspect();
        $configurationId = (int) ($configuration['id'] ?? 0);
        return self::evaluate($model, $configuration, [
            'runtime_production_enabled' => $runtime['production_allowed'] === true,
            'schema_compatible' => $this->repository->hasProductionSchema(),
            'production_status_service' => $configurationId > 0
                && $this->repository->hasSuccessfulIntegrationTest($configurationId),
            'authorized_homologation' => $this->repository->hasAuthorizedHomologation($model),
        ]);
    }

    /**
     * @param array<string,mixed>|null $configuration
     * @param array{runtime_production_enabled:bool,schema_compatible:bool,production_status_service:bool,authorized_homologation:bool} $facts
     * @return array{allowed:bool,errors:array<int,string>,checks:array<string,bool>}
     */
    public static function evaluate(string $model, ?array $configuration, array $facts): array
    {
        $checks = [
            'runtime_production_enabled' => $facts['runtime_production_enabled'],
            'schema_compatible' => $facts['schema_compatible'],
            'production_configuration' => is_array($configuration)
                && ($configuration['ambiente'] ?? '') === 'producao'
                && ($configuration['modelo'] ?? '') === $model,
            'production_status_service' => $facts['production_status_service'],
            'authorized_homologation' => $facts['authorized_homologation'],
            'qr_code_v3' => $model !== '65' || (int) ($configuration['qr_code_versao'] ?? 0) === 3,
        ];
        $messages = [
            'runtime_production_enabled' => 'A flag de produção e os requisitos técnicos do servidor ainda não estão liberados.',
            'schema_compatible' => 'Aplique e valide o SQL fiscal final antes de liberar produção.',
            'production_configuration' => 'Crie uma configuração própria para produção e para este modelo.',
            'production_status_service' => 'Teste o status da SEFAZ em produção com esta configuração antes da ativação.',
            'authorized_homologation' => 'Autorize ao menos um documento deste modelo em homologação antes de ativar produção.',
            'qr_code_v3' => 'NFC-e em produção exige QR Code versão 3.',
        ];
        $errors = [];
        foreach ($checks as $key => $ok) {
            if (!$ok) {
                $errors[] = $messages[$key];
            }
        }
        return ['allowed' => $errors === [], 'errors' => $errors, 'checks' => $checks];
    }
}
