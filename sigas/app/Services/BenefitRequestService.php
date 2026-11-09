<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BenefitRequestRepository;
use PDO;
use RuntimeException;
use Throwable;

final class BenefitRequestService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly BenefitRequestRepository $requests,
        private readonly PersonJourneyService $journey,
    ) {
    }

    /**
     * Uma pessoa pode participar de vários módulos ao mesmo tempo.
     * A única trava é duplicar a MESMA solicitação enquanto ela ainda está aberta.
     */
    public function open(
        int $personId,
        ?int $socioeconomicId,
        string $module,
        string $benefitCode,
        string $benefitName,
        ?int $sectorId,
        int $userId,
        ?int $responsibleUserId = null,
        string $observation = '',
    ): int {
        if ($personId <= 0) {
            throw new RuntimeException('Pessoa inválida para a solicitação.', 422);
        }

        $module = $this->token($module, 80);
        $benefitCode = $this->token($benefitCode, 80);
        $benefitName = trim($benefitName);
        if ($benefitName === '') {
            throw new RuntimeException('Informe o benefício solicitado.', 422);
        }

        $existing = $this->requests->findOpenByPersonAndCode($personId, $module, $benefitCode);
        if (is_array($existing)) {
            throw new RuntimeException(
                'Esta pessoa já possui uma solicitação aberta para este benefício.',
                409
            );
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $requestId = $this->requests->insert([
                'pessoa_id' => $personId,
                'atendimento_id' => null,
                'socioeconomico_id' => $socioeconomicId,
                'modulo' => $module,
                'beneficio_codigo' => $benefitCode,
                'beneficio_nome' => mb_substr($benefitName, 0, 150),
                'status' => 'solicitado',
                'prioridade' => 'normal',
                'setor_origem_id' => $sectorId,
                'responsavel_usuario_id' => $responsibleUserId,
                'solicitado_em' => date('Y-m-d H:i:s'),
                'solicitado_por' => $userId,
                'referencia_modulo' => $module,
                'referencia_tipo' => 'beneficio_solicitacao',
                'referencia_id' => null,
                'observacao' => $this->nullable($observation),
            ]);

            $attendanceId = $this->journey->start(
                $personId,
                $module,
                $sectorId,
                $userId,
                'Solicitação de ' . $benefitName,
                $module,
                'beneficio_solicitacao',
                $requestId,
                $observation !== '' ? $observation : 'Solicitação de benefício registrada.'
            );
            $this->requests->attachAttendance($requestId, $attendanceId);

            $stmt = $this->pdo->prepare(
                'UPDATE beneficio_solicitacoes
                 SET referencia_id = :id
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $requestId]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $requestId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function decide(
        int $requestId,
        string $decision,
        string $reason,
        int $userId,
    ): void {
        $request = $this->requests->find($requestId);
        if (!is_array($request)) {
            throw new RuntimeException('Solicitação não localizada.', 404);
        }

        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['apto', 'nao_apto', 'pendente'], true)) {
            throw new RuntimeException('Decisão inválida.', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('O parecer/motivo da decisão é obrigatório.', 422);
        }

        $status = match ($decision) {
            'apto' => 'apto',
            'nao_apto' => 'indeferido',
            default => 'em_analise',
        };

        $this->requests->decide($requestId, $decision, $reason, $userId, $status);

        if ($decision === 'nao_apto') {
            $attendanceId = (int) ($request['atendimento_id'] ?? 0);
            if ($attendanceId > 0) {
                $this->journey->complete(
                    $attendanceId,
                    $userId,
                    'Solicitação encerrada após decisão técnica: não apta. ' . mb_substr($reason, 0, 300)
                );
            }
        }
    }

    private function token(string $value, int $limit): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/\A[a-z0-9]+(?:[-_.][a-z0-9]+)*\z/', $value) !== 1) {
            throw new RuntimeException('Identificador de benefício inválido.', 422);
        }
        return mb_substr($value, 0, $limit);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, 2000);
    }
}
