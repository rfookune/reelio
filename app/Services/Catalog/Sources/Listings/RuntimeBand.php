<?php

namespace App\Services\Catalog\Sources\Listings;

enum RuntimeBand: string
{
    case UnderNinety = 'under_90';
    case NinetyToOneTwenty = '90_to_120';
    case OverOneTwenty = 'over_120';

    /** @return array{minimum: ?int, maximum: ?int} */
    public function minuteBounds(): array
    {
        return match ($this) {
            self::UnderNinety => ['minimum' => null, 'maximum' => 89],
            self::NinetyToOneTwenty => ['minimum' => 90, 'maximum' => 120],
            self::OverOneTwenty => ['minimum' => 121, 'maximum' => null],
        };
    }
}
