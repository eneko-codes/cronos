<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Desktime;

final readonly class DesktimeUserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $timezone = null
    ) {}
}
