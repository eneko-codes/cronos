<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Notification retention period options.
 *
 * Defines how long notifications should be kept in the database
 * before being automatically pruned.
 */
enum NotificationRetentionPeriod: int
{
    case SevenDays = 7;
    case FourteenDays = 14;
    case ThirtyDays = 30;
    case SixtyDays = 60;
    case NinetyDays = 90;
    case SixMonths = 180;
    case OneYear = 365;

    public function label(): string
    {
        return match ($this) {
            self::SevenDays => '7 days',
            self::FourteenDays => '14 days',
            self::ThirtyDays => '30 days',
            self::SixtyDays => '60 days',
            self::NinetyDays => '90 days',
            self::SixMonths => '6 months',
            self::OneYear => '1 year',
        };
    }
}
