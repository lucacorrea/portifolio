<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\DTO\ComidaMesaCadastroData;
use App\DTO\ComidaMesaCompetenciaData;
use App\DTO\ComidaMesaEntregaData;
use App\DTO\ComidaMesaFilter;
use App\Repositories\ComidaMesaRepository;
use App\Core\Logger;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ComidaMesaService
{
    /** @var array<int,string> */
    private const MONTHS = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
    private const PROGRAM_STATUSES = ['em_analise', 'ativa', 'lista_espera', 'suspensa', 'bloqueada', 'encerrada'];
    private const PRIORITIES = ['alta', 'normal', 'baixa'];
    private const COMPETENCE_STATUSES = ['planejada', 'aberta', 'encerrada', 'cancelada'];

    public function __construct(private readonly ComidaMesaRepository $repository)
    {
    }

    /** @param array<string,mixed> $query */
    public function buildFilter(array $query): ComidaMesaFilter
    {
        return new ComidaMesaFilter(
            $this->stringValue($query['search'] ?? null),
            $this->intValue($query['competencia_id'] ?? null),
            $this->stringValue($query['program_status'] ?? null),
            $this->stringValue($query['delivery_status'] ?? null),
            $this->stringValue($query['zone'] ?? null),
            $this->stringValue($query['district'] ?? null),
            $this->stringValue($query['community'] ?? null),
            $this->intValue($query['pole_id'] ?? null),
            $this->intValue($query['page'] ?? null) ?? 1,
        );
    }

    /** @return array<string,mixed>|null */
    public function resolveCompetence(?int $competenceId): ?array
    {
        return $competenceId !== null ? $this->repository->findCompetenceById($competenceId) : $this->repository->findDefaultCompetence();
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function getDashboardData(array $query): array
    {
        $requestedFilter = $this->buildFilter($query);
        $competence = $this->resolveCompetence($requestedFilter->competenceId);
        $filter = new ComidaMesaFilter(
            $requestedFilter->search,
            $competence === null ? null : (int) $competence['id'],
            $requestedFilter->programStatus,
            $requestedFilter->deliveryStatus,
            $requestedFilter->zone,
            $requestedFilter->district,
            $requestedFilter->community,
            $requestedFilter->poleId,
            $requestedFilter->page,
        );

        return [
            'filter' => $filter,
            'competence' => $competence,
            'competences' => $this->repository->listCompetences(),
            'poles' => $this->repository->listActivePoles(),
            'statistics' => $this->repository->getStatistics($filter->competenceId),
            'registrations' => $this->repository->paginate($filter),
        ];
    }

    /** @return array<string,mixed> */
    public function consultCpf(string $cpf, ?int $competenceId): array
    {
        $digits = Validator::onlyDigits($cpf);
        $competence = $this->resolveCompetence($competenceId);
        $row = $this->repository->findByCpf($digits, $competence === null ? null : (int) $competence['id']);

        if ($row === null) {
            return ['ok' => true, 'state' => 'nao_localizado', 'person' => null, 'cpf' => $digits];
        }

        $person = [
            'id' => (int) $row['pessoa_id'],
            'name' => (string) $row['responsavel_nome'],
            'cpf_masked' => $this->maskCpf((string) $row['cpf']),
            'nis' => $row['nis'] === null ? null : (string) $row['nis'],
            'vinculo_familiar' => (string) ($row['vinculo_familiar'] ?? 'sem_familia'),
            'parentesco' => $row['parentesco'] ?? null,
        ];
        $family = $row['familia_id'] === null ? null : ['id' => (int) $row['familia_id'], 'code' => (string) $row['familia_codigo']];

        if ($row['inscricao_id'] === null) {
            return ['ok' => true, 'state' => 'pessoa_sem_inscricao', 'person' => $person, 'family' => $family, 'cpf' => $digits];
        }

        $delivery = $this->deliveryStatusForRow($row, $competence);
        $eligibility = $this->deliveryEligibility(
            [
                'status' => (string) $row['inscricao_status'],
                'polo_id' => $row['polo_id'] ?? null,
                'polo_ativo' => $row['polo_ativo'] ?? null,
            ],
            $competence,
            $row['entrega_id'] === null ? null : ['status' => $row['entrega_status']]
        );

        return [
            'ok' => true,
            'state' => 'inscrito',
            'person' => $person,
            'family' => $family,
            'registration' => [
                'id' => (int) $row['inscricao_id'],
                'status' => (string) $row['inscricao_status'],
                'status_label' => $this->programStatusLabel((string) $row['inscricao_status']),
                'priority' => (string) ($row['prioridade'] ?? 'normal'),
                'pole_id' => $row['polo_id'] === null ? null : (int) $row['polo_id'],
                'pole' => $row['polo_nome'] === null ? null : (string) $row['polo_nome'],
                'family_code' => $family['code'] ?? null,
            ],
            'competence' => $competence === null ? null : [
                'id' => (int) $competence['id'],
                'label' => $this->formatCompetence((int) $competence['mes'], (int) $competence['ano']),
                'status' => (string) $competence['status'],
                'inicio_entregas' => $competence['inicio_entregas'] ?? null,
                'fim_entregas' => $competence['fim_entregas'] ?? null,
            ],
            'delivery' => [
                'id' => $row['entrega_id'] === null ? null : (int) $row['entrega_id'],
                'status' => $delivery['status'],
                'status_label' => $delivery['label'],
                'delivered_at' => $delivery['delivered_at'],
                'receiver_name' => $row['recebedor_nome'] ?? null,
                'cancellation_reason' => $row['motivo_cancelamento'] ?? null,
            ],
            'eligibility' => $eligibility,
        ];
    }

    /** @return array<string,mixed> */
    public function saveRegistration(ComidaMesaCadastroData $data, int $userId, AuditService $audit): array
    {
        $this->validateRegistration($data);

        return $this->repository->transaction(function (ComidaMesaRepository $repo) use ($data, $userId, $audit): array {
            $before = null;
            $person = $repo->findPersonByCpf($data->cpf);

            if ($data->registrationId !== null) {
                $locked = $repo->lockRegistrationForEdit($data->registrationId);
                if ($locked === null) {
                    throw $this->problem('Inscrição não localizada.', 404);
                }
                $currentVersion = $this->normalizeVersion($locked['versao_atualizacao'] ?? null);
                if ($data->updateVersion === null || $currentVersion !== $this->normalizeVersion($data->updateVersion)) {
                    throw $this->problem('Este cadastro foi alterado por outro usuário. Recarregue os dados antes de salvar novamente.', 409);
                }
                $before = $repo->detail($data->registrationId, false, false);
                if ($before === null) {
                    throw $this->problem('Inscrição não localizada.', 404);
                }
                if ((string) $before['cpf'] !== $data->cpf) {
                    throw $this->problem('Não é permitido alterar o CPF para outra pessoa.', 422);
                }
                $person = $repo->findPersonByCpf($data->cpf);
            }

            if ($person === null) {
                $personId = $repo->insertPerson($this->personPayload($data, $userId, true));
            } else {
                $personId = (int) $person['id'];
                $repo->updatePerson($personId, $this->personPayload($data, $userId, false));
            }

            $family = $repo->findFamilyLinkForPerson($personId);
            if ($family !== null && ($family['vinculo_familiar'] ?? '') === 'integrante' && $data->registrationId === null) {
                throw $this->problem('Esta pessoa já pertence a uma família cadastrada.', 409);
            }

            $createdFamily = false;
            if ($family === null) {
                $tempCode = 'TMP-' . bin2hex(random_bytes(12));
                $familyId = $repo->insertFamily($this->familyPayload($data, $userId, $personId, $tempCode));
                $familyCode = sprintf('FAM-%06d', $familyId);
                $repo->updateFamily($familyId, $this->familyPayload($data, $userId, $personId, $familyCode));
                $createdFamily = true;
            } else {
                $familyId = (int) $family['id'];
                $familyCode = (string) $family['codigo'];
                $repo->updateFamily($familyId, $this->familyPayload($data, $userId, $personId, $familyCode));
            }

            $existingRegistration = $repo->findRegistrationByFamily($familyId);
            if ($existingRegistration !== null && ($data->registrationId === null || (int) $existingRegistration['id'] !== $data->registrationId)) {
                throw $this->problem('Esta família já possui inscrição no programa.', 409);
            }

            $registrationPayload = $this->registrationPayload($data, $userId, $familyId, $before);
            $createdRegistration = $data->registrationId === null;
            $registrationId = $createdRegistration
                ? $repo->insertRegistration($registrationPayload)
                : $data->registrationId;

            if (!$createdRegistration) {
                $repo->updateRegistration($registrationId, $registrationPayload);
            }

            $after = $repo->detail($registrationId, false, false);
            $action = $createdRegistration ? 'cadastro_criado' : 'cadastro_editado';
            if (!$createdRegistration) {
                $action = $this->statusAction((string) ($before['status'] ?? ''), $data->status) ?? $action;
            }
            $repo->addHistory($registrationId, $userId, $action, $createdRegistration ? 'Cadastro criado.' : 'Cadastro atualizado.', $this->summary($before), $this->summary($after));
            $audit->record($userId, null, $action, 'comida_mesa', null, $this->summary($before), $this->summary($after));

            return [
                'id' => $registrationId,
                'created' => $createdRegistration,
                'family_created' => $createdFamily,
                'family_code' => $familyCode,
            ];
        });
    }

    public function saveCompetence(ComidaMesaCompetenciaData $data, int $userId, AuditService $audit): int
    {
        $fields = [];
        $fields += $data->fieldErrors;
        if ($data->id !== null && $this->repository->findCompetenceById($data->id) === null) {
            throw $this->problem('Competência não localizada.', 404);
        }
        if ($data->month < 1 || $data->month > 12) {
            $fields['mes'] = 'Informe um mês entre 1 e 12.';
        }
        $maxYear = (int) date('Y') + 2;
        if ($data->year < 2020 || $data->year > $maxYear) {
            $fields['ano'] = 'Informe um ano entre 2020 e ' . $maxYear . '.';
        }
        if (!in_array($data->status, self::COMPETENCE_STATUSES, true)) {
            $fields['status'] = 'Situação inválida.';
        }
        if ($data->startsAt !== null && $data->endsAt !== null && $data->startsAt > $data->endsAt) {
            $fields['fim_entregas'] = 'Fim das entregas não pode ser anterior ao início.';
        }
        if ($fields !== []) {
            throw $this->validation($fields);
        }
        if ($this->repository->findCompetenceByMonth($data->year, $data->month, $data->id) !== null) {
            throw $this->problem('Competência mensal já cadastrada.', 409);
        }

        $id = $this->repository->saveCompetence([
            'id' => $data->id,
            'mes' => $data->month,
            'ano' => $data->year,
            'status' => $data->status,
            'inicio_entregas' => $data->startsAt,
            'fim_entregas' => $data->endsAt,
            'observacao' => $data->observation,
            'criado_por' => $userId,
        ]);
        $audit->record($userId, null, 'competencia_salva', 'comida_mesa', $this->formatCompetence($data->month, $data->year));

        return $id;
    }

    /** @return array<string,mixed> */
    public function registerDelivery(ComidaMesaEntregaData $data, int $userId, AuditService $audit): array
    {
        $this->validateDeliveryData($data);

        return $this->repository->transaction(function (ComidaMesaRepository $repo) use ($data, $userId, $audit): array {
            $registration = $repo->lockRegistrationForDelivery($data->registrationId);
            $competence = $repo->lockCompetenceForDelivery($data->competenceId);

            if ($registration === null) {
                throw $this->problem('Inscrição não localizada.', 404);
            }
            if ((string) $registration['status'] !== 'ativa') {
                throw $this->problem('Somente inscrições ativas podem receber cesta.', 409);
            }
            if ($competence === null || (string) $competence['status'] !== 'aberta') {
                throw $this->problem('A competência deve estar aberta para registrar entrega.', 409);
            }
            if ((int) ($registration['polo_ativo'] ?? 0) !== 1 || empty($registration['polo_id'])) {
                throw $this->problem('A inscrição precisa possuir polo ativo.', 409);
            }

            $delivery = $repo->lockDelivery($data->registrationId, $data->competenceId);
            if ($delivery !== null && (string) $delivery['status'] === 'entregue') {
                throw $this->problem('Esta família já recebeu a cesta nesta competência.', 409);
            }
            if ($delivery !== null && (string) $delivery['status'] !== 'cancelada') {
                throw $this->problem('Situação de entrega indisponível para reativação.', 409);
            }

            $payload = [
                'inscricao_id' => $data->registrationId,
                'competencia_id' => $data->competenceId,
                'polo_id' => (int) $registration['polo_id'],
                'recebedor_nome' => $data->receiverName,
                'recebedor_cpf' => $data->receiverCpf,
                'recebedor_parentesco' => $data->receiverKinship,
                'entregue_por' => $userId,
                'entregue_em' => $this->nowManaus(),
                'observacao' => $data->observation,
            ];
            $action = 'entrega_registrada';
            if ($delivery === null) {
                $deliveryId = $repo->insertDelivery($payload);
            } else {
                $deliveryId = (int) $delivery['id'];
                $repo->reactivateDelivery($deliveryId, $payload);
                $action = 'entrega_reativada';
            }
            $repo->addHistory($data->registrationId, $userId, $action, 'Entrega registrada na competência.', null, ['entrega_id' => $deliveryId, 'competencia_id' => $data->competenceId]);
            $audit->record($userId, null, $action, 'comida_mesa', null, null, ['inscricao_id' => $data->registrationId, 'competencia_id' => $data->competenceId]);

            return ['id' => $deliveryId, 'reactivated' => $action === 'entrega_reativada'];
        });
    }

    public function cancelDelivery(int $registrationId, int $competenceId, string $reason, int $userId, AuditService $audit): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw $this->validation(['motivo' => 'Informe um motivo com pelo menos 10 caracteres.']);
        }
        if (mb_strlen($reason) > 255) {
            throw $this->validation(['motivo' => 'Informe um motivo com no máximo 255 caracteres.']);
        }

        return $this->repository->transaction(function (ComidaMesaRepository $repo) use ($registrationId, $competenceId, $reason, $userId, $audit): int {
            $repo->lockRegistrationForDelivery($registrationId);
            $delivery = $repo->lockDelivery($registrationId, $competenceId);
            if ($delivery === null || (string) $delivery['status'] !== 'entregue') {
                throw $this->problem('Entrega entregue não localizada para cancelamento.', 404);
            }

            $repo->cancelDelivery((int) $delivery['id'], $userId, $this->nowManaus(), $reason);
            $repo->addHistory($registrationId, $userId, 'entrega_cancelada', $reason, ['status' => 'entregue'], ['status' => 'cancelada']);
            $audit->record($userId, null, 'entrega_cancelada', 'comida_mesa', $reason, ['entrega_id' => (int) $delivery['id']], ['status' => 'cancelada']);

            return (int) $delivery['id'];
        });
    }

    /** @return array<string,mixed> */
    public function detail(int $registrationId, bool $canEditCpf, bool $canViewDocuments, bool $canViewHistory): array
    {
        $row = $this->repository->detail($registrationId, $canViewDocuments, $canViewHistory);
        if ($row === null) {
            throw $this->problem('Inscrição não localizada.', 404);
        }

        if ($canEditCpf) {
            $row['cpf_completo'] = $this->formatCpf((string) $row['cpf']);
        }
        $row['cpf_mascarado'] = $this->maskCpf((string) $row['cpf']);
        unset($row['cpf']);
        foreach ($row['integrantes'] as &$member) {
            $member['cpf_mascarado'] = $this->maskCpf((string) $member['cpf']);
            unset($member['cpf']);
        }
        unset($member);
        $row['status_label'] = $this->programStatusLabel((string) $row['status']);
        $row['prioridade_label'] = $this->priorityLabel((string) $row['prioridade']);
        $row['data_inscricao_formatada'] = $this->formatDate($row['data_inscricao'] ?? null);
        $row['data_aprovacao_formatada'] = $this->formatDate($row['data_aprovacao'] ?? null);
        $row['atualizado_em_formatado'] = $this->formatDate($row['atualizado_em'] ?? null);
        $row['renda_familiar_formatada'] = $this->formatMoney($row['renda_familiar'] ?? null);
        $row['historico'] = array_map(fn (array $event): array => $this->formatHistoryEvent($event), $row['historico']);
        $row['entregas'] = array_map(function (array $delivery): array {
            $delivery['competencia_label'] = $this->formatCompetence((int) $delivery['mes'], (int) $delivery['ano']);
            $delivery['status_label'] = $this->deliveryStatusLabel((string) $delivery['status']);
            $delivery['entregue_em_formatado'] = $this->formatDate($delivery['entregue_em'] ?? null);
            $delivery['cancelada_em_formatada'] = $this->formatDate($delivery['cancelada_em'] ?? null);

            return $delivery;
        }, $row['entregas']);
        $row['documentos'] = array_map(function (array $document): array {
            $document['tamanho_formatado'] = $this->formatBytes((int) ($document['tamanho'] ?? 0));
            $document['criado_em_formatado'] = $this->formatDate($document['criado_em'] ?? null);

            return $document;
        }, $row['documentos']);

        return $row;
    }

    /** @param array<string,mixed> $registration @param array<string,mixed>|null $competence @param array<string,mixed>|null $delivery @return array<string,mixed> */
    public function deliveryEligibility(array $registration, ?array $competence, ?array $delivery): array
    {
        $registrationStatus = (string) ($registration['status'] ?? $registration['inscricao_status'] ?? '');
        if ($competence === null) {
            return ['allowed' => false, 'action' => 'none', 'reason' => 'Nenhuma competência selecionada.'];
        }
        if (($delivery['status'] ?? null) === 'entregue') {
            return ['allowed' => true, 'action' => 'cancel', 'reason' => null];
        }
        if ($registrationStatus !== 'ativa') {
            return ['allowed' => false, 'action' => 'none', 'reason' => 'Inscrição não está ativa.'];
        }
        if (($competence['status'] ?? null) !== 'aberta') {
            return ['allowed' => false, 'action' => 'none', 'reason' => 'Competência não está aberta.'];
        }
        if (empty($registration['polo_id'])) {
            return ['allowed' => false, 'action' => 'none', 'reason' => 'Inscrição sem polo definido.'];
        }
        if ((int) ($registration['polo_ativo'] ?? 1) !== 1) {
            return ['allowed' => false, 'action' => 'none', 'reason' => 'Polo da inscrição está inativo.'];
        }
        if ($delivery === null || empty($delivery['status'])) {
            return ['allowed' => true, 'action' => 'register', 'reason' => null];
        }
        if (($delivery['status'] ?? null) === 'cancelada') {
            return ['allowed' => true, 'action' => 'reactivate', 'reason' => null];
        }
        return ['allowed' => false, 'action' => 'none', 'reason' => 'Situação de entrega indisponível.'];
    }

    public function maskCpf(string $cpf): string
    {
        return strlen(Validator::onlyDigits($cpf)) === 11 ? '***.***.***-**' : '***.***.***-**';
    }

    public function formatCpf(string $cpf): string
    {
        $cpf = Validator::onlyDigits($cpf);
        return strlen($cpf) === 11 ? substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2) : $cpf;
    }

    public function formatCompetence(int $month, int $year): string
    {
        return (self::MONTHS[$month] ?? 'Mês inválido') . ' de ' . $year;
    }

    public function programStatusLabel(string $status): string
    {
        return match ($status) {
            'ativa' => 'Beneficiária ativa',
            'em_analise' => 'Em análise',
            'lista_espera' => 'Lista de espera',
            'suspensa' => 'Suspensa',
            'bloqueada' => 'Bloqueada',
            'encerrada' => 'Encerrada',
            default => 'Não informado',
        };
    }

    /** @param array<string,mixed> $row @param array<string,mixed>|null $competence @return array<string,mixed> */
    public function deliveryStatusForRow(array $row, ?array $competence): array
    {
        if ($competence === null) {
            return ['status' => 'sem_competencia', 'label' => 'Sem competência', 'class' => 'status-neutral', 'icon' => 'dash-circle', 'delivered_at' => null];
        }
        if (!empty($row['entrega_id']) && ($row['entrega_status'] ?? null) === 'entregue') {
            return ['status' => 'recebida', 'label' => 'Recebida', 'class' => 'status-success', 'icon' => 'bag-check', 'delivered_at' => $row['entrega_data'] ?? null];
        }
        if (!empty($row['entrega_id']) && ($row['entrega_status'] ?? null) === 'cancelada') {
            return ['status' => 'cancelada', 'label' => 'Entrega cancelada', 'class' => 'status-warning', 'icon' => 'arrow-counterclockwise', 'delivered_at' => $row['entrega_data'] ?? null];
        }
        $status = (string) ($row['inscricao_status'] ?? '');
        if ($status === 'ativa') {
            return ['status' => 'aguardando', 'label' => 'Aguardando retirada', 'class' => 'status-warning', 'icon' => 'clock', 'delivered_at' => null];
        }
        if (in_array($status, ['suspensa', 'bloqueada'], true)) {
            return ['status' => 'bloqueada', 'label' => 'Bloqueada', 'class' => 'status-danger', 'icon' => 'slash-circle', 'delivered_at' => null];
        }

        return ['status' => 'indisponivel', 'label' => 'Não disponível', 'class' => 'status-neutral', 'icon' => 'dash-circle', 'delivered_at' => null];
    }

    private function validateRegistration(ComidaMesaCadastroData $data): void
    {
        $fields = [];
        $fields += $data->fieldErrors;
        if (mb_strlen($data->name) < 3) {
            $fields['nome'] = 'Informe o nome completo.';
        } elseif (mb_strlen($data->name) > 150) {
            $fields['nome'] = 'Nome deve ter no máximo 150 caracteres.';
        }
        if (!Validator::cpf($data->cpf)) {
            $fields['cpf'] = 'CPF inválido.';
        }
        if ($data->phone === null || strlen($data->phone) < 10 || strlen($data->phone) > 30) {
            $fields['telefone'] = 'Informe um telefone válido.';
        }
        if ($data->nis !== null && strlen($data->nis) > 14) {
            $fields['nis'] = 'NIS deve ter no máximo 14 dígitos.';
        }
        if ($data->rg !== null && mb_strlen($data->rg) > 30) {
            $fields['rg'] = 'RG deve ter no máximo 30 caracteres.';
        }
        if ($data->email !== null && mb_strlen($data->email) > 180) {
            $fields['email'] = 'E-mail deve ter no máximo 180 caracteres.';
        }
        if ($data->email !== null && !Validator::email($data->email)) {
            $fields['email'] = 'E-mail inválido.';
        }
        if (!in_array($data->zone, ['urbana', 'rural'], true)) {
            $fields['zona'] = 'Informe zona urbana ou rural.';
        }
        if ($data->zone === 'urbana' && $data->district === null) {
            $fields['bairro'] = 'Bairro é obrigatório para zona urbana.';
        }
        if ($data->zone === 'rural' && $data->community === null) {
            $fields['comunidade'] = 'Comunidade é obrigatória para zona rural.';
        }
        if ($data->membersCount < 1) {
            $fields['quantidade_membros'] = 'A família deve ter pelo menos um membro.';
        } elseif ($data->membersCount > 65535) {
            $fields['quantidade_membros'] = 'Quantidade de membros inválida.';
        }
        if ($data->zipCode !== null && strlen($data->zipCode) > 8) {
            $fields['cep'] = 'CEP deve ter no máximo 8 dígitos.';
        }
        if ($data->familyIncome !== null && $data->familyIncome < 0) {
            $fields['renda_familiar'] = 'Renda familiar não pode ser negativa.';
        }
        if (!in_array($data->status, self::PROGRAM_STATUSES, true)) {
            $fields['status'] = 'Situação inválida.';
        }
        if (!in_array($data->priority, self::PRIORITIES, true)) {
            $fields['prioridade'] = 'Prioridade inválida.';
        }
        if ($data->status === 'ativa' && $data->poleId === null) {
            $fields['polo_id'] = 'Inscrição ativa deve possuir polo.';
        }
        if (in_array($data->status, ['suspensa', 'bloqueada'], true) && $data->suspensionReason === null) {
            $fields['motivo_suspensao'] = 'Informe o motivo.';
        }
        if ($data->poleId !== null && $this->repository->findActivePoleById($data->poleId) === null) {
            $fields['polo_id'] = 'Polo ativo não localizado.';
        }
        if ($fields !== []) {
            throw $this->validation($fields);
        }
    }

    /** @return array<string,mixed> */
    private function personPayload(ComidaMesaCadastroData $data, int $userId, bool $create): array
    {
        $payload = ['nome' => $data->name, 'cpf' => $data->cpf, 'nis' => $data->nis, 'rg' => $data->rg, 'data_nascimento' => $data->birthDate, 'telefone' => $data->phone, 'email' => $data->email, 'atualizado_por' => $userId];
        if ($create) {
            $payload['criado_por'] = $userId;
        }
        return $payload;
    }

    /** @return array<string,mixed> */
    private function familyPayload(ComidaMesaCadastroData $data, int $userId, int $personId, string $code): array
    {
        return ['codigo' => $code, 'responsavel_pessoa_id' => $personId, 'zona' => $data->zone, 'logradouro' => $data->street, 'numero' => $data->number, 'complemento' => $data->complement, 'bairro' => $data->district, 'comunidade' => $data->community, 'ponto_referencia' => $data->referencePoint, 'cep' => $data->zipCode, 'quantidade_membros' => $data->membersCount, 'renda_familiar' => $data->familyIncome, 'criado_por' => $userId, 'atualizado_por' => $userId];
    }

    /** @return array<string,mixed> */
    private function registrationPayload(ComidaMesaCadastroData $data, int $userId, int $familyId, ?array $before): array
    {
        $approved = $data->status === 'ativa';
        $alreadyApproved = !empty($before['data_aprovacao']);
        return ['familia_id' => $familyId, 'polo_id' => $data->poleId, 'status' => $data->status, 'prioridade' => $data->priority, 'data_inscricao' => $data->registrationDate ?? date('Y-m-d'), 'data_aprovacao' => $approved ? ($alreadyApproved ? $before['data_aprovacao'] : $this->nowManaus()) : null, 'aprovado_por' => $approved ? ($before['aprovado_por'] ?? $userId) : null, 'motivo_suspensao' => $data->suspensionReason, 'observacao' => $data->observation, 'criado_por' => $userId, 'atualizado_por' => $userId];
    }

    private function validateDeliveryData(ComidaMesaEntregaData $data): void
    {
        $fields = [];
        if ($data->registrationId < 1) {
            $fields['inscricao_id'] = 'Inscrição inválida.';
        }
        if ($data->competenceId < 1) {
            $fields['competencia_id'] = 'Competência inválida.';
        }
        if (mb_strlen($data->receiverName) < 3) {
            $fields['recebedor_nome'] = 'Informe o nome do recebedor.';
        } elseif (mb_strlen($data->receiverName) > 150) {
            $fields['recebedor_nome'] = 'Nome do recebedor deve ter no máximo 150 caracteres.';
        }
        if ($data->receiverCpf !== null && !Validator::cpf($data->receiverCpf)) {
            $fields['recebedor_cpf'] = 'CPF do recebedor inválido.';
        }
        if ($data->receiverKinship !== null && mb_strlen($data->receiverKinship) > 60) {
            $fields['recebedor_parentesco'] = 'Parentesco deve ter no máximo 60 caracteres.';
        }
        if ($data->observation !== null && mb_strlen($data->observation) > 255) {
            $fields['observacao'] = 'Observação deve ter no máximo 255 caracteres.';
        }
        if ($fields !== []) {
            throw $this->validation($fields);
        }
    }

    private function statusAction(string $before, string $after): ?string
    {
        if ($before === $after) {
            return null;
        }
        return match ($after) {
            'ativa' => $before === 'suspensa' || $before === 'bloqueada' ? 'inscricao_reativada' : 'inscricao_aprovada',
            'suspensa' => 'inscricao_suspensa',
            'bloqueada' => 'inscricao_bloqueada',
            'encerrada' => 'inscricao_encerrada',
            default => 'cadastro_editado',
        };
    }

    /** @param array<string,mixed>|null $row @return array<string,mixed>|null */
    private function summary(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        return ['inscricao_id' => $row['id'] ?? null, 'familia_id' => $row['familia_id'] ?? null, 'status' => $row['status'] ?? null, 'prioridade' => $row['prioridade'] ?? null, 'polo_id' => $row['polo_id'] ?? null, 'observacao' => $row['observacao'] ?? null, 'motivo_suspensao' => $row['motivo_suspensao'] ?? null];
    }

    private function nowManaus(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Manaus')))->format('Y-m-d H:i:s');
    }

    private function normalizeVersion(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'alta' => 'Alta',
            'normal' => 'Normal',
            'baixa' => 'Baixa',
            default => 'Não informada',
        };
    }

    private function deliveryStatusLabel(string $status): string
    {
        return match ($status) {
            'entregue' => 'Entregue',
            'cancelada' => 'Cancelada',
            default => 'Não informado',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatMoney(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format(max(1, $bytes) / 1024, 1, ',', '.') . ' KB';
    }

    /** @param array<string,mixed> $event @return array<string,mixed> */
    private function formatHistoryEvent(array $event): array
    {
        $before = $this->decodeHistoryJson($event['dados_anteriores'] ?? null, (int) ($event['id'] ?? 0));
        $after = $this->decodeHistoryJson($event['dados_novos'] ?? null, (int) ($event['id'] ?? 0));
        $event['before'] = $before;
        $event['after'] = $after;
        $event['changes'] = $this->historyChanges($before, $after);
        unset($event['dados_anteriores'], $event['dados_novos']);

        return $event;
    }

    /** @return array<string,mixed> */
    private function decodeHistoryJson(mixed $json, int $historyId): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $exception) {
            Logger::application('Comida Mesa history JSON decode failed.', ['history_id' => $historyId, 'type' => $exception::class]);
            return [];
        }
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after @return list<array<string,string>> */
    private function historyChanges(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old === $new) {
                continue;
            }
            $changes[] = [
                'field' => $this->historyFieldLabel((string) $key),
                'before' => $this->historyValueLabel((string) $key, $old),
                'after' => $this->historyValueLabel((string) $key, $new),
            ];
        }

        return $changes;
    }

    private function historyFieldLabel(string $field): string
    {
        return match ($field) {
            'status' => 'Situação',
            'prioridade' => 'Prioridade',
            'polo_id' => 'Polo',
            'observacao' => 'Observação',
            'motivo_suspensao' => 'Motivo',
            'entrega_id' => 'Entrega',
            'competencia_id' => 'Competência',
            default => $field,
        };
    }

    private function historyValueLabel(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }
        if ($field === 'status') {
            return $this->programStatusLabel((string) $value);
        }
        if ($field === 'prioridade') {
            return $this->priorityLabel((string) $value);
        }

        return (string) $value;
    }

    private function validation(array $fields): RuntimeException
    {
        return new RuntimeException(json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 422);
    }

    private function problem(string $message, int $status): RuntimeException
    {
        return new RuntimeException($message, $status);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : null);
    }
}
