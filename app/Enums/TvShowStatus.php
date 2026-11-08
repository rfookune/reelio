<?php

namespace App\Enums;

enum TvShowStatus: string
{
    case ReturningSeries = 'Returning Series';
    case InProduction = 'In Production';
    case Pilot = 'Pilot';
    case Ended = 'Ended';
    case Canceled = 'Canceled';

    /** @return list<string> */
    public static function airing(): array
    {
        return [
            self::ReturningSeries->value,
            self::InProduction->value,
            self::Pilot->value,
        ];
    }
}
