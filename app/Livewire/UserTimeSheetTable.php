<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\OverallDeviationDetails;
use App\DataTransferObjects\PeriodDayData;
use App\Models\User;
use App\Traits\FormatsDurationsTrait;
use Illuminate\Support\Collection;
use Livewire\Component;

class UserTimeSheetTable extends Component
{
    use FormatsDurationsTrait;

    public User $user;

    public string $currentDate;

    public string $viewMode;

    public bool $showDeviations;

    /** @var Collection<string, PeriodDayData> */
    public Collection $periodData;

    public DashboardTotals $dashboardTotals;

    public ?OverallDeviationDetails $totalDeviationsDetails;

    public bool $isNextPeriodDisabled;

    public function mount(
        User $user,
        string $currentDate,
        string $viewMode,
        bool $showDeviations,
        Collection $periodData,
        DashboardTotals $dashboardTotals,
        ?OverallDeviationDetails $totalDeviationsDetails,
        bool $isNextPeriodDisabled
    ): void {
        $this->user = $user;
        $this->currentDate = $currentDate;
        $this->viewMode = $viewMode;
        $this->showDeviations = $showDeviations;
        $this->periodData = $periodData;
        $this->dashboardTotals = $dashboardTotals;
        $this->totalDeviationsDetails = $totalDeviationsDetails;
        $this->isNextPeriodDisabled = $isNextPeriodDisabled;
    }

    public function dispatchPreviousPeriod(): void
    {
        $this->dispatch('timeSheetPreviousPeriod');
    }

    public function dispatchNextPeriod(): void
    {
        $this->dispatch('timeSheetNextPeriod');
    }

    public function dispatchToggleDeviations(): void
    {
        $this->dispatch('timeSheetToggleDeviations');
    }

    public function dispatchChangeViewMode(string $mode): void
    {
        $this->dispatch('timeSheetChangeViewMode', mode: $mode);
    }

    public function render()
    {
        return view('livewire.user-time-sheet-table');
    }
}
