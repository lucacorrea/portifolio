<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\DTO\UserData;
use App\Models\User;
use App\Repositories\CargoRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

final class GovernanceUserRegistrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SectorRepository $sectors,
        private readonly CargoRepository $cargos,
        private readonly AuditService $audit,
    ) {
    }

    public function createPending(
        User $operator,
        string $name,
        string $cpf,
        ?string $registration,
        int $cargoId,
        string $email,
        ?string $phone,
        int $requestedSectorId,
        string $password,
        string $passwordConfirmation,
    ): int {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?: '';
        $cpf = Validator::onlyDigits($cpf);
        $registration = $this->nullableTrim($registration);
        $email = mb_strtolower(trim($email));
        $phone = $this->nullableTrim($phone);

        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Informe o nome completo do usuário.');
        }
        if (!Validator::cpf($cpf)) {
            throw new InvalidArgumentException('Informe um CPF válido.');
        }
        if (!Validator::email($email) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        if ($registration !== null && mb_strlen($registration) > 60) {
            throw new InvalidArgumentException('A matrícula informada é muito longa.');
        }
        if ($phone !== null) {
            $phoneDigits = Validator::onlyDigits($phone);
            if (!in_array(strlen($phoneDigits), [10, 11], true)) {
                throw new InvalidArgumentException('Informe um telefone válido com DDD.');
            }
            $phone = $phoneDigits;
        }
        if (!$this->sectors->existsActive($requestedSectorId)) {
            throw new InvalidArgumentException('Selecione um setor ativo para a solicitação.');
        }

        $cargo = $this->cargos->findActiveById($cargoId);
        if ($cargo === null) {
            throw new InvalidArgumentException('Selecione um cargo ativo cadastrado na Governança.');
        }
        $jobTitle = trim((string) ($cargo['nome'] ?? ''));
        if ($jobTitle === '' || mb_strlen($jobTitle) > 120) {
            throw new InvalidArgumentException('O cargo selecionado é inválido.');
        }

        if (!Validator::strongPassword($password)) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres, incluindo letra, número e símbolo.');
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new InvalidArgumentException('A confirmação da senha não confere.');
        }
        if ($this->users->cpfExists($cpf)) {
            throw new InvalidArgumentException('Já existe uma conta cadastrada para este CPF.');
        }
        if ($this->users->emailExists($email)) {
            throw new InvalidArgumentException('Já existe uma conta cadastrada para este e-mail.');
        }
        if ($registration !== null && $this->users->registrationExists($registration)) {
            throw new InvalidArgumentException('Já existe uma conta cadastrada para esta matrícula.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('Não foi possível proteger a senha informada.');
        }

        $data = new UserData(
            $name,
            $cpf,
            $registration,
            $jobTitle,
            $email,
            $phone,
            $requestedSectorId,
            $passwordHash,
        );

        return Database::transaction(function () use ($operator, $data, $requestedSectorId, $cargoId): int {
            $userId = $this->users->createPending($data);

            $this->audit->record(
                $operator->id,
                $userId,
                'usuario_criado_pendente',
                'governanca_acessos',
                'Conta criada pela Governança aguardando aprovação de acesso.',
                null,
                [
                    'usuario_id' => $userId,
                    'nome' => $data->nome,
                    'cpf' => $data->cpf,
                    'email' => $data->email,
                    'matricula' => $data->matricula,
                    'cargo_id' => $cargoId,
                    'cargo' => $data->cargo,
                    'telefone' => $data->telefone,
                    'setor_solicitado_id' => $requestedSectorId,
                    'status' => 'pendente',
                ]
            );

            return $userId;
        });
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
