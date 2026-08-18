<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Repository\FiscalInutilizationRepository;
use App\Fiscal\Storage\FiscalDocumentStorage;
use InvalidArgumentException;
use Throwable;

final class FiscalInutilizationService
{
    private readonly SefazResponseParser $responseParser;

    public function __construct(
        private readonly FiscalInutilizationRepository $repository,
        private readonly FiscalConfigurationRepository $configurations,
        private readonly FiscalToolsFactory $toolsFactory,
        private readonly FiscalDocumentStorage $storage,
        private readonly FiscalRuntimeReadiness $runtime,
        private readonly FiscalProductionGate $productionGate,
        ?SefazResponseParser $responseParser = null
    ) {
        $this->responseParser = $responseParser ?? new SefazResponseParser();
    }

    /** @param array<string,mixed> $input @return array{id:int,status:string,cstat:string,reason:string} */
    public function request(array $input, int $userId, bool $allowProduction = false): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para inutilização.');
        }
        $environment = FiscalConfigurationService::environment((string) ($input['ambiente'] ?? ''));
        $model = trim((string) ($input['modelo'] ?? ''));
        if (!in_array($model, ['55', '65'], true)) {
            throw new InvalidArgumentException('Modelo inválido para inutilização.');
        }
        $configurationId = $this->integer($input['configuracao_id'] ?? null, 1, PHP_INT_MAX, 'Configuração inválida.');
        $series = $this->integer($input['serie'] ?? null, 0, 999, 'Série inválida.');
        $year = $this->integer($input['ano'] ?? null, 2006, 2099, 'Ano fiscal inválido.');
        $start = $this->integer($input['numero_inicial'] ?? null, 1, 999999999, 'Número inicial inválido.');
        $end = $this->integer($input['numero_final'] ?? null, $start, 999999999, 'Número final inválido.');
        if ($end - $start > 9999) {
            throw new InvalidArgumentException('A faixa de inutilização não pode exceder 10.000 números.');
        }
        $justification = trim((string) ($input['justificativa'] ?? ''));
        if (mb_strlen($justification) < 15 || mb_strlen($justification) > 255
            || str_contains($justification, "\0") || $justification !== strip_tags($justification)
        ) {
            throw new InvalidArgumentException('Informe justificativa entre 15 e 255 caracteres, sem HTML.');
        }
        $key = strtolower(trim((string) ($input['idempotency_key'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
            throw new InvalidArgumentException('Token de inutilização inválido.');
        }
        $existing = $this->repository->findByIdempotencyKey($key);
        if ($existing !== null) {
            $this->assertSameRequest($existing, [
                'environment' => $environment,
                'model' => $model,
                'configuration_id' => $configurationId,
                'series' => $series,
                'year' => $year,
                'start_number' => $start,
                'end_number' => $end,
                'justification' => $justification,
            ]);
            return $this->result($existing);
        }
        $profile = $this->configurations->connectionProfile($configurationId);
        if ($profile === null || $profile['ambiente'] !== $environment || $profile['modelo'] !== $model) {
            throw new InvalidArgumentException('A configuração não pertence ao ambiente e modelo informados.');
        }
        if (($profile['status'] ?? '') !== 'ativa') {
            throw new InvalidArgumentException('Ative a configuração fiscal antes de solicitar inutilização.');
        }
        $readiness = $this->runtime->inspect();
        if (!$readiness['homologation_ready']
            || ($environment === 'producao' && (!$allowProduction || !$readiness['production_allowed']))
        ) {
            throw new InvalidArgumentException('O gate técnico não libera inutilização neste ambiente.');
        }
        if ($environment === 'producao') {
            $gate = $this->productionGate->inspect($model, $profile);
            if (!$gate['allowed']) {
                throw new InvalidArgumentException((string) ($gate['errors'][0] ?? 'O gate de produção não foi concluído.'));
            }
        }

        $id = $this->repository->create([
            'environment' => $environment,
            'model' => $model,
            'configuration_id' => $configurationId,
            'series' => $series,
            'year' => $year,
            'start_number' => $start,
            'end_number' => $end,
            'justification' => $justification,
            'idempotency_key' => $key,
            'user_id' => $userId,
        ]);
        try {
            $tools = $this->toolsFactory->create($configurationId, $model);
        } catch (Throwable $exception) {
            $reason = 'Falha local antes do envio. Corrija certificado/configuração e gere uma nova solicitação.';
            $this->repository->finish($id, 'rejeitado', '', $reason, '', null, null);
            FiscalSafeLogger::record($exception, 'inutilization_prepare');
            return ['id' => $id, 'status' => 'rejeitado', 'cstat' => '', 'reason' => $reason];
        }
        try {
            $responseXml = $tools->sefazInutiliza(
                $series,
                $start,
                $end,
                $justification,
                $environment === 'producao' ? 1 : 2,
                substr((string) $year, -2)
            );
        } catch (Throwable $exception) {
            $request = $this->storeIfXml($environment, $model, $id, 'inutilizacao_pedido', (string) $tools->lastRequest);
            $reason = 'Comunicação inconclusiva. Confirme a faixa na SEFAZ antes de qualquer nova tentativa.';
            $this->repository->finish($id, 'pendente_confirmacao', '', $reason, '', $request, null);
            FiscalSafeLogger::record($exception, 'inutilization');
            return ['id' => $id, 'status' => 'pendente_confirmacao', 'cstat' => '', 'reason' => $reason];
        }

        $request = $this->storeIfXml($environment, $model, $id, 'inutilizacao_pedido', (string) $tools->lastRequest);
        $response = $this->storeIfXml($environment, $model, $id, 'inutilizacao_resposta', $responseXml);
        try {
            $parsed = $this->responseParser->inutilization($responseXml);
        } catch (InvalidArgumentException) {
            $reason = 'Resposta preservada, mas sem resultado conclusivo. Confirme a faixa na SEFAZ.';
            $this->repository->finish($id, 'pendente_confirmacao', '', $reason, '', $request, $response);
            return ['id' => $id, 'status' => 'pendente_confirmacao', 'cstat' => '', 'reason' => $reason];
        }
        $status = $parsed['terminal']
            ? 'autorizado'
            : ($parsed['pending'] ? 'pendente_confirmacao' : 'rejeitado');
        $this->repository->finish(
            $id, $status, $parsed['cstat'], $parsed['reason'], $parsed['protocol'], $request, $response
        );
        return ['id' => $id, 'status' => $status, 'cstat' => $parsed['cstat'], 'reason' => $parsed['reason']];
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(string $environment, string $model): array
    {
        return $this->repository->recent(
            FiscalConfigurationService::environment($environment),
            in_array($model, ['55', '65'], true) ? $model : throw new InvalidArgumentException('Modelo inválido.')
        );
    }

    /** @return array{reference:string,sha256:string}|null */
    private function storeIfXml(string $environment, string $model, int $id, string $type, string $xml): ?array
    {
        return trim($xml) === '' ? null : $this->storage->store($environment, $model, $id, $type, $xml);
    }

    private function integer(mixed $value, int $min, int $max, string $message): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if (!is_int($parsed)) {
            throw new InvalidArgumentException($message);
        }
        return $parsed;
    }

    /** @param array<string,mixed> $existing @param array<string,mixed> $request */
    private function assertSameRequest(array $existing, array $request): void
    {
        $matches = (string) $existing['ambiente'] === $request['environment']
            && (string) $existing['modelo'] === $request['model']
            && (int) $existing['configuracao_id'] === $request['configuration_id']
            && (int) $existing['serie'] === $request['series']
            && (int) $existing['ano'] === $request['year']
            && (int) $existing['numero_inicial'] === $request['start_number']
            && (int) $existing['numero_final'] === $request['end_number']
            && hash_equals((string) $existing['justificativa'], $request['justification']);
        if (!$matches) {
            throw new InvalidArgumentException('O token de inutilização já foi usado por outra solicitação.');
        }
    }

    /** @param array<string,mixed> $row @return array{id:int,status:string,cstat:string,reason:string} */
    private function result(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
            'cstat' => (string) ($row['cstat'] ?? ''),
            'reason' => (string) ($row['xmotivo'] ?? 'Solicitação já registrada.'),
        ];
    }
}
