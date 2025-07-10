<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

/**
 * Data Transfer Object for ProofHub Project.
 *
 * All fields and types must match the raw ProofHub API response exactly.
 * - status: array|null (object from API, not string)
 */
final readonly class ProofhubProjectDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $title = null,
        public ?array $assigned = null,
        public ?array $status = null,
        public ?string $description = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?int $owner_id = null,
        public ?string $proofhub_created_at = null,
        public ?string $proofhub_updated_at = null
    ) {}
}
