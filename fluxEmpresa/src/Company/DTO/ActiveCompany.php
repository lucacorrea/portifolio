<?php
declare(strict_types=1);
namespace App\Company\DTO;
final class ActiveCompany { public function __construct(public readonly int $id, public readonly string $uuid, public readonly string $name, public readonly int $supportUserId, public readonly int $accessId, public readonly string $enteredAt) {} }
