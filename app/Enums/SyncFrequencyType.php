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

}
