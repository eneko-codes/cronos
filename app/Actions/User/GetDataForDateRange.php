<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Carbon\Carbon;

class GetDataForDateRange
{
    /**
     * Get comprehensive user data (schedules, leaves, attendances, time entries)
     * for a specific user within a given date range.
     *
     * @param  User  $user  The user for whom to fetch data.
     * @param  Carbon  $startDate  Beginning of the date range.
     * @param  Carbon  $endDate  End of the date range.
     * @return array Associative array containing 'schedules', 'leaves', 'attendances', 'time_entries'.
     */
    public function handle(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Ensure we're working with dates at day precision
        $startDateObject = $startDate->copy()->startOfDay();
        $endDateObject = $endDate->copy()->endOfDay();

        // Get schedules with eager loading to avoid N+1 queries
        $schedules = $user->userSchedules()
            ->with(['schedule.scheduleDetails'])
            ->where('effective_from', '<=', $endDateObject)
            ->where(function ($query) use ($startDateObject) {
                $query
                    ->where('effective_until', '>=', $startDateObject)
                    ->orWhereNull('effective_until');
            })
            ->get();

        // Get leaves with eager loading
        $leaves = $user->userLeaves()
            ->with(['leaveType', 'department', 'category'])
            ->where(function ($query) use ($startDateObject, $endDateObject) {
                $query
                    ->whereBetween('start_date', [$startDateObject, $endDateObject])
                    ->orWhereBetween('end_date', [$startDateObject, $endDateObject])
                    ->orWhere(function ($innerQuery) use (
                        $startDateObject,
                        $endDateObject
                    ) {
                        $innerQuery
                            ->where('start_date', '<=', $startDateObject)
                            ->where('end_date', '>=', $endDateObject);
                    });
            })
            ->get();

        // Get attendances with eager loading
        $attendances = $user->userAttendances()
            ->whereBetween('date', [
                $startDateObject->toDateString(),
                $endDateObject->toDateString(),
            ])
            ->get();

        // Get time entries with eager loading
        $timeEntries = $user->timeEntries()
            ->with(['project', 'task'])
            ->whereBetween('date', [
                $startDateObject->toDateString(),
                $endDateObject->toDateString(),
            ])
            ->get();

        return [
            'schedules' => $schedules,
            'leaves' => $leaves,
            'attendances' => $attendances,
            'time_entries' => $timeEntries,
        ];
    }
}
