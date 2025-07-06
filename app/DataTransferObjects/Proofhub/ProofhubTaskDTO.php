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
        public ?array $subtasks = null
    ) {}
}
