<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SystemPin;

final readonly class SystemPinUserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $Nombre = null,
        public ?string $Email = null,
    ) {}
}
