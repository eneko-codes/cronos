<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Desktime;

final readonly class DesktimeAttendanceDTO
{
    public function __construct(
        public ?int $user_id = null,
        public ?string $date = null,
        public ?int $desktimeTime = null
    ) {}
}
