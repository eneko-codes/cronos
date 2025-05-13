<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserLeave;
use Carbon\Carbon;

class GetUpcomingLeaveAction
{
    public function execute(User $user): ?UserLeave
    {
        if ($user->do_not_track) {
            return null;
        }

        $today = Carbon::today();

        return UserLeave::where('user_id', $user->id)
            ->where('status', 'validate')
            ->where('end_date', '>=', $today)
            ->where('start_date', '<=', $today->copy()->addDays(30))
            ->orderBy('start_date', 'asc')
            ->with('leaveType') // Eager load leaveType relationship
            ->first();
    }
}
