<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubTimeEntryDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $date = null,
        public ?string $created_at = null,
        public ?int $logged_hours = null,
        public ?int $logged_mins = null,
        public ?array $timesheet = null,
        public ?array $task = null,
        public ?array $project = null,
        public ?array $creator = null,
        public ?string $status = null,
        public ?string $description = null
    ) {}
}
