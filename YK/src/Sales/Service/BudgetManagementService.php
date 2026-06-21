<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\ServiceRepository;
use App\CRM\Repository\ClientRepository;
use App\Sales\DTO\BudgetFormData;
use App\Sales\Entity\Budget;
use App\Sales\Entity\BudgetItem;
use App\Sales\Repository\BudgetRepository;
use InvalidArgumentException;

final class BudgetManagementService
{
    public function __construct(
        private readonly BudgetRepository $budgets,
        private readonly ClientRepository $clients,
        private readonly ProductRepository $products,
        private readonly ServiceRepository $services
    ) {
    }

    /** @return Budget[] */
    public function listBudgets(array $filters = []): array
    {
        return $this->budgets->findAll($filters);
    }

    public function budgetSummary(): array
    {
        return $this->budgets->summary();
    }

    public function getBudget(int $id): Budget
    {
        $budget = $this->budgets->findById($id);
        if ($budget === null) throw new InvalidArgumentException('Orçamento não encontrado.');
        return $budget;
    }

    /** @return BudgetItem[] */
    public function getBudgetItems(int $id): array
    {
        $this->getBudget($id);
        return $this->budgets->findItems($id);
    }

    public function createBudget(BudgetFormData $data): Budget
    {
        $this->validateReferences($data, true);
        return $this->budgets->create($data);
    }

    public function updateBudget(int $id, BudgetFormData $data): void
    {
        $budget = $this->getBudget($id);
        if (in_array($budget->status(), ['aprovado', 'recusado'], true)) {
            throw new InvalidArgumentException('Orçamento aprovado ou recusado não pode ser editado.');
        }
        $this->validateReferences($data, false);
        $this->budgets->update($id, $data);
    }

    public function approveBudget(int $id): void
    {
        $budget = $this->getBudget($id);
        if ($budget->status() === 'recusado') throw new InvalidArgumentException('Orçamento recusado não pode ser aprovado.');
        if ($this->budgets->findItems($id) === []) throw new InvalidArgumentException('Não é possível aprovar orçamento sem itens.');
        $this->budgets->approve($id);
    }

    public function rejectBudget(int $id, ?string $reason = null): void
    {
        $budget = $this->getBudget($id);
        if ($budget->status() === 'aprovado') throw new InvalidArgumentException('Orçamento aprovado não pode ser recusado.');
        $reason = $this->cleanReason($reason);
        $this->budgets->reject($id, $reason);
    }

    /** @return Budget[] */
    public function budgetsByClient(int $clientId): array
    {
        return $this->budgets->budgetsByClient($clientId);
    }

    private function validateReferences(BudgetFormData $data, bool $newBudget): void
    {
        $client = $this->clients->findById($data->clientId());
        if ($client === null) throw new InvalidArgumentException('Cliente não encontrado.');
        if ($newBudget && $client->status() !== 'ativo') {
            throw new InvalidArgumentException('Não é possível criar orçamento para cliente inativo.');
        }
        foreach ($data->items() as $item) {
            if ($item->type() === 'servico' && ($item->referenceId() === null || $this->services->findById($item->referenceId()) === null)) {
                throw new InvalidArgumentException('Serviço do orçamento não encontrado.');
            }
            if ($item->type() === 'produto' && ($item->referenceId() === null || $this->products->findById($item->referenceId()) === null)) {
                throw new InvalidArgumentException('Produto do orçamento não encontrado.');
            }
        }
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) ($reason ?? ''));
        if ($reason === '') return null;
        if (str_contains($reason, "\0") || $reason !== strip_tags($reason)) {
            throw new InvalidArgumentException('Motivo de recusa inválido.');
        }
        return $reason;
    }
}
