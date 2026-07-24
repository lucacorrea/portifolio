<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Workforce/DTO/EmployeeFormData.php';

use App\Workforce\DTO\EmployeeFormData;

function employeeFormAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export($expected, true)
            . '; obtido: '
            . var_export($actual, true)
        );
    }
}

function employeeFormAssertInvalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$normalized = EmployeeFormData::fromArray([
    'nome' => "  Maria   da Silva  ",
    'funcao' => 'Técnica',
]);
employeeFormAssertSame('Maria da Silva', $normalized->name(), 'O nome deve ser normalizado.');
employeeFormAssertSame('Técnica', $normalized->functionName(), 'A função deve ser exposta pela API.');
employeeFormAssertSame(true, $normalized->has('nome'), 'O nome deve sempre fazer parte dos dados.');
employeeFormAssertSame(false, $normalized->has('salario'), 'Campo opcional ausente não deve virar atualização implícita.');

employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray(['name' => '']),
    'Nome vazio deve ser rejeitado.'
);
employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray(['name' => '<b>Maria</b>']),
    'HTML no nome deve ser rejeitado.'
);

$salary = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'salario' => '1.234,5',
]);
employeeFormAssertSame('1234.50', $salary->salary(), 'Salário brasileiro deve ser normalizado para DECIMAL.');
employeeFormAssertSame('1234.50', $salary->databaseValues()['salario'], 'Salário normalizado deve seguir para o banco.');

$emptySalary = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'salario' => '',
]);
employeeFormAssertSame(true, $emptySalary->has('salario'), 'Salário vazio informado deve representar limpeza explícita.');
employeeFormAssertSame(null, $emptySalary->salary(), 'Salário vazio deve ser normalizado para null.');

foreach (['-1,00', '12,345', '10000000000,00', 'abc'] as $invalidSalary) {
    employeeFormAssertInvalid(
        static fn(): EmployeeFormData => EmployeeFormData::fromArray([
            'name' => 'Maria da Silva',
            'salario' => $invalidSalary,
        ]),
        'Salário inválido deve ser rejeitado: ' . $invalidSalary
    );
}

$dates = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'data_nascimento' => '1990-02-28',
    'data_admissao' => '2020-01-15',
    'cnh_data_vencimento' => '2099-12-31',
]);
employeeFormAssertSame('1990-02-28', $dates->value('data_nascimento'), 'Data válida deve ser preservada.');
employeeFormAssertSame('2099-12-31', $dates->value('cnh_data_vencimento'), 'Vencimento futuro da CNH deve ser permitido.');

foreach (['2025-02-29', '28/02/2025', '2025-13-01'] as $invalidDate) {
    employeeFormAssertInvalid(
        static fn(): EmployeeFormData => EmployeeFormData::fromArray([
            'name' => 'Maria da Silva',
            'data_nascimento' => $invalidDate,
        ]),
        'Data inválida deve ser rejeitada: ' . $invalidDate
    );
}

employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray([
        'name' => 'Maria da Silva',
        'data_nascimento' => '2999-01-01',
    ]),
    'Nascimento futuro deve ser rejeitado.'
);
employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray([
        'name' => 'Maria da Silva',
        'data_nascimento' => '2000-01-01',
        'data_admissao' => '1999-12-31',
    ]),
    'Admissão anterior ao nascimento deve ser rejeitada.'
);

$enums = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'estado_civil' => 'Uniao estavel',
    'sexo' => 'Feminino',
]);
employeeFormAssertSame('Uniao estavel', $enums->value('estado_civil'), 'Estado civil permitido deve ser aceito.');
employeeFormAssertSame('Feminino', $enums->value('sexo'), 'Sexo permitido deve ser aceito.');

employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray([
        'name' => 'Maria da Silva',
        'estado_civil' => 'Complicado',
    ]),
    'Estado civil fora do enum deve ser rejeitado.'
);
employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray([
        'name' => 'Maria da Silva',
        'sexo' => 'Outro',
    ]),
    'Sexo fora do enum deve ser rejeitado.'
);

$ufs = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'rg_uf' => 'sp',
    'titulo_eleitor_uf' => ' df ',
    'carteira_trabalho_uf' => '',
]);
employeeFormAssertSame('SP', $ufs->value('rg_uf'), 'UF deve ser convertida para maiúsculas.');
employeeFormAssertSame('DF', $ufs->value('titulo_eleitor_uf'), 'UF deve ignorar espaços externos.');
employeeFormAssertSame(null, $ufs->value('carteira_trabalho_uf'), 'UF vazia deve ser normalizada para null.');
employeeFormAssertInvalid(
    static fn(): EmployeeFormData => EmployeeFormData::fromArray([
        'name' => 'Maria da Silva',
        'rg_uf' => 'XX',
    ]),
    'UF inexistente deve ser rejeitada.'
);

$cpf = EmployeeFormData::fromArray([
    'name' => 'Maria da Silva',
    'cpf_numero' => '529.982.247-25',
]);
employeeFormAssertSame('52998224725', $cpf->cpfNumber(), 'CPF válido deve ser normalizado para dígitos.');

foreach (['111.111.111-11', '529.982.247-24', '123', '529A98224725'] as $invalidCpf) {
    employeeFormAssertInvalid(
        static fn(): EmployeeFormData => EmployeeFormData::fromArray([
            'name' => 'Maria da Silva',
            'cpf_numero' => $invalidCpf,
        ]),
        'CPF inválido deve ser rejeitado: ' . $invalidCpf
    );
}

echo "EmployeeFormDataTest: OK\n";
