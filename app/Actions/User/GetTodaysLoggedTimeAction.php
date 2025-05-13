<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\TimeEntry;
use App\Models\User;
use App\Traits\FormatsDurationsTrait;
use Carbon\Carbon;

class GetTodaysLoggedTimeAction
{
    use FormatsDurationsTrait;

    public function execute(User $user): string
    {
        if ($user->do_not_track) {
            return '0h 0m';
        }

        $today = Carbon::today();
        $totalSecondsToday = TimeEntry::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->sum('duration_seconds');

        $loggedMinutes = $totalSecondsToday / 60;

        return $this->formatMinutesToHoursMinutes($loggedMinutes);
    }
}
