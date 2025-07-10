<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubTaskDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?int $project_id = null,
        public ?array $project = null,
        public ?array $assigned = null,
        public ?string $title = null,
        public ?array $subtasks = null,
        public ?string $status = null,
        public ?string $due_date = null,
        public ?string $description = null,
        public array|string|null $tags = null,
        public ?string $priority = null,
        public ?string $created_by = null,
        public ?string $updated_by = null,
        public ?string $proofhub_created_at = null,
        public ?string $proofhub_updated_at = null
    ) {}
}
