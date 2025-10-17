<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\DataTransferObjects\Dashboard\DayData;
use App\DataTransferObjects\Dashboard\PeriodTotals;
use App\Models\User;
use App\Services\Dashboard\AttendanceService;
use App\Services\Dashboard\DeviationCalculator;
use App\Services\Dashboard\LeaveService;
use App\Services\Dashboard\ScheduleService;
use App\Services\Dashboard\TimeEntryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class TimeSheetTable extends Component
{
    #[Locked]
    public int $userId;

    #[Url]
    public string $currentDate;

    #[Url]
    public string $viewMode = 'weekly';

    #[Url]
    public bool $showDeviations = false;

    public function mount(User $user, ?string $currentDate = null): void
    {
        $this->userId = $user->id;
        $this->currentDate = $currentDate ?? now()->toDateString();
    }

    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    #[Computed]
    public function periodStart(): Carbon
    {
        return $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->startOfWeek()
            : Carbon::parse($this->currentDate)->startOfMonth();
    }

    #[Computed]
    public function periodEnd(): Carbon
    {
        return $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->endOfWeek()
            : Carbon::parse($this->currentDate)->endOfMonth();
    }

    #[Computed]
    public function periodData(): Collection
    {
        $rawData = $this->user->getDataForDateRange($this->periodStart, $this->periodEnd);
        
        return $this->processPeriodData($rawData);
    }

    #[Computed]
    public function totals(): PeriodTotals
    {
        $totals = [
            'scheduled' => 0,
            'attendance' => 0,
            'worked' => 0,
            'leave' => 0,
        ];

        $today = now()->startOfDay();

        foreach ($this->periodData as $dayData) {
            if ($dayData->isFuture()) {
                continue;
            }

            $totals['scheduled'] += $dayData->schedule->totalMinutes ?? 0;
            $totals['attendance'] += $this->timeToMinutes($dayData->attendance->duration);
            $totals['worked'] += $this->timeToMinutes($dayData->worked->duration);
            $totals['leave'] += $dayData->leave->actualMinutes ?? 0;
        }

        return new PeriodTotals(
            scheduled: $totals['scheduled'],
            attendance: $totals['attendance'],
            worked: $totals['worked'],
            leave: $totals['leave'],
        );
    }

    #[Computed]
    public function overallDeviations(): ?array
    {
        if (!$this->showDeviations) {
            return null;
        }

        $totals = [
            'scheduled' => $this->totals->scheduled,
            'attendance' => $this->totals->attendance,
            'worked' => $this->totals->worked,
            'leave' => $this->totals->leave,
        ];

        $calculator = new DeviationCalculator();
        return $calculator->calculateOverallDeviations($totals);
    }

    public function toggleDeviations(): void
    {
        $this->showDeviations = !$this->showDeviations;
    }

    public function changeViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    protected function processPeriodData(array $rawData): Collection
    {
        $dates = new Collection;
        $cursor = $this->periodStart->copy();

        $scheduleService = new ScheduleService();
        $attendanceService = new AttendanceService();
        $timeEntryService = new TimeEntryService();
        $leaveService = new LeaveService();
        $deviationCalculator = new DeviationCalculator();

        while ($cursor->lte($this->periodEnd)) {
            $dateString = $cursor->toDateString();

            $scheduleData = $scheduleService->getScheduleForDate($rawData['schedules'], $dateString);
            $leaveData = $leaveService->getLeaveForDate($rawData['leaves'], $dateString, $rawData['schedules']);
            $attendanceData = $attendanceService->getAttendanceForDate($rawData['attendances'], $dateString);
            $workedData = $timeEntryService->getWorkedTimeForDate($dateString, $this->userId);

            $deviations = null;
            if ($this->showDeviations) {
                $deviations = $deviationCalculator->calculateDailyDeviations(
                    $scheduleData['duration'] ?? null,
                    $attendanceData['duration'] ?? null,
                    $workedData['duration'] ?? null,
                    $leaveData['isHalfDay'] ?? false,
                    $dateString
                );
            }

            $dates->put($dateString, new DayData(
                date: $dateString,
                schedule: $scheduleData ? new \App\DataTransferObjects\Dashboard\ScheduleData(
                    duration: $scheduleData['duration'],
                    slots: $scheduleData['slots'],
                    scheduleName: $scheduleData['scheduleName'],
                    totalMinutes: $scheduleData['totalMinutes'],
                ) : null,
                leave: $leaveData ? new \App\DataTransferObjects\Dashboard\LeaveData(
                    duration: $leaveData['duration'],
                    durationText: $leaveData['durationText'],
                    durationDays: $leaveData['durationDays'],
                    status: $leaveData['status'],
                    isHalfDay: $leaveData['isHalfDay'],
                    timePeriod: $leaveData['timePeriod'],
                    timeRange: $leaveData['timeRange'],
                    halfDayTime: $leaveData['halfDayTime'],
                    startTime: $leaveData['startTime'],
                    endTime: $leaveData['endTime'],
                    actualMinutes: $leaveData['actualMinutes'],
                    leaveType: $leaveData['leaveType'],
                    context: $leaveData['context'],
                ) : null,
                attendance: $attendanceData ? new \App\DataTransferObjects\Dashboard\AttendanceData(
                    duration: $attendanceData['duration'],
                    isRemote: $attendanceData['is_remote'],
                    isMixed: $attendanceData['is_mixed'],
                    hasOffice: $attendanceData['has_office'],
                    hasRemote: $attendanceData['has_remote'],
                    segments: $attendanceData['segments'],
                    hasOpenSegment: $attendanceData['has_open_segment'],
                    start: $attendanceData['start'],
                    end: $attendanceData['end'],
                ) : null,
                worked: $workedData['duration'] ? new \App\DataTransferObjects\Dashboard\TimeEntryData(
                    duration: $workedData['duration'],
                    projects: $workedData['projects']->toArray(),
                    detailedEntries: $workedData['detailedEntries']->toArray(),
                ) : null,
                attendanceVsScheduled: $deviations['attendanceVsScheduled'] ?? null,
                workedVsScheduled: $deviations['workedVsScheduled'] ?? null,
                workedVsAttendance: $deviations['workedVsAttendance'] ?? null,
            ));

            $cursor->addDay();
        }

        return $dates;
    }

    protected function timeToMinutes(?string $time): int
    {
        if (empty(trim($time ?? ''))) {
            return 0;
        }

        try {
            return (int) \Carbon\CarbonInterval::fromString($time)->totalMinutes;
        } catch (\Exception $e) {
            $parts = explode(' ', trim($time));
            $hours = 0;
            $minutes = 0;

            foreach ($parts as $part) {
                $part = trim($part);
                if (str_ends_with($part, 'h')) {
                    $hours = (int) rtrim($part, 'h');
                } elseif (str_ends_with($part, 'm')) {
                    $minutes = (int) rtrim($part, 'm');
                }
            }

            return ($hours * 60) + $minutes;
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.time-sheet-table');
    }
}
