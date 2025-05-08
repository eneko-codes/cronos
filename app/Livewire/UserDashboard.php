<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\OverallDeviationDetails;
use App\DataTransferObjects\PeriodDayData;
use App\Models\Setting;
use App\Models\User;
use App\Services\UserDashboardDataProcessorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('User Dashboard')]
class UserDashboard extends Component
{
    /**
     * The user model instance.
     */
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

    /**
     * Indicates if notifications are enabled globally.
     */
    public bool $isGloballyEnabled = true;

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
        $this->isViewingSpecificUser = $id !== null;

        if ($id !== null) {
            $authenticatedUser = Auth::user();
            if (! $authenticatedUser || ! $authenticatedUser->isAdmin()) {
                if ($authenticatedUser && $authenticatedUser->id != $id) {
                    $this->redirect(route('dashboard'), navigate: true);

                    return;
                }
            }
            // Simplified eager loading for mount, specific data is fetched by the service
            $this->user = User::findOrFail($id);
        } else {
            $this->user = Auth::user();
        }

        $this->isAdmin = Auth::check() && Auth::user()->isAdmin();
        $this->periodData = new Collection;
        $this->dashboardTotals = new DashboardTotals(scheduled: 0, attendance: 0, worked: 0, leave: 0);

        $this->setPeriodStart(
            $this->viewMode === 'weekly'
              ? now()->startOfWeek()
              : now()->startOfMonth()
        );
        $this->loadPeriodDataAndTotals(); // This will now use the service
        $this->loadGlobalNotificationSetting();
    }

    /**
     * Toggles the visibility of the deviation columns and reloads data.
     */
    public function toggleDeviations(): void
    {
        $this->showDeviations = ! $this->showDeviations;
        $this->loadPeriodDataAndTotals(); // Reload data with new deviation visibility
    }

    /**
     * Switches the view between 'weekly' and 'monthly' modes and reloads data.
     *
     * @param  string  $mode  The desired view mode ('weekly' or 'monthly').
     */
    public function changeViewMode(string $mode): void
    {
        if (! in_array($mode, ['weekly', 'monthly'])) {
            $this->viewMode = 'weekly';

            return;
        }
        $this->viewMode = $mode;

        $this->setPeriodStart(
            $this->viewMode === 'weekly'
              ? now()->startOfWeek()
              : now()->startOfMonth()
        );
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
            'isGloballyEnabled' => $this->isGloballyEnabled,
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
     * Fetches and processes user data for the current period using UserDashboardDataProcessorService.
     */
    protected function loadPeriodDataAndTotals(): void
    {
        $startDate = $this->getPeriodStart();
        $endDate = $this->getPeriodEnd();

        // Resolve the service from the container
        $processor = app(UserDashboardDataProcessorService::class);

        // The user model is already loaded in $this->user during mount.
        // The service's GetDataForDateRange action will handle specific data needed.
        $processedOutput = $processor->generateProcessedData(
            $this->user,
            $startDate,
            $endDate,
            $this->showDeviations
        );

        $this->periodData = $processedOutput['periodData'];
        $this->dashboardTotals = $processedOutput['dashboardTotals'];
        $this->totalDeviationsDetails = $processedOutput['totalDeviationsDetails'];
    }

    /**
     * Updates the component's current date property.
     *
     * @param  Carbon  $date  The new start date.
     */
    protected function setPeriodStart(Carbon $date): void
    {
        $this->currentDate = $date->toDateString();
    }

    /**
     * Load global notification setting.
     */
    protected function loadGlobalNotificationSetting(): void
    {
        $this->isGloballyEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        );
    }
}
