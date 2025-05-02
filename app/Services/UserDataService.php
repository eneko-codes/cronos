<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class UserDataService
{
    /**
     * Returns data for the user within a specified date range.
     *
     * Collects and organizes all user data (schedules, leaves, attendances, time entries)
     * within the provided date range for comprehensive reporting.
     *
     * @param  User  $user  The user for whom to fetch data.
     * @param  Carbon  $startDate  Beginning of the date range.
     * @param  Carbon  $endDate  End of the date range.
     * @return array Associative array of user data organized by date.
     */
    public function getDataForUserAndDateRange(User $user, Carbon $startDate, Carbon $endDate): array
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
