<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class PeriodNavigator extends Component
{
    #[Url]
    public string $currentDate;

    #[Url]
    public string $viewMode = 'weekly';

    public function mount(?string $currentDate = null): void
    {
        $this->currentDate = $currentDate ?? now()->toDateString();
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

    #[Computed]
    public function periodTitle(): string
    {
        $date = Carbon::parse($this->currentDate);
        $periodType = $this->viewMode === 'weekly' ? 'Week' : 'Month';
        
        return "{$periodType} of {$date->translatedFormat('F d, Y')}";
    }

    public function previousPeriod(): void
    {
        $startDate = $this->periodStart;
        $this->currentDate = $this->viewMode === 'weekly' 
            ? $startDate->subWeek()->toDateString()
            : $startDate->subMonth()->toDateString();
    }

    public function nextPeriod(): void
    {
        $startDate = $this->periodStart;
        $this->currentDate = $this->viewMode === 'weekly' 
            ? $startDate->addWeek()->toDateString()
            : $startDate->addMonth()->toDateString();
    }

    public function changeViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.period-navigator');
    }
}
