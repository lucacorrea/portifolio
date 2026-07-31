<?php
declare(strict_types=1);
namespace App\Integration\SO\DTO;
final class SoSupplierData
{
    public function __construct(public readonly int $id, public readonly string $name, public readonly string $document, public readonly string $contact, public readonly string $phone) {}
}
