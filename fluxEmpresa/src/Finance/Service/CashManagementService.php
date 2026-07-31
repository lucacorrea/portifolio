<?php

declare(strict_types=1);

namespace App\Finance\Service;

use App\Company\DTO\CompanyScope;
use App\Inventory\Service\InventoryManagementService;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class CashManagementService
{
    use CashSessionOperations;
    use PointOfSaleOperations;

    private const FORMS = ['dinheiro', 'pix', 'boleto', 'cartao_debito', 'cartao_credito', 'transferencia', 'cheque', 'outro'];

    private readonly InventoryManagementService $inventory;

    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope,
        ?InventoryManagementService $inventory = null
    )
    {
        if ($inventory !== null && $inventory->companyId() !== $companyScope->id()) {
            throw new InvalidArgumentException('Serviços financeiros pertencem a empresas diferentes.');
        }
        $this->inventory = $inventory ?? new InventoryManagementService($connection, $companyScope);
    }

    public function companyId(): int
    {
        return $this->companyScope->id();
    }

    /** @return string[] */
    public static function paymentForms(): array
    {
        return self::FORMS;
    }

    public function registerEntry(string $originType, int $originId, string $description, string $form, string $value, int $userId, ?DateTimeImmutable $date = null): int
    {
        return $this->transactional(function () use ($originType, $originId, $description, $form, $value, $userId, $date): int {
            $session = $this->requireOpenSession(true);
            return $this->insertMovement((int) $session['id'], 'entrada', $originType, $originId, $description, $form, $this->moneyCents($value), $userId, $date);
        });
    }

    public function registerExit(string $originType, int $originId, string $description, string $form, string $value, int $userId, ?DateTimeImmutable $date = null): int
    {
        return $this->transactional(function () use ($originType, $originId, $description, $form, $value, $userId, $date): int {
            $session = $this->requireOpenSession(true);
            $cents = $this->moneyCents($value);
            if ($form === 'dinheiro') $this->assertCashAvailable($session, $cents);
            return $this->insertMovement((int) $session['id'], 'saida', $originType, $originId, $description, $form, $cents, $userId, $date);
        });
    }

    public function reverseMovement(int $sourceId, string $originType, int $originId, string $description, int $userId): int
    {
        return $this->transactional(function () use ($sourceId, $originType, $originId, $description, $userId): int {
            $session = $this->requireOpenSession(true);
            $statement = $this->connection->prepare('SELECT * FROM caixa_movimentacoes WHERE id = :id AND empresa_id = :empresa_id FOR UPDATE');
            $statement->execute(['id' => $sourceId, 'empresa_id' => $this->companyScope->id()]);
            $source = $statement->fetch();
            if ($source === false || !in_array((string) $source['tipo'], ['entrada', 'saida'], true)) {
                throw new InvalidArgumentException('Movimentação original do Caixa não encontrada.');
            }
            $statement = $this->connection->prepare('SELECT id FROM caixa_movimentacoes WHERE estornado_de_id = :id AND empresa_id = :empresa_id LIMIT 1');
            $statement->execute(['id' => $sourceId, 'empresa_id' => $this->companyScope->id()]);
            if ($statement->fetchColumn() !== false) throw new InvalidArgumentException('Movimentação do Caixa já estornada.');

            $type = $source['tipo'] === 'entrada' ? 'estorno_entrada' : 'estorno_saida';
            $sourceCents = (int) round((float) $source['valor'] * 100);
            if ($type === 'estorno_entrada' && $source['forma_pagamento'] === 'dinheiro') {
                $this->assertCashAvailable($session, $sourceCents);
            }
            return $this->insertMovement(
                (int) $session['id'], $type, $originType, $originId, $description,
                (string) ($source['forma_pagamento'] ?? 'outro'), $sourceCents,
                $userId, null, $sourceId
            );
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function listByDate(string $date): array
    {
        $date = $this->validDate($date);
        $statement = $this->connection->prepare(
            'SELECT movimento.*, usuario.nome AS usuario_nome, sessao.codigo AS sessao_codigo
               FROM caixa_movimentacoes movimento
               JOIN usuarios usuario ON usuario.id = movimento.usuario_id
               LEFT JOIN caixa_sessoes sessao ON sessao.id = movimento.caixa_sessao_id AND sessao.empresa_id = movimento.empresa_id
              WHERE DATE(movimento.data_movimento) = :date AND movimento.empresa_id = :empresa_id
              ORDER BY movimento.data_movimento DESC, movimento.id DESC'
        );
        $statement->execute(['date' => $date, 'empresa_id' => $this->companyScope->id()]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function currentSession(): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT sessao.*, abertura.nome AS aberto_por_nome, fechamento.nome AS fechado_por_nome
               FROM caixa_sessoes sessao
               JOIN usuarios abertura ON abertura.id = sessao.aberto_por
               LEFT JOIN usuarios fechamento ON fechamento.id = sessao.fechado_por
              WHERE sessao.status = "aberta" AND sessao.empresa_id = :empresa_id LIMIT 1'
        );
        $statement->execute(['empresa_id' => $this->companyScope->id()]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    private function requireOpenSession(bool $lock): array
    {
        $sql = 'SELECT * FROM caixa_sessoes WHERE status = "aberta" AND empresa_id = :empresa_id LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $statement = $this->connection->prepare($sql);
        $statement->execute(['empresa_id' => $this->companyScope->id()]);
        $row = $statement->fetch();
        if ($row === false) throw new InvalidArgumentException('Abra o Caixa antes de registrar esta operação.');
        return $row;
    }

    private function insertMovement(int $sessionId, string $type, string $originType, ?int $originId, string $description, string $form, int $cents, int $userId, ?DateTimeImmutable $date = null, ?int $reversedFrom = null): int
    {
        if ($cents <= 0) throw new InvalidArgumentException('Valor do Caixa deve ser maior que zero.');
        if (!in_array($form, self::FORMS, true)) throw new InvalidArgumentException('Forma de pagamento inválida.');
        $description = $this->requiredText($description, 255, 'Informe a descrição da movimentação.');
        $originType = $this->requiredIdentifier($originType);
        $statement = $this->connection->prepare(
            'INSERT INTO caixa_movimentacoes
                (empresa_id, caixa_sessao_id, tipo, origem_tipo, origem_id, descricao, forma_pagamento, valor,
                 data_movimento, usuario_id, estornado_de_id)
             VALUES (:empresa_id, :session_id, :type, :origin_type, :origin_id, :description, :form, :value,
                     :movement_date, :user_id, :reversed_from)'
        );
        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'session_id' => $sessionId, 'type' => $type, 'origin_type' => $originType,
            'origin_id' => $originId, 'description' => $description, 'form' => $form,
            'value' => $this->centsToDecimal($cents),
            'movement_date' => ($date ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'user_id' => $userId, 'reversed_from' => $reversedFrom,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string,mixed> $session */
    private function assertCashAvailable(array $session, int $cents): void
    {
        $opening = (int) round((float) $session['valor_abertura'] * 100);
        if ($cents > $this->cashPositionCents((int) $session['id'], $opening)) {
            throw new InvalidArgumentException('Dinheiro insuficiente na gaveta para esta saída.');
        }
    }

    private function transactional(callable $operation): mixed
    {
        $owns = !$this->connection->inTransaction();
        if ($owns) $this->connection->beginTransaction();
        try {
            $result = $operation();
            if ($owns) $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function moneyCents(mixed $value, bool $allowZero = false): int
    {
        $text = str_replace(' ', '', trim((string) $value));
        if (str_contains($text, ',')) $text = str_replace(',', '.', str_replace('.', '', $text));
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $text)) throw new InvalidArgumentException('Valor monetário inválido.');
        $cents = (int) round((float) $text * 100);
        if ($cents < 0 || $cents > 999999999999 || (!$allowZero && $cents === 0)) throw new InvalidArgumentException('Valor monetário inválido.');
        return $cents;
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function requiredText(mixed $value, int $max, string $message): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($text === '' || $text !== strip_tags($text) || str_contains($text, "\0") || $length > $max) throw new InvalidArgumentException($message);
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        if (trim((string) $value) === '') return null;
        return $this->requiredText($value, $max, 'Observação inválida.');
    }

    private function requiredIdentifier(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z0-9_]{2,40}$/', $value) !== 1) throw new InvalidArgumentException('Origem da movimentação inválida.');
        return $value;
    }

    private function validDate(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : date('Y-m-d');
    }
}
