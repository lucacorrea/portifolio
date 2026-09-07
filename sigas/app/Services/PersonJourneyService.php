<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PersonJourneyRepository;
use InvalidArgumentException;

final class PersonJourneyService
{
    public function __construct(private readonly PersonJourneyRepository $repository)
    {
    }

    public function start(
        int $personId,
        string $module,
        ?int $sectorId,
        ?int $userId,
        string $purpose,
        ?string $benefitModule = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $observation = null,
    ): int {
        if ($personId <= 0) {
            throw new InvalidArgumentException('Pessoa inválida para rastreabilidade.');
        }

        $module = $this->normalizeModule($module);
        $purpose = trim($purpose);
        if ($purpose === '') {
            throw new InvalidArgumentException('Informe a finalidade do atendimento.');
        }
        $purpose = mb_substr($purpose, 0, 180);
        $benefitModule = $benefitModule === null ? null : $this->normalizeModule($benefitModule);
        $referenceType = $this->nullableToken($referenceType, 80);

        if ($referenceType !== null && $referenceId !== null && $referenceId > 0) {
            $existing = $this->repository->findByReference($module, $referenceType, $referenceId);
            if (is_array($existing)) {
                return (int) ($existing['id'] ?? 0);
            }
        }

        $attendanceId = $this->repository->insertAttendance([
            'pessoa_id' => $personId,
            'protocolo' => $this->protocol(),
            'finalidade' => $purpose,
            'beneficio_modulo' => $benefitModule,
            'setor_origem_id' => $sectorId,
            'modulo_origem' => $module,
            'setor_atual_id' => $sectorId,
            'modulo_atual' => $module,
            'usuario_abertura_id' => $userId,
            'status' => 'aberto',
            'referencia_modulo' => $referenceType === null ? null : $module,
            'referencia_tipo' => $referenceType,
            'referencia_id' => $referenceId,
            'observacao' => $this->nullableText($observation, 500),
        ]);

        $this->repository->insertMovement([
            'atendimento_id' => $attendanceId,
            'pessoa_id' => $personId,
            'tipo' => 'entrada',
            'setor_origem_id' => null,
            'setor_destino_id' => $sectorId,
            'modulo_origem' => null,
            'modulo_destino' => $module,
            'usuario_id' => $userId,
            'observacao' => $this->nullableText($observation ?: 'Atendimento iniciado.', 500),
        ]);

        return $attendanceId;
    }

    public function move(
        int $attendanceId,
        ?int $destinationSectorId,
        string $destinationModule,
        ?int $userId,
        string $observation,
        string $type = 'encaminhamento',
    ): void {
        $attendance = $this->repository->findAttendance($attendanceId);
        if (!is_array($attendance)) {
            throw new InvalidArgumentException('Atendimento não localizado.');
        }
        if (in_array((string) ($attendance['status'] ?? ''), ['concluido', 'cancelado'], true)) {
            throw new InvalidArgumentException('Atendimento encerrado não pode ser movimentado.');
        }

        $destinationModule = $this->normalizeModule($destinationModule);
        $type = $this->nullableToken($type, 40) ?? 'encaminhamento';
        $observation = trim($observation);
        if (mb_strlen($observation) < 3) {
            throw new InvalidArgumentException('Informe o motivo do encaminhamento.');
        }

        $this->repository->insertMovement([
            'atendimento_id' => $attendanceId,
            'pessoa_id' => (int) $attendance['pessoa_id'],
            'tipo' => $type,
            'setor_origem_id' => $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'],
            'setor_destino_id' => $destinationSectorId,
            'modulo_origem' => (string) ($attendance['modulo_atual'] ?? ''),
            'modulo_destino' => $destinationModule,
            'usuario_id' => $userId,
            'observacao' => $this->nullableText($observation, 500),
        ]);

        $this->repository->updateCurrentLocation(
            $attendanceId,
            $destinationSectorId,
            $destinationModule,
            'encaminhado',
        );
    }

    public function complete(int $attendanceId, ?int $userId, string $observation): void
    {
        $attendance = $this->repository->findAttendance($attendanceId);
        if (!is_array($attendance)) {
            throw new InvalidArgumentException('Atendimento não localizado.');
        }
        if ((string) ($attendance['status'] ?? '') === 'concluido') {
            return;
        }

        $this->repository->insertMovement([
            'atendimento_id' => $attendanceId,
            'pessoa_id' => (int) $attendance['pessoa_id'],
            'tipo' => 'conclusao',
            'setor_origem_id' => $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'],
            'setor_destino_id' => $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'],
            'modulo_origem' => (string) ($attendance['modulo_atual'] ?? ''),
            'modulo_destino' => (string) ($attendance['modulo_atual'] ?? ''),
            'usuario_id' => $userId,
            'observacao' => $this->nullableText($observation, 500),
        ]);

        $this->repository->updateCurrentLocation(
            $attendanceId,
            $attendance['setor_atual_id'] === null ? null : (int) $attendance['setor_atual_id'],
            (string) ($attendance['modulo_atual'] ?? ''),
            'concluido',
            true,
        );
    }

    /** @return list<array<string,mixed>> */
    public function historyByPerson(int $personId): array
    {
        return $personId > 0 ? $this->repository->historyByPerson($personId) : [];
    }

    private function protocol(): string
    {
        return 'SIGAS-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function normalizeModule(string $module): string
    {
        $module = strtolower(trim($module));
        if ($module === '' || preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/', $module) !== 1) {
            throw new InvalidArgumentException('Módulo inválido para rastreabilidade.');
        }
        return mb_substr($module, 0, 80);
    }

    private function nullableToken(?string $value, int $limit): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('/\A[a-zA-Z0-9_.-]+\z/', $value) !== 1) {
            throw new InvalidArgumentException('Identificador de referência inválido.');
        }
        return mb_substr($value, 0, $limit);
    }

    private function nullableText(?string $value, int $limit): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
