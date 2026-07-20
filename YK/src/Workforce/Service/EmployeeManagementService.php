<?php

declare(strict_types=1);

namespace App\Workforce\Service;

use App\Workforce\DTO\EmployeeFormData;
use App\Workforce\Entity\Employee;
use App\Workforce\Repository\EmployeeRepository;
use InvalidArgumentException;

final class EmployeeManagementService
{
    public function __construct(
        private readonly EmployeeRepository $employees
    ) {
    }

    /**
     * @return Employee[]
     */
    public function listEmployees(string $search = ''): array
    {
        return $this->employees->findAll($search);
    }

    public function getEmployee(int $id): Employee
    {
        $employee = $this->employees->findById($id);

        if ($employee === null) {
            throw new InvalidArgumentException(
                'Funcionário não encontrado.'
            );
        }

        return $employee;
    }

    public function createEmployee(
        EmployeeFormData $data
    ): Employee {
        return $this->employees->create($data);
    }

    public function updateEmployee(
        int $id,
        EmployeeFormData $data,
        bool $updateSalary = true,
        bool $updateDocuments = true,
        bool $updateBankData = true
    ): void {
        $this->getEmployee($id);

        $this->employees->update(
            $id,
            $data,
            $updateSalary,
            $updateDocuments,
            $updateBankData
        );
    }

    public function updateEmployeePhoto(int $id, ?string $photoPath): void
    {
        $this->getEmployee($id);
        $this->employees->updateEmployeePhoto($id, $photoPath);
    }
}
