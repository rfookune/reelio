<?php

namespace App\Services\Ratings\Sync\Datasets;

use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingRow;
use App\Services\Ratings\Sync\RatingSyncFailed;

/**
 * A Rating source that publishes its scores as one downloadable file. The feed knows only where the file is
 * and how to read it; downloading, validation and the swap are shared by every dataset.
 */
interface RatingDataset
{
    public function source(): RatingSourceName;

    public function url(): string;

    /**
     * Reads the downloaded file. A row that can't be read is yielded as null so it is skipped and counted.
     *
     * @return iterable<RatingRow|null>
     *
     * @throws RatingSyncFailed when the file itself is unusable
     */
    public function rows(string $path): iterable;
}
