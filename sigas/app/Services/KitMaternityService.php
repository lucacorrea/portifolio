<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\BenefitRequestRepository;
use App\Repositories\KitMaternityRepository;
use App\Repositories\SocioeconomicRepository;
use PDO;
use RuntimeException;
use Throwable;

final class KitMaternityService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly KitMaternityRepository $kit,
        private readonly BenefitRequestRepository $requests,
        private readonly SocioeconomicRepository $socioeconomic,
        private readonly BenefitRequestService $benefits,
        private readonly PersonJourneyService $journey,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function openRequest(array $data, int $userId, ?int $sectorId): int
    {
        $personId = (int) ($data['pessoa_id'] ?? 0);
        if ($personId <= 0) {
            throw new RuntimeException('Selecione uma pessoa já cadastrada no SIGAS.', 422);
        }

        $profile = $this->socioeconomic->findByPersonId($personId);
        $socioeconomicId = is_array($profile) ? (int) ($profile['id'] ?? 0) : null;
        if ($socioeconomicId !== null && $socioeconomicId <= 0) {
            $socioeconomicId = null;
        }

        $dum = $this->dateOrNull($data['dum'] ?? null);
        $dpp = $this->dateOrNull($data['dpp'] ?? null);
        $weeks = $this->gestationalWeeks($dum, $dpp);

        $this->pdo->beginTransaction();
        try {
            $benefitRequestId = $this->benefits->open(
                $personId,
                $socioeconomicId,
                'kit-maternidade',
                'kit-maternidade',
                'Kit Maternidade',
                $sectorId,
                $userId,
                $this->positiveIntOrNull($data['responsavel_tecnico_id'] ?? null),
                trim((string) ($data['observacao'] ?? 'Solicitação do Kit Maternidade.'))
            );

            $kitRequestId = $this->kit->insertRequest([
                'beneficio_solicitacao_id' => $benefitRequestId,
                'pessoa_id' => $personId,
                'dum' => $dum,
                'dpp' => $dpp,
                'idade_gestacional_inicial_semanas' => $weeks,
                'prenatal_iniciado' => !empty($data['prenatal_iniciado']) ? 1 : 0,
                'unidade_prenatal' => $this->nullable($data['unidade_prenatal'] ?? null, 150),
                'gestacao_risco' => !empty($data['gestacao_risco']) ? 1 : 0,
                'risco_descricao' => $this->nullable($data['risco_descricao'] ?? null, 500),
                'numero_gestacao' => $this->positiveIntOrNull($data['numero_gestacao'] ?? null),
                'numero_partos' => $this->nonNegativeIntOrNull($data['numero_partos'] ?? null),
                'responsavel_tecnico_id' => $this->positiveIntOrNull($data['responsavel_tecnico_id'] ?? null),
                'acompanhamento_iniciado_em' => null,
                'status' => $socioeconomicId === null ? 'aguardando_socioeconomico' : 'solicitado',
                'observacao' => $this->nullable($data['observacao'] ?? null, 2000),
                'usuario_id' => $userId,
            ]);

            $stmt = $this->pdo->prepare(
                'UPDATE beneficio_solicitacoes
                 SET referencia_modulo = \'kit-maternidade\',
                     referencia_tipo = \'kit_maternidade_solicitacao\',
                     referencia_id = :kit_id,
                     status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $benefitRequestId,
                'kit_id' => $kitRequestId,
                'status' => $socioeconomicId === null ? 'aguardando_socioeconomico' : 'solicitado',
            ]);

            $this->pdo->commit();
            return $kitRequestId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function addFollowUp(int $kitRequestId, array $data, int $userId): int
    {
        $request = $this->kit->find($kitRequestId);
        if (!is_array($request)) {
            throw new RuntimeException('Solicitação do Kit Maternidade não localizada.', 404);
        }
        if (in_array((string) ($request['status'] ?? ''), ['encerrado', 'cancelado'], true)) {
            throw new RuntimeException('Este acompanhamento já foi encerrado.', 409);
        }

        $type = strtolower(trim((string) ($data['tipo'] ?? '')));
        if (!in_array($type, ['visita', 'reuniao', 'contato', 'orientacao', 'retorno', 'outro'], true)) {
            throw new RuntimeException('Tipo de acompanhamento inválido.', 422);
        }

        $participation = null;
        if ($type === 'reuniao') {
            $participation = strtolower(trim((string) ($data['participacao'] ?? '')));
            if (!in_array($participation, ['presente', 'ausente', 'justificada'], true)) {
                throw new RuntimeException('Informe a participação na reunião.', 422);
            }
        }

        $eventDate = $this->dateTimeOrNow($data['data_evento'] ?? null);
        $weeks = $this->positiveIntOrNull($data['idade_gestacional_semanas'] ?? null)
            ?? $this->gestationalWeeks(
                $this->dateOrNull($request['dum'] ?? null),
                $this->dateOrNull($request['dpp'] ?? null),
                $eventDate
            );

        $risk = !empty($data['risco_identificado']);
        $riskDescription = $this->nullable($data['risco_descricao'] ?? null, 500);
        if ($risk && $riskDescription === null) {
            throw new RuntimeException('Descreva o risco identificado.', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $id = $this->kit->insertFollowUp([
                'kit_solicitacao_id' => $kitRequestId,
                'tipo' => $type,
                'data_evento' => $eventDate,
                'idade_gestacional_semanas' => $weeks,
                'participacao' => $participation,
                'risco_identificado' => $risk ? 1 : 0,
                'risco_descricao' => $riskDescription,
                'observacao' => $this->nullable($data['observacao'] ?? null, 4000),
                'proxima_acao' => $this->nullable($data['proxima_acao'] ?? null, 255),
                'proxima_acao_em' => $this->dateTimeOrNull($data['proxima_acao_em'] ?? null),
                'usuario_id' => $userId,
            ]);

            if ($risk) {
                $stmt = $this->pdo->prepare(
                    'UPDATE kit_maternidade_solicitacoes
                     SET gestacao_risco = 1,
                         risco_descricao = COALESCE(:descricao, risco_descricao),
                         atualizado_por = :usuario_id
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $kitRequestId,
                    'descricao' => $riskDescription,
                    'usuario_id' => $userId,
                ]);
            }

            $this->requests->updateStatus(
                (int) $request['beneficio_solicitacao_id'],
                'em_acompanhamento',
                $userId
            );
            $this->pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function evaluate(int $kitRequestId, array $data, int $userId): int
    {
        $request = $this->kit->find($kitRequestId);
        if (!is_array($request)) {
            throw new RuntimeException('Solicitação do Kit Maternidade não localizada.', 404);
        }

        $profile = $this->socioeconomic->findByPersonId((int) $request['pessoa_id']);
        if (!is_array($profile)) {
            throw new RuntimeException(
                'A avaliação não pode ser concluída sem o formulário socioeconômico da candidata.',
                409
            );
        }

        $result = strtolower(trim((string) ($data['resultado'] ?? '')));
        if (!in_array($result, ['apto', 'nao_apto', 'pendente'], true)) {
            throw new RuntimeException('Informe o resultado da avaliação.', 422);
        }

        $opinion = trim((string) ($data['parecer_tecnico'] ?? ''));
        if (mb_strlen($opinion) < 10) {
            throw new RuntimeException('Registre um parecer técnico fundamentado.', 422);
        }

        $pending = is_array($data['pendencias'] ?? null) ? array_values($data['pendencias']) : [];
        $pendingJson = json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

        $this->pdo->beginTransaction();
        try {
            $evaluationId = $this->kit->insertEvaluation([
                'kit_solicitacao_id' => $kitRequestId,
                'resultado' => $result,
                'parecer_tecnico' => $opinion,
                'pendencias_json' => $pendingJson,
                'usuario_id' => $userId,
            ]);

            $this->benefits->decide(
                (int) $request['beneficio_solicitacao_id'],
                $result,
                $opinion,
                $userId
            );

            $kitStatus = match ($result) {
                'apto' => 'apto',
                'nao_apto' => 'nao_apto',
                default => 'em_analise',
            };
            $this->kit->setStatus($kitRequestId, $kitStatus, $userId);

            $this->pdo->commit();
            return $evaluationId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function deliver(int $kitRequestId, array $data, int $userId): int
    {
        $request = $this->kit->find($kitRequestId);
        if (!is_array($request)) {
            throw new RuntimeException('Solicitação do Kit Maternidade não localizada.', 404);
        }
        if ((string) ($request['decisao'] ?? '') !== 'apto') {
            throw new RuntimeException('A entrega só pode ser registrada após decisão APTA.', 409);
        }
        if (!empty($request['entregue_em'])) {
            throw new RuntimeException('Esta solicitação já possui entrega registrada.', 409);
        }

        $receiver = trim((string) ($data['recebedor_nome'] ?? $request['pessoa_nome'] ?? ''));
        if ($receiver === '') {
            throw new RuntimeException('Informe quem recebeu o Kit.', 422);
        }

        $receiverCpf = Validator::onlyDigits((string) ($data['recebedor_cpf'] ?? ''));
        if ($receiverCpf !== '' && !Validator::cpf($receiverCpf)) {
            throw new RuntimeException('CPF do recebedor inválido.', 422);
        }

        $this->pdo->beginTransaction();
        try {
            $deliveryId = $this->kit->insertDelivery([
                'kit_solicitacao_id' => $kitRequestId,
                'entregue_em' => $this->dateTimeOrNow($data['entregue_em'] ?? null),
                'lote' => $this->nullable($data['lote'] ?? null, 80),
                'termo_referencia' => $this->nullable($data['termo_referencia'] ?? null, 100),
                'recebedor_nome' => mb_substr($receiver, 0, 150),
                'recebedor_cpf' => $receiverCpf === '' ? null : $receiverCpf,
                'observacao' => $this->nullable($data['observacao'] ?? null, 2000),
                'entregue_por' => $userId,
            ]);
            $this->requests->updateStatus((int) $request['beneficio_solicitacao_id'], 'entregue', $userId);
            $this->pdo->commit();
            return $deliveryId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function closePostpartum(int $kitRequestId, int $userId, string $observation): void
    {
        $request = $this->kit->find($kitRequestId);
        if (!is_array($request)) {
            throw new RuntimeException('Solicitação do Kit Maternidade não localizada.', 404);
        }
        $observation = trim($observation);
        if ($observation === '') {
            throw new RuntimeException('Informe a observação de encerramento.', 422);
        }

        $benefit = $this->requests->find((int) $request['beneficio_solicitacao_id']);
        $this->pdo->beginTransaction();
        try {
            $this->kit->setStatus($kitRequestId, 'encerrado', $userId);
            $this->requests->updateStatus((int) $request['beneficio_solicitacao_id'], 'encerrado', $userId);

            $attendanceId = is_array($benefit) ? (int) ($benefit['atendimento_id'] ?? 0) : 0;
            if ($attendanceId > 0) {
                $this->journey->complete($attendanceId, $userId, $observation);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function gestationalWeeks(?string $dum, ?string $dpp, ?string $at = null): ?int
    {
        $target = strtotime($at ?? date('Y-m-d H:i:s'));
        if ($target === false) {
            return null;
        }
        if ($dum !== null) {
            $start = strtotime($dum . ' 00:00:00');
            if ($start !== false && $target >= $start) {
                return max(0, min(45, (int) floor(($target - $start) / 604800)));
            }
        }
        if ($dpp !== null) {
            $due = strtotime($dpp . ' 00:00:00');
            if ($due !== false) {
                return max(0, min(45, 40 - (int) floor(($due - $target) / 604800)));
            }
        }
        return null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function dateTimeOrNow(mixed $value): string
    {
        return $this->dateTimeOrNull($value) ?? date('Y-m-d H:i:s');
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function nonNegativeIntOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return max(0, (int) $value);
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
