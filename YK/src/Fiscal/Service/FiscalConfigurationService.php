<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use InvalidArgumentException;
use RuntimeException;

final class FiscalConfigurationService
{
    private const STATE_CODES = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16',
        'TO' => '17', 'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25',
        'PE' => '26', 'AL' => '27', 'SE' => '28', 'BA' => '29', 'MG' => '31', 'ES' => '32',
        'RJ' => '33', 'SP' => '35', 'PR' => '41', 'SC' => '42', 'RS' => '43', 'MS' => '50',
        'MT' => '51', 'GO' => '52', 'DF' => '53',
    ];

    public function __construct(
        private readonly FiscalConfigurationRepository $repository,
        private readonly ?FiscalSecretVault $vault = null,
        private readonly ?FiscalCertificateStorage $certificateStorage = null
    ) {
    }

    /** @return array<string,mixed> */
    public function overview(?string $environment = null, string $model = '65'): array
    {
        $environment = self::environment($environment);
        $model = self::model($model);
        return [
            'environment' => $environment,
            'model' => $model,
            'configuration' => $this->repository->latestConfiguration($environment, $model),
            'certificates' => $this->repository->activeCertificates(),
            'series' => $this->repository->activeSeries($environment, $model),
            'readiness' => $this->readiness($environment, $model),
        ];
    }

    /** @param array<string,mixed> $data */
    public function saveCertificate(array $upload, string $password, int $userId): int
    {
        $this->assertUser($userId);
        if ($password === '') throw new InvalidArgumentException('Informe a senha do certificado fiscal.');
        if (strlen($password) > 200 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Senha do certificado fiscal inválida.');
        }
        $vault = $this->requireVault();
        $storage = $this->requireCertificateStorage();
        $companyCnpj = self::cnpj((string) ($this->repository->companyFiscalData()['documento'] ?? ''));
        $secret = $vault->seal($password);
        $certificateId = 0;
        $storage->replaceWithMetadata(
            $upload,
            $password,
            $companyCnpj,
            null,
            function (array $metadata) use ($secret, $userId, &$certificateId): void {
                $certificateId = $this->repository->insertCertificate($metadata, $secret, $userId);
            }
        );
        if ($certificateId <= 0) throw new RuntimeException('Certificado não foi persistido.');
        return $certificateId;
    }

    /** @param array<string,mixed> $data */
    public function createConfiguration(array $data, int $userId): int
    {
        $this->assertUser($userId);
        $environment = self::environment(isset($data['ambiente']) ? (string) $data['ambiente'] : null);
        if ($environment === 'producao') {
            throw new InvalidArgumentException('Configuração de produção permanece bloqueada nesta etapa.');
        }
        $model = self::model((string) ($data['modelo'] ?? '65'));
        $state = self::state((string) ($data['uf'] ?? 'AM'));
        $schemaVersion = self::schemaVersion((string) ($data['schema_versao'] ?? '4.00'));
        $certificateId = self::positiveInt($data['certificado_id'] ?? null, 'Certificado fiscal inválido.');
        $qrVersion = $model === '65'
            ? self::boundedInt($data['qr_code_versao'] ?? 3, 1, 9, 'Versão do QR Code inválida.')
            : null;
        $cscId = $model === '65' ? self::shortText($data['csc_id'] ?? null, 40, 'ID do CSC inválido.') : null;
        $cscPlaintext = $model === '65' ? trim((string) ($data['csc'] ?? '')) : '';
        if ($model === '65' && ($cscId === null || $cscPlaintext === '')) {
            throw new InvalidArgumentException('Informe o ID e o CSC para configurar a NFC-e.');
        }
        if (strlen($cscPlaintext) > 120 || str_contains($cscPlaintext, "\0")) {
            throw new InvalidArgumentException('CSC inválido.');
        }
        $sealedCsc = $cscPlaintext === '' ? null : $this->requireVault()->seal($cscPlaintext);

        return $this->repository->insertConfiguration([
            'environment' => $environment, 'model' => $model, 'state' => $state,
            'schema_version' => $schemaVersion, 'qr_version' => $qrVersion,
            'certificate_id' => $certificateId, 'csc_id' => $cscId,
        ], $sealedCsc, $userId);
    }

    /** @param array<string,mixed> $data */
    public function saveSeries(array $data, int $userId): void
    {
        $this->assertUser($userId);
        $environment = self::environment(isset($data['ambiente']) ? (string) $data['ambiente'] : null);
        if ($environment === 'producao') {
            throw new InvalidArgumentException('Séries de produção permanecem bloqueadas nesta etapa.');
        }
        $this->repository->saveSeries([
            'environment' => $environment,
            'model' => self::model((string) ($data['modelo'] ?? '65')),
            'series' => self::boundedInt($data['serie'] ?? null, 0, 999, 'Série fiscal inválida.'),
            'next_number' => self::boundedInt($data['proximo_numero'] ?? 1, 1, 999999999, 'Próxima numeração inválida.'),
        ], $userId);
    }

    /** @return array{ready:bool,blocked:bool,errors:array<int,string>,warnings:array<int,string>,checks:array<string,mixed>} */
    public function readiness(?string $environment = null, string $model = '65', ?int $configurationId = null): array
    {
        $environment = self::environment($environment);
        $model = self::model($model);
        $configuration = $configurationId === null
            ? $this->repository->latestConfiguration($environment, $model)
            : $this->repository->configurationById($configurationId);
        if ($configuration !== null
            && ($configuration['ambiente'] !== $environment || $configuration['modelo'] !== $model)
        ) {
            throw new InvalidArgumentException('Configuração não pertence ao ambiente e modelo informados.');
        }

        $company = $this->repository->companyFiscalData();
        $errors = [];
        $warnings = [];
        $cnpj = null;
        try { $cnpj = self::cnpj((string) ($company['documento'] ?? '')); }
        catch (InvalidArgumentException) { $errors[] = 'Cadastre um CNPJ válido para a empresa.'; }
        $state = strtoupper(trim((string) ($company['endereco_uf'] ?? '')));
        try { self::state($state); }
        catch (InvalidArgumentException) { $errors[] = 'Cadastre uma UF válida no endereço fiscal da empresa.'; }
        $cityCode = trim((string) ($company['codigo_municipio_ibge'] ?? ''));
        if (!self::isValidIbgeCityCode($cityCode, $state)) $errors[] = 'Cadastre o código IBGE do município compatível com a UF.';
        $crt = (int) ($company['crt'] ?? 0);
        if (!in_array($crt, [1, 2, 3, 4], true)) $errors[] = 'Cadastre um CRT válido para a empresa.';
        if (!self::isValidStateRegistration((string) ($company['inscricao_estadual'] ?? ''), $state)) {
            $errors[] = 'Cadastre uma inscrição estadual válida para a UF da empresa.';
        }
        foreach (['razao_social', 'endereco_logradouro', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_cep'] as $field) {
            if (trim((string) ($company[$field] ?? '')) === '') $errors[] = 'Complete os dados obrigatórios do endereço e identificação fiscal da empresa.';
        }

        if ($configuration === null) {
            $errors[] = 'Crie uma configuração fiscal para o ambiente e modelo.';
        } else {
            if (($configuration['certificado_status'] ?? '') !== 'ativo'
                || strtotime((string) ($configuration['valido_ate'] ?? '')) <= time()
            ) $errors[] = 'Selecione um certificado fiscal ativo e dentro da validade.';
            if ($cnpj !== null && !hash_equals($cnpj, (string) ($configuration['titular_cnpj'] ?? ''))) {
                $errors[] = 'O certificado fiscal não pertence ao CNPJ da empresa.';
            }
            if ($state !== '' && ($configuration['uf'] ?? '') !== $state) {
                $errors[] = 'A UF da configuração difere do endereço fiscal da empresa.';
            }
            if ($model === '65' && (empty($configuration['has_csc']) || trim((string) ($configuration['csc_id'] ?? '')) === '')) {
                $errors[] = 'A NFC-e exige ID do CSC e CSC protegido.';
            }
        }

        $series = $this->repository->activeSeries($environment, $model);
        if ($series === []) $errors[] = 'Cadastre ao menos uma série ativa para o ambiente e modelo.';
        $productChecks = $this->repository->productReadiness($crt);
        foreach (['missing_ncm', 'missing_origin', 'missing_cfop', 'missing_icms_code', 'missing_pis', 'missing_cofins', 'missing_tax_unit'] as $field) {
            if (($productChecks[$field] ?? 0) > 0) $errors[] = 'Existem produtos de venda com cadastro tributário incompleto.';
        }
        $clientChecks = $this->repository->clientReadiness();
        if (($clientChecks['identified_without_address'] ?? 0) > 0) $warnings[] = 'Há clientes identificados sem endereço completo para NF-e.';
        if (($clientChecks['contributors_without_ie'] ?? 0) > 0) $warnings[] = 'Há clientes contribuintes sem inscrição estadual.';
        if (($clientChecks['invalid_city_code'] ?? 0) > 0) $warnings[] = 'Há clientes com código IBGE inválido.';
        $blocked = $environment === 'producao';
        if ($blocked) $errors[] = 'Emissão em produção está bloqueada nesta etapa de fundação fiscal.';

        return [
            'ready' => !$blocked && $errors === [], 'blocked' => $blocked,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'checks' => ['company' => $company, 'configuration' => $configuration,
                'series' => $series, 'products' => $productChecks, 'clients' => $clientChecks],
        ];
    }

    public function activate(int $configurationId, int $userId): void
    {
        $this->assertUser($userId);
        $configuration = $this->repository->configurationById($configurationId);
        if ($configuration === null) throw new InvalidArgumentException('Configuração fiscal não encontrada.');
        if ($configuration['ambiente'] !== 'homologacao') {
            throw new InvalidArgumentException('Ativação de produção está bloqueada nesta etapa.');
        }
        $readiness = $this->readiness('homologacao', (string) $configuration['modelo'], $configurationId);
        if (!$readiness['ready']) throw new InvalidArgumentException('A configuração fiscal possui pendências e não pode ser ativada.');
        $this->repository->activateConfiguration($configurationId, 'homologacao', (string) $configuration['modelo'], $userId);
    }

    public static function environment(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return 'homologacao';
        if (!in_array($value, ['homologacao', 'producao'], true)) throw new InvalidArgumentException('Ambiente fiscal inválido.');
        return $value;
    }

    public static function cnpj(string $value): string
    {
        return FiscalCertificateStorage::normalizeCnpj($value);
    }

    public static function isValidIbgeCityCode(string $code, string $state): bool
    {
        return preg_match('/^\d{7}$/', $code) === 1
            && isset(self::STATE_CODES[$state])
            && str_starts_with($code, self::STATE_CODES[$state]);
    }

    public static function isValidStateRegistration(string $value, string $state): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($state === 'AM') {
            if (strlen($digits) !== 9) return false;
            $sum = 0;
            foreach ([9, 8, 7, 6, 5, 4, 3, 2] as $index => $weight) $sum += (int) $digits[$index] * $weight;
            $digit = 11 - ($sum % 11);
            if ($digit >= 10) $digit = 0;
            return (int) $digits[8] === $digit;
        }
        return preg_match('/^\d{2,14}$/', $digits) === 1;
    }

    private static function model(string $value): string
    {
        if (!in_array($value, ['55', '65'], true)) throw new InvalidArgumentException('Modelo fiscal inválido.');
        return $value;
    }

    private static function state(string $value): string
    {
        $value = strtoupper(trim($value));
        if (!isset(self::STATE_CODES[$value])) throw new InvalidArgumentException('UF fiscal inválida.');
        return $value;
    }

    private static function schemaVersion(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,2}\.\d{2}$/', $value) !== 1) throw new InvalidArgumentException('Versão do schema inválida.');
        return $value;
    }

    private static function positiveInt(mixed $value, string $message): int
    {
        return self::boundedInt($value, 1, PHP_INT_MAX, $message);
    }

    private static function boundedInt(mixed $value, int $min, int $max, string $message): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if (!is_int($parsed)) throw new InvalidArgumentException($message);
        return $parsed;
    }

    private static function shortText(mixed $value, int $max, string $message): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        if (str_contains($value, "\0") || strlen($value) > $max || $value !== strip_tags($value)) throw new InvalidArgumentException($message);
        return $value;
    }

    private function requireVault(): FiscalSecretVault
    {
        return $this->vault ?? throw new RuntimeException('Cofre de segredos fiscais não injetado.');
    }

    private function requireCertificateStorage(): FiscalCertificateStorage
    {
        return $this->certificateStorage ?? throw new RuntimeException('Armazenamento de certificados não injetado.');
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido para configuração fiscal.');
    }
}
