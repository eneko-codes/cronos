<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubUserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $email = null,
        public ?string $name = null
    ) {}
}
