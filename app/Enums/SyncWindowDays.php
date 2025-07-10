<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncWindowDays: int
{
    case Thirty = 30;
    case TwentyNine = 29;
    case TwentyEight = 28;
    case TwentySeven = 27;
    case TwentySix = 26;
    case TwentyFive = 25;
    case TwentyFour = 24;
    case TwentyThree = 23;
    case TwentyTwo = 22;
    case TwentyOne = 21;
    case Twenty = 20;
    case Nineteen = 19;
    case Eighteen = 18;
    case Seventeen = 17;
    case Sixteen = 16;
    case Fifteen = 15;
    case Fourteen = 14;
    case Thirteen = 13;
    case Twelve = 12;
    case Eleven = 11;
    case Ten = 10;
    case Nine = 9;
    case Eight = 8;
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
            self::Thirty => '30 days',
            self::TwentyNine => '29 days',
            self::TwentyEight => '28 days',
            self::TwentySeven => '27 days',
            self::TwentySix => '26 days',
            self::TwentyFive => '25 days',
            self::TwentyFour => '24 days',
            self::TwentyThree => '23 days',
            self::TwentyTwo => '22 days',
            self::TwentyOne => '21 days',
            self::Twenty => '20 days',
            self::Nineteen => '19 days',
            self::Eighteen => '18 days',
            self::Seventeen => '17 days',
            self::Sixteen => '16 days',
            self::Fifteen => '15 days',
            self::Fourteen => '14 days',
            self::Thirteen => '13 days',
            self::Twelve => '12 days',
            self::Eleven => '11 days',
            self::Ten => '10 days',
            self::Nine => '9 days',
            self::Eight => '8 days',
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
