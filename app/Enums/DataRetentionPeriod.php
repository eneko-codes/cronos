<?php

declare(strict_types=1);

namespace App\Enums;

enum DataRetentionPeriod: int
{
    case Disabled = 0;
    case ThirtyDays = 30;
    case ThreeMonths = 90;
    case SixMonths = 180;
    case OneYear = 365;
    case TwoYears = 730;
    case ThreeYears = 1095;
    case FiveYears = 1825;

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::ThirtyDays => '30 days',
            self::ThreeMonths => '3 months',
            self::SixMonths => '6 months',
            self::OneYear => '1 year',
            self::TwoYears => '2 years',
            self::ThreeYears => '3 years',
            self::FiveYears => '5 years',
        };
    }
}
