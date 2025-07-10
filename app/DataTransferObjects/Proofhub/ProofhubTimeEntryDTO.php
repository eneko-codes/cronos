<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubTimeEntryDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public ?int $project_id = null,
        public ?int $task_id = null,
        public ?float $duration = null,
        public ?string $date = null,
        public ?string $created_at = null,
        public ?string $user_email = null,
        public ?string $task_title = null,
        public ?string $status = null,
        public ?string $description = null,
        public ?string $proofhub_updated_at = null,
        public ?bool $billable = null,
        public ?string $comments = null,
        public array|string|null $tags = null
    ) {}
}
