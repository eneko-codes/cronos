<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\OverallDeviationDetails;
use App\DataTransferObjects\PeriodDayData;
use App\Models\User;
use App\Services\Dashboard\DashboardDataAggregatorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('User Dashboard')]
#[Lazy]
class UserDashboard extends Component
{
    /**
     * The user model instance.
     */
    #[Locked]
    public User $user;

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

    /**
     * @var Collection<string, PeriodDayData>
     */
    public Collection $periodData;

    public ?DashboardTotals $dashboardTotals = null;

    public ?OverallDeviationDetails $totalDeviationsDetails = null;

    /**
     * Flag indicating if the authenticated user has admin privileges.
     */
    public bool $isAdmin = false;

    /**
     * Flag indicating if we are viewing a specific user via ID, not the authenticated user's own dashboard.
     */
    public bool $isViewingSpecificUser = false;

    protected $listeners = [
        'timeSheetPreviousPeriod' => 'previousPeriod',
        'timeSheetNextPeriod' => 'nextPeriod',
        'timeSheetToggleDeviations' => 'toggleDeviations',
        'timeSheetChangeViewMode' => 'changeViewMode',
    ];

    /**
     * Initializes the component, loads the user, sets the initial period, and loads data.
     *
     * @param  int|null  $id  The ID of the user to display, or null to display the authenticated user.
     */
    public function mount($id = null): void
    {
        $this->currentDate = now()->toDateString();
        $this->isAdmin = Auth::user()->isAdmin();
        $this->isViewingSpecificUser = $id !== null;

        if ($this->isViewingSpecificUser) {
            $this->user = User::findOrFail($id);
        } else {
            $this->user = Auth::user();
        }

        $this->loadPeriodDataAndTotals();
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
     * Renders the component's Blade view.
     */
    public function render()
    {
        // $dashboardTotals and $totalDeviationsDetails are now direct public properties
        // set by loadPeriodDataAndTotals via the service.
        $isNextPeriodDisabled = $this->isNextPeriodDisabled; // This is a computed property call

        return view('livewire.user-dashboard', [
            'dashboardTotals' => $this->dashboardTotals,
            'totalDeviationsDetailsForTable' => $this->totalDeviationsDetails,
            'isNextPeriodDisabledForTable' => $isNextPeriodDisabled,
        ]);
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
}
