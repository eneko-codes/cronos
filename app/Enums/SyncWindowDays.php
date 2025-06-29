<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncWindowDays: int
{
    case Seven = 7;
    case Six = 6;
    case Five = 5;
    case Four = 4;
    case Three = 3;
    case Two = 2;
    case One = 1;

    public function label(): string
    {
        return match ($this) {
            self::Seven => '7 days',
            self::Six => '6 days',
            self::Five => '5 days',
            self::Four => '4 days',
            self::Three => '3 days',
            self::Two => '2 days',
            self::One => '1 day',
        };
    }
}
