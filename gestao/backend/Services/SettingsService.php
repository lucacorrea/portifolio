<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use InvalidArgumentException;
use RuntimeException;

final class SettingsService
{
    private const RECEIPT_MODES = [
        'perguntar',
        'sempre',
        'nunca',
    ];

    private const RECEIPT_TEMPLATES = [
        'simples',
        'detalhado',
    ];

    public function __construct(private SettingsRepository $settings)
    {
    }

    public function getEmpresa(int $empresaId): array
    {
        $empresa = $this->settings->getEmpresa($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        return $empresa;
    }

    public function getConfiguracoes(int $empresaId): array
    {
        return $this->settings->getConfiguracoes($empresaId);
    }

    public function saveEmpresa(int $empresaId, array $data): bool
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $nomeFantasia = trim((string)($data['nome_fantasia'] ?? ''));
        $cpfCnpj = trim((string)($data['cpf_cnpj'] ?? ''));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $endereco = trim((string)($data['endereco'] ?? ''));

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome da empresa.');
        }

        if (mb_strlen($nome) < 2) {
            throw new InvalidArgumentException('O nome da empresa deve ter pelo menos 2 caracteres.');
        }

        if (mb_strlen($nome) > 180) {
            throw new InvalidArgumentException('O nome da empresa deve ter no máximo 180 caracteres.');
        }

        if ($nomeFantasia !== '' && mb_strlen($nomeFantasia) > 180) {
            throw new InvalidArgumentException('O nome fantasia deve ter no máximo 180 caracteres.');
        }

        if ($cpfCnpj !== '' && mb_strlen($cpfCnpj) > 20) {
            throw new InvalidArgumentException('O CPF/CNPJ deve ter no máximo 20 caracteres.');
        }

        if ($telefone !== '' && mb_strlen($telefone) > 30) {
            throw new InvalidArgumentException('O telefone deve ter no máximo 30 caracteres.');
        }

        if ($endereco !== '' && mb_strlen($endereco) > 255) {
            throw new InvalidArgumentException('O endereço deve ter no máximo 255 caracteres.');
        }

        return $this->settings->updateEmpresa($empresaId, [
            'nome' => $nome,
            'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null,
            'cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'endereco' => $endereco !== '' ? $endereco : null,
        ]);
    }

    public function saveReceiptRules(int $empresaId, array $data): bool
    {
        $receiptMode = (string)($data['receipt_mode'] ?? 'perguntar');
        $receiptTemplate = (string)($data['receipt_template'] ?? 'detalhado');

        if (!in_array($receiptMode, self::RECEIPT_MODES, true)) {
            throw new InvalidArgumentException('Modo de comprovante inválido.');
        }

        if (!in_array($receiptTemplate, self::RECEIPT_TEMPLATES, true)) {
            throw new InvalidArgumentException('Modelo de comprovante inválido.');
        }

        return $this->settings->saveReceiptRules($empresaId, [
            'receipt_mode' => $receiptMode,
            'receipt_template' => $receiptTemplate,
        ]);
    }

    public function saveDueRules(int $empresaId, array $data): bool
    {
        $expirationAlertDays = $this->intValue($data['expiration_alert_days'] ?? 7, 'Dias de alerta de vencimento');
        $debtDueDays = $this->intValue($data['debt_due_days'] ?? 30, 'Dias para vencimento de dívida');

        if ($expirationAlertDays < 0 || $expirationAlertDays > 365) {
            throw new InvalidArgumentException('Os dias de alerta de vencimento devem ficar entre 0 e 365.');
        }

        if ($debtDueDays < 0 || $debtDueDays > 365) {
            throw new InvalidArgumentException('Os dias para vencimento de dívida devem ficar entre 0 e 365.');
        }

        return $this->settings->saveDueRules($empresaId, [
            'expiration_alert_days' => $expirationAlertDays,
            'debt_due_days' => $debtDueDays,
        ]);
    }

    public function saveStockRules(int $empresaId, array $data): bool
    {
        $defaultMinStock = $this->intValue($data['default_min_stock'] ?? 0, 'Estoque mínimo padrão');

        if ($defaultMinStock < 0 || $defaultMinStock > 999999) {
            throw new InvalidArgumentException('O estoque mínimo padrão deve ficar entre 0 e 999999.');
        }

        return $this->settings->saveStockRules($empresaId, [
            'default_min_stock' => $defaultMinStock,
            'block_expired_products' => $this->boolValue($data, 'block_expired_products'),
            'block_negative_stock' => $this->boolValue($data, 'block_negative_stock'),
            'low_stock_alert' => $this->boolValue($data, 'low_stock_alert'),
        ]);
    }

    public function savePaymentRules(int $empresaId, array $data): bool
    {
        $payload = [
            'payment_pix' => $this->boolValue($data, 'payment_pix'),
            'payment_cash' => $this->boolValue($data, 'payment_cash'),
            'payment_credit' => $this->boolValue($data, 'payment_credit'),
            'payment_debit' => $this->boolValue($data, 'payment_debit'),
            'payment_account' => $this->boolValue($data, 'payment_account'),
            'payment_mixed' => $this->boolValue($data, 'payment_mixed'),
        ];

        $enabled = array_sum($payload);

        if ($enabled < 1) {
            throw new InvalidArgumentException('Habilite pelo menos uma forma de pagamento.');
        }

        return $this->settings->savePaymentRules($empresaId, $payload);
    }

    public function saveCashRules(int $empresaId, array $data): bool
    {
        $discountLimitPercent = $this->decimalValue($data['discount_limit_percent'] ?? 0, 'Limite de desconto');

        if ($discountLimitPercent < 0 || $discountLimitPercent > 100) {
            throw new InvalidArgumentException('O limite de desconto deve ficar entre 0 e 100%.');
        }

        return $this->settings->saveCashRules($empresaId, [
            'allow_discount' => $this->boolValue($data, 'allow_discount'),
            'discount_limit_percent' => $discountLimitPercent,
            'require_customer_for_account' => $this->boolValue($data, 'require_customer_for_account'),
            'require_cancellation_reason' => $this->boolValue($data, 'require_cancellation_reason'),
        ]);
    }

    public function saveSecurityRules(int $empresaId, array $data): bool
    {
        return $this->settings->saveSecurityRules($empresaId, [
            'audit_log_enabled' => $this->boolValue($data, 'audit_log_enabled'),
            'confirm_deletes' => $this->boolValue($data, 'confirm_deletes'),
            'operator_pin_enabled' => $this->boolValue($data, 'operator_pin_enabled'),
            'notifications_enabled' => $this->boolValue($data, 'notifications_enabled'),
        ]);
    }

    private function boolValue(array $data, string $key): int
    {
        return isset($data[$key]) ? 1 : 0;
    }

    private function intValue(mixed $value, string $label): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException($label . ' deve ser um número inteiro.');
        }

        return (int)$value;
    }

    private function decimalValue(mixed $value, string $label): float
    {
        if ($value === '' || $value === null) {
            return 0.0;
        }

        $value = str_replace(',', '.', (string)$value);

        if (!is_numeric($value)) {
            throw new InvalidArgumentException($label . ' deve ser um número válido.');
        }

        return round((float)$value, 2);
    }
}