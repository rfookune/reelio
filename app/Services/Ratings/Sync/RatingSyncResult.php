<?php

namespace App\Services\Ratings\Sync;

use App\Services\Ratings\Sync\Enums\RatingSyncOutcome;

readonly class RatingSyncResult
{
    public function __construct(
        public RatingSyncOutcome $outcome,
        public int $imported = 0,
        public int $skipped = 0,
    ) {}
}
