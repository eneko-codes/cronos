<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Proofhub;

final readonly class ProofhubUserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $verified = null,
        public ?array $groups = null,
        public ?int $timezone = null,
        public ?string $initials = null,
        public ?string $profile_color = null,
        public ?string $image_url = null,
        public ?string $language = null,
        public ?bool $suspended = null,
        public ?string $last_active = null,
        public ?array $role = null,
        public ?string $proofhub_created_at = null,
        public ?string $proofhub_updated_at = null
    ) {}
}
