<?php

namespace App\Ratings\Imdb;

readonly class ImdbImportResult
{
    public function __construct(
        public ImdbImportOutcome $outcome,
        public int $imported = 0,
        public int $skipped = 0,
    ) {}
}
