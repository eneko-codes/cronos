<?php

namespace App\Enums;

enum SyncFrequencyType: string
{
    case Never = 'never';
    case EveryMinute = 'everyMinute';
    case EveryFiveMinutes = 'everyFiveMinutes';
    case EveryFifteenMinutes = 'everyFifteenMinutes';
    case EveryThirtyMinutes = 'everyThirtyMinutes';
    case Hourly = 'hourly';
    case EveryTwoHours = 'everyTwoHours';
    case EveryThreeHours = 'everyThreeHours';
    case EveryFourHours = 'everyFourHours';
    case EverySixHours = 'everySixHours';
    case EveryTwelveHours = 'everyTwelveHours';
    case DailyAt9 = 'dailyAt_9';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case TwiceMonthly = 'twiceMonthly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::EveryMinute => 'Every Minute',
            self::EveryFiveMinutes => 'Every 5 Minutes',
            self::EveryFifteenMinutes => 'Every 15 Minutes',
            self::EveryThirtyMinutes => 'Every 30 Minutes',
            self::Hourly => 'Every Hour',
            self::EveryTwoHours => 'Every Two Hours',
            self::EveryThreeHours => 'Every Three Hours',
            self::EveryFourHours => 'Every Four Hours',
            self::EverySixHours => 'Every Six Hours',
            self::EveryTwelveHours => 'Every Twelve Hours',
            self::DailyAt9 => 'Daily at 9:00',
            self::Daily => 'Daily at midnight',
            self::Weekly => 'Weekly on Sunday',
            self::TwiceMonthly => 'Twice Monthly (1st and 15th)',
            self::Monthly => 'Monthly',
        };
    }
}
