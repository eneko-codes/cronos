<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class GetTodaysLoggedTimeQuery
{
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

        return CarbonInterval::minutes((int) round($loggedMinutes))->cascade()->format('%hh %dm');
    }
}
