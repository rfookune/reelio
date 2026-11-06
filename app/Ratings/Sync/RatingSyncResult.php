<?php

namespace App\Ratings\Sync;

readonly class RatingSyncResult
{
    public function __construct(
        public RatingSyncOutcome $outcome,
        public int $imported = 0,
        public int $skipped = 0,
    ) {}
}
