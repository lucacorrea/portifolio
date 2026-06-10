<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SettingsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getEmpresa(int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, nome_fantasia, cpf_cnpj, telefone, endereco, logo, ativo, criado_em, atualizado_em
             FROM empresas
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $empresaId,
        ]);

        $empresa = $stmt->fetch();

        return $empresa ?: null;
    }

    public function updateEmpresa(int $empresaId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
             SET nome = :nome,
                 nome_fantasia = :nome_fantasia,
                 cpf_cnpj = :cpf_cnpj,
                 telefone = :telefone,
                 endereco = :endereco
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $empresaId,
            ':nome' => $data['nome'],
            ':nome_fantasia' => $data['nome_fantasia'] ?? null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':endereco' => $data['endereco'] ?? null,
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function getConfiguracoes(int $empresaId): array
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'SELECT *
             FROM configuracoes_empresa
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        $config = $stmt->fetch();

        return $config ?: [];
    }

    public function ensureConfiguracoes(int $empresaId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO configuracoes_empresa (empresa_id)
             VALUES (:empresa_id)'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);
    }

    public function saveReceiptRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET receipt_mode = :receipt_mode,
                 receipt_template = :receipt_template
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':receipt_mode' => $data['receipt_mode'],
            ':receipt_template' => $data['receipt_template'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function saveDueRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET expiration_alert_days = :expiration_alert_days,
                 debt_due_days = :debt_due_days
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':expiration_alert_days' => $data['expiration_alert_days'],
            ':debt_due_days' => $data['debt_due_days'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function saveStockRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET default_min_stock = :default_min_stock,
                 block_expired_products = :block_expired_products,
                 block_negative_stock = :block_negative_stock,
                 low_stock_alert = :low_stock_alert
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':default_min_stock' => $data['default_min_stock'],
            ':block_expired_products' => $data['block_expired_products'],
            ':block_negative_stock' => $data['block_negative_stock'],
            ':low_stock_alert' => $data['low_stock_alert'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function savePaymentRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET payment_pix = :payment_pix,
                 payment_cash = :payment_cash,
                 payment_credit = :payment_credit,
                 payment_debit = :payment_debit,
                 payment_account = :payment_account,
                 payment_mixed = :payment_mixed
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':payment_pix' => $data['payment_pix'],
            ':payment_cash' => $data['payment_cash'],
            ':payment_credit' => $data['payment_credit'],
            ':payment_debit' => $data['payment_debit'],
            ':payment_account' => $data['payment_account'],
            ':payment_mixed' => $data['payment_mixed'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function saveCashRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET allow_discount = :allow_discount,
                 discount_limit_percent = :discount_limit_percent,
                 require_customer_for_account = :require_customer_for_account,
                 require_cancellation_reason = :require_cancellation_reason
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':allow_discount' => $data['allow_discount'],
            ':discount_limit_percent' => $data['discount_limit_percent'],
            ':require_customer_for_account' => $data['require_customer_for_account'],
            ':require_cancellation_reason' => $data['require_cancellation_reason'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function saveSecurityRules(int $empresaId, array $data): bool
    {
        $this->ensureConfiguracoes($empresaId);

        $stmt = $this->db->prepare(
            'UPDATE configuracoes_empresa
             SET audit_log_enabled = :audit_log_enabled,
                 confirm_deletes = :confirm_deletes,
                 operator_pin_enabled = :operator_pin_enabled,
                 notifications_enabled = :notifications_enabled
             WHERE empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':audit_log_enabled' => $data['audit_log_enabled'],
            ':confirm_deletes' => $data['confirm_deletes'],
            ':operator_pin_enabled' => $data['operator_pin_enabled'],
            ':notifications_enabled' => $data['notifications_enabled'],
        ]);

        return $stmt->rowCount() >= 0;
    }
}