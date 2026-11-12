<?php

namespace App\Services\Catalog\Sources\Listings;

enum Era: string
{
    case TwentyTwenties = '2020s';
    case TwentyTens = '2010s';
    case TwoThousands = '2000s';
    case NineteenNineties = '1990s';
    case Older = 'older';

    /** @return array{from: ?string, until: string} */
    public function dateRange(): array
    {
        return match ($this) {
            self::TwentyTwenties => ['from' => '2020-01-01', 'until' => '2029-12-31'],
            self::TwentyTens => ['from' => '2010-01-01', 'until' => '2019-12-31'],
            self::TwoThousands => ['from' => '2000-01-01', 'until' => '2009-12-31'],
            self::NineteenNineties => ['from' => '1990-01-01', 'until' => '1999-12-31'],
            self::Older => ['from' => null, 'until' => '1989-12-31'],
        };
    }
}
