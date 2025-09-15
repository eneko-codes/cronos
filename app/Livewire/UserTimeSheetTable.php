<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\Dashboard\DashboardDataAggregatorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Lazy]
class UserTimeSheetTable extends Component
{
    #[Locked]
    public int $userId;

    /**
     * The first date of the currently displayed period (YYYY-MM-DD string).
     */
    #[Url]
    public string $currentDate;

    /**
     * Current view mode: 'weekly' or 'monthly'.
     */
    #[Url]
    public string $viewMode = 'weekly';

    /**
     * Flag to toggle the display of deviation percentage columns.
     */
    #[Url]
    public bool $showDeviations = false;

    /** @var Collection<string, array> */
    public Collection $periodData;

    public ?array $dashboardTotals = null;

    public ?array $totalDeviationsDetails = null;

    public function mount(User $user): void
    {
        $this->userId = $user->id;
        $this->currentDate = now()->toDateString();
        // viewMode and showDeviations will be initialized from URL or their defaults
        $this->loadPeriodDataAndTotals();
    }

    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    /**
     * Toggles the visibility of the deviation columns and reloads data.
     */
    public function toggleDeviations(): void
    {
        $this->showDeviations = ! $this->showDeviations;
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Switches the view between 'weekly' and 'monthly' modes and reloads data.
     *
     * @param  string  $mode  The desired view mode ('weekly' or 'monthly').
     */
    public function changeViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the previous week or month.
     */
    public function previousPeriod(): void
    {
        $startDate = $this->getPeriodStart();
        $this->setPeriodStart(
            $this->viewMode === 'weekly' ? $startDate->subWeek() : $startDate->subMonth()
        );
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the next week or month.
     */
    public function nextPeriod(): void
    {
        $startDate = $this->getPeriodStart();
        $this->setPeriodStart(
            $this->viewMode === 'weekly' ? $startDate->addWeek() : $startDate->addMonth()
        );
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Computes whether the "next period" navigation button should be disabled.
     * Prevents navigating into the future.
     */
    #[Computed]
    public function isNextPeriodDisabled(): bool
    {
        $current = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'weekly') {
            $candidate = $current->copy()->addWeek();

            return $candidate->startOf('week')->gt(now());
        } else {
            $candidate = $current->copy()->addMonth();

            return $candidate->startOf('month')->gt(now());
        }
    }

    /**
     * Gets the start date of the current period as a Carbon instance (UTC).
     */
    protected function getPeriodStart(): Carbon
    {
        return Carbon::parse($this->currentDate)->startOfDay();
    }

    /**
     * Calculates the end date of the current period based on the view mode.
     *
     * @return Carbon The end date of the period.
     */
    protected function getPeriodEnd(): Carbon
    {
        $startDate = $this->getPeriodStart();

        return $this->viewMode === 'weekly'
            ? $startDate->clone()->endOfWeek()
            : $startDate->clone()->endOfMonth();
    }

    /**
     * Fetches and processes user data for the current period using DashboardDataAggregatorService.
     */
    protected function loadPeriodDataAndTotals(): void
    {
        $startDate = $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->startOfWeek()
            : Carbon::parse($this->currentDate)->startOfMonth();

        $endDate = $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->endOfWeek()
            : Carbon::parse($this->currentDate)->endOfMonth();

        $data = app(DashboardDataAggregatorService::class)->aggregatePeriodData(
            $this->user,
            $startDate,
            $endDate,
            $this->showDeviations
        );

        $this->periodData = $data['periodData'];
        $this->dashboardTotals = $data['dashboardTotals'];
        $this->totalDeviationsDetails = $data['totalDeviationsDetails'];
    }

    /**
     * Sets the start date of the current period from a Carbon instance.
     *
     * @param  Carbon  $date  The Carbon date object to set as the start of the period.
     */
    protected function setPeriodStart(Carbon $date): void
    {
        $this->currentDate = $date->format('Y-m-d');
    }

    public function getFormattedTotalDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        return \Carbon\CarbonInterval::minutes($minutes)->cascade()->format('%hh %Im');
    }

    public function dispatchPreviousPeriod(): void
    {
        $this->previousPeriod();
    }

    public function dispatchNextPeriod(): void
    {
        $this->nextPeriod();
    }

    public function dispatchToggleDeviations(): void
    {
        $this->toggleDeviations();
    }

    public function dispatchChangeViewMode(string $mode): void
    {
        $this->changeViewMode($mode);
    }

    public function placeholder(array $params = [])
    {
        // The placeholder view can use $params['viewMode'] if needed to adjust skeleton rows/cols
        // For now, a generic table skeleton.
        return view('livewire.placeholders.user-time-sheet-table-skeleton', $params);
    }

    public function render()
    {
        return view('livewire.user-time-sheet-table');
    }
}
