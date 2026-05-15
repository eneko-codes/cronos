<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubTaskDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $title = null,
        public ?int $project_id = null,
        public ?array $project = null,
        public ?array $assigned = null,
        public ?array $subtasks = null,
        public ?array $stage = null,
        public ?string $due_date = null,
        public ?string $description = null,
        public array|string|null $tags = null,
        public ?array $creator = null,
        public ?string $proofhub_created_at = null,
        public ?string $proofhub_updated_at = null
    ) {}
}
