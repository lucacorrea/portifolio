<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Repositories\PersonJourneyRepository;
use App\Services\PersonJourneyService;

require_once __DIR__ . '/bootstrap.php';

/**
 * Vincula um candidato manual à pessoa central e abre a trilha de atendimento.
 *
 * Regras de segurança:
 * - somente CPF válido e sem conflito é vinculado automaticamente;
 * - cadastro central existente nunca é sobrescrito silenciosamente;
 * - CPF ausente/inválido/duplicado permanece para revisão;
 * - falha de rastreabilidade não apaga nem duplica a triagem já salva.
 */
function pe_link_person_and_start_journey(PDO $pdo, int $candidateId): ?int
{
    if ($candidateId <= 0) {
        return null;
    }

    try {
        $candidate = pe_candidate_by_id($pdo, $candidateId);
        if (!is_array($candidate)) {
            return null;
        }

        $cpf = pe_digits($candidate['cpf'] ?? '');
        $hasCpfConflict = (int) ($candidate['cpf_duplicado'] ?? 0) === 1
            && (int) ($candidate['cpf_duplicado_confirmado'] ?? 0) !== 1;

        if (!pe_validate_cpf($cpf) || $hasCpfConflict) {
            return null;
        }

        $userId = filter_var(
            $_SESSION['auth_user_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $userId = $userId === false ? null : (int) $userId;

        $sectorId = null;
        if ($userId !== null) {
            $operator = $pdo->prepare(
                'SELECT setor_id FROM usuarios WHERE id = :id AND excluido_em IS NULL LIMIT 1'
            );
            $operator->execute(['id' => $userId]);
            $sectorValue = $operator->fetchColumn();
            if ($sectorValue !== false && $sectorValue !== null) {
                $sectorId = (int) $sectorValue;
            }
        }

        $person = $pdo->prepare('SELECT id FROM pessoas WHERE cpf = :cpf LIMIT 1');
        $person->execute(['cpf' => $cpf]);
        $personIdValue = $person->fetchColumn();
        $personId = $personIdValue === false ? null : (int) $personIdValue;

        if ($personId === null || $personId <= 0) {
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO pessoas
                        (nome, cpf, nis, rg, data_nascimento, telefone, email, criado_por, atualizado_por)
                     VALUES
                        (:nome, :cpf, :nis, :rg, :data_nascimento, :telefone, :email, :criado_por, :atualizado_por)'
                );
                $insert->execute([
                    'nome' => trim((string) ($candidate['nome'] ?? '')),
                    'cpf' => $cpf,
                    'nis' => pe_nullable($candidate['nis'] ?? null),
                    'rg' => pe_nullable($candidate['rg'] ?? null),
                    'data_nascimento' => pe_date_or_null($candidate['data_nascimento'] ?? null),
                    'telefone' => pe_nullable($candidate['telefone'] ?? null),
                    'email' => pe_nullable($candidate['email'] ?? null),
                    'criado_por' => $userId,
                    'atualizado_por' => $userId,
                ]);
                $personId = (int) $pdo->lastInsertId();
            } catch (PDOException $exception) {
                // Corrida concorrente: a unicidade do CPF pode ter sido preenchida
                // entre o SELECT e o INSERT. Releia antes de considerar falha.
                $person->execute(['cpf' => $cpf]);
                $reloaded = $person->fetchColumn();
                if ($reloaded === false) {
                    throw $exception;
                }
                $personId = (int) $reloaded;
            }
        }

        if ($personId <= 0) {
            return null;
        }

        // Migration nova pode ainda não estar instalada na hospedagem. Nesse caso,
        // o catch externo preserva o cadastro atual e registra o problema técnico.
        $link = $pdo->prepare(
            'UPDATE pe_candidatos
             SET pessoa_id = :pessoa_id
             WHERE id = :id
               AND (pessoa_id IS NULL OR pessoa_id = :pessoa_id)'
        );
        $link->execute([
            'pessoa_id' => $personId,
            'id' => $candidateId,
        ]);

        $journey = new PersonJourneyService(new PersonJourneyRepository($pdo));
        $journey->start(
            $personId,
            'primeiro-emprego',
            $sectorId,
            $userId,
            'Triagem para o programa Coari Meu Primeiro Emprego',
            'primeiro-emprego',
            'pe_candidato',
            $candidateId,
            'Triagem manual iniciada no setor do usuário responsável.'
        );

        return $personId;
    } catch (Throwable $exception) {
        Logger::application('Primeiro Emprego person journey registration failed.', [
            'candidate_id' => $candidateId,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
        return null;
    }
}
