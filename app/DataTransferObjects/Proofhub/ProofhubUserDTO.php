<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubUserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $email = null,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $verified = null,
        public ?array $groups = null,
        public ?bool $suspended = null,
        public ?array $role = null,
        public ?string $proofhub_created_at = null,
        public ?string $proofhub_updated_at = null
    ) {}
}
