<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class DeviationData
{
    public function __construct(
        public int $percentage = 0,
        public int $differenceMinutes = 0,
        public string $tooltip = '',
        public bool $shouldDisplay = false,
    ) {}

    public function getFormattedPercentage(): string
    {
        if (! $this->shouldDisplay) {
            return '';
        }

        $sign = $this->percentage > 0 ? '+' : '';

        return "{$sign}{$this->percentage}%";
    }

    public function getBackgroundClass(): string
    {
        if (! $this->shouldDisplay) {
            return 'bg-white dark:bg-gray-800';
        }

        if ($this->percentage > 0) {
            return 'bg-green-50 dark:bg-green-900/30';
        } elseif ($this->percentage <= -50) {
            return 'bg-red-50 dark:bg-red-900/30';
        } elseif ($this->percentage < 0) {
            return 'bg-yellow-50 dark:bg-yellow-900/30';
        }

        return 'bg-white dark:bg-gray-800';
    }

    public function getTextClass(): string
    {
        if (! $this->shouldDisplay) {
            return 'text-transparent';
        }

        if ($this->percentage > 0) {
            return 'text-green-600 dark:text-green-600';
        } elseif ($this->percentage <= -50) {
            return 'text-red-600 dark:text-red-600';
        } elseif ($this->percentage < 0) {
            return 'text-yellow-500 dark:text-yellow-500';
        }

        return 'text-gray-700 dark:text-gray-300';
    }
}
