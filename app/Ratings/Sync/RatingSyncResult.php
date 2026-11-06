<?php

namespace App\Ratings\Sync;

use App\Ratings\Enums\RatingSyncOutcome;

readonly class RatingSyncResult
{
    public function __construct(
        public RatingSyncOutcome $outcome,
        public int $imported = 0,
        public int $skipped = 0,
    ) {}
}
