<?php

declare(strict_types=1);

namespace App\Queries;

use App\DataTransferObjects\TodaysAttendanceData;
use App\Models\User;
use App\Models\UserAttendance;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class GetTodaysAttendanceQuery
{
    public function execute(User $user): TodaysAttendanceData // Return non-nullable, provide default
    {
        if ($user->do_not_track) {
            return new TodaysAttendanceData(
                status: 'Not Tracked',
                timeInfo: null,
                duration: '0h 0m',
                isRemote: false,
                clockedIn: false
            );
        }

        $today = Carbon::today();
        $attendance = UserAttendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance) {
            $status = 'Unknown';
            $timeInfo = null;
            $durationMinutes = 0;
            $isRemote = (bool) $attendance->is_remote;
            $clockedIn = false;

            if ($attendance->is_remote) {
                $status = 'Remote';
                $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
            } elseif ($attendance->start && $attendance->end) {
                $status = 'In Office - Clocked Out';
                $start = Carbon::parse($attendance->start);
                $end = Carbon::parse($attendance->end);
                $timeInfo = $start->format('H:i').' - '.$end->format('H:i');
                $durationMinutes = $start->diffInMinutes($end);
            } elseif ($attendance->start && ! $attendance->end) {
                $status = 'In Office - Clocked In';
                $start = Carbon::parse($attendance->start);
                $timeInfo = 'Since '.$start->format('H:i');
                $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
                $clockedIn = true;
            } elseif (! $attendance->start && ! $attendance->end && isset($attendance->presence_seconds) && $attendance->presence_seconds > 0) {
                $status = 'Present (System)';
                $durationMinutes = (int) ($attendance->presence_seconds / 60);
            } else {
                $status = 'No Activity Recorded';
            }

            return new TodaysAttendanceData(
                status: $status,
                timeInfo: $timeInfo,
                duration: CarbonInterval::minutes((int) round($durationMinutes))->cascade()->format('%hh %dm'),
                isRemote: $isRemote,
                clockedIn: $clockedIn
            );
        }

        return new TodaysAttendanceData(
            status: 'Not Clocked In',
            timeInfo: null,
            duration: '0h 0m',
            isRemote: false,
            clockedIn: false
        );
    }
}
