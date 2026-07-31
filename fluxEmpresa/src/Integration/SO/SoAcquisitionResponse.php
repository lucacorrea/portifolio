<?php
declare(strict_types=1);
namespace App\Integration\SO;
use InvalidArgumentException;
final class SoAcquisitionResponse {
    public function __construct(private readonly int $id, private readonly string $number, private readonly ?string $deliveryCode, private readonly string $status) {}
    public static function fromArray(array $body): self {
        $data = $body['data'] ?? null;
        if (($body['success'] ?? false) !== true || !is_array($data) || !is_int($data['acquisition_id'] ?? null) || $data['acquisition_id'] <= 0 || !is_string($data['acquisition_number'] ?? null) || trim($data['acquisition_number']) === '' || !is_string($data['status'] ?? null)) throw new InvalidArgumentException('Resposta inválida do SO.');
        foreach (['acquisition_number' => 50, 'status' => 50, 'delivery_code' => 50] as $field => $limit) if (isset($data[$field]) && (!is_string($data[$field]) || mb_strlen($data[$field]) > $limit)) throw new InvalidArgumentException('Resposta inválida do SO.');
        return new self($data['acquisition_id'], trim($data['acquisition_number']), isset($data['delivery_code']) ? trim((string) $data['delivery_code']) : null, trim($data['status']));
    }
    public function id(): int { return $this->id; } public function number(): string { return $this->number; } public function deliveryCode(): ?string { return $this->deliveryCode; } public function status(): string { return $this->status; }
}
