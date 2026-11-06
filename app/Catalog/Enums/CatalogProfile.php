<?php

namespace App\Catalog\Enums;

/**
 * How a Catalog source call is made: a visitor is waiting (Interactive) or a job is running (Background).
 */
enum CatalogProfile
{
    case Interactive;
    case Background;

    public function timeoutSeconds(): int
    {
        return match ($this) {
            self::Interactive => 3,
            self::Background => 10,
        };
    }

    public function attempts(): int
    {
        return match ($this) {
            self::Interactive => 2,
            self::Background => 3,
        };
    }
}
