<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubProjectDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $title = null,
        public ?array $assigned = null
    ) {}
}
