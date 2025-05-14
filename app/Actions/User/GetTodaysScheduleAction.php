<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\DataTransferObjects\TodaysScheduleData;
use App\Models\User;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class GetTodaysScheduleAction
{
    public function execute(User $user): ?TodaysScheduleData
    {
        if ($user->do_not_track) {
            return null;
        }

        $today = Carbon::today();
        $weekday = ($today->dayOfWeek + 6) % 7; // 0=Monday, ..., 6=Sunday

        $activeSchedule = UserSchedule::where('user_id', $user->id)
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->with('schedule:odoo_schedule_id,description') // Ensure only necessary fields are loaded
            ->first();

        if ($activeSchedule !== null && $activeSchedule->schedule !== null) {
            $scheduleModel = $activeSchedule->schedule;
            $details = $scheduleModel->scheduleDetails()->where('weekday', $weekday)->get();
            $totalMinutes = 0;

            if ($details->isNotEmpty()) {
                /** @var \App\Models\ScheduleDetail $detail */
                foreach ($details->sortBy('start') as $detail) {
                    // Ensure start and end times are parsed correctly for diffInMinutes
                    $start = Carbon::parse($detail->start);
                    $end = Carbon::parse($detail->end);
                    $minutesForSlot = $start->diffInMinutes($end);
                    $totalMinutes += $minutesForSlot;
                }
            }

            return new TodaysScheduleData(
                duration: CarbonInterval::minutes((int) round($totalMinutes))->cascade()->format('%hh %dm'),
                name: $scheduleModel->description ?? 'Default Schedule',
            );
        }

        return null;
    }
}
