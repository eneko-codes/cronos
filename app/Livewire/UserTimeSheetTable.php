<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Traits\FormatsDurationsTrait;
use Livewire\Component;

class UserTimeSheetTable extends Component
{
    use FormatsDurationsTrait;

    public User $user;

    public string $currentDate;

    public string $viewMode;

    public bool $showDeviations;

    public array $periodData;

    public array $dashboardTotals;

    public ?array $totalDeviationsDetails; // Renamed from totalDeviationsForTable for consistency

    public bool $isNextPeriodDisabled;    // Renamed from isNextPeriodDisabledForTable

    public function mount(
        User $user,
        string $currentDate,
        string $viewMode,
        bool $showDeviations,
        array $periodData,
        array $dashboardTotals,
        ?array $totalDeviationsDetails,
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
