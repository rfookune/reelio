<?php

namespace App\Services\Ratings\Sync\Datasets;

use App\Services\Ratings\Sync\RatingSyncFailed;

use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingRow;

/**
 * IMDb's non-commercial `title.ratings.tsv.gz`: a gzipped TSV of IMDb id, average rating and vote count.
 */
class ImdbRatingDataset implements RatingDataset
{
    private const EXPECTED_HEADER = "tconst\taverageRating\tnumVotes";

    public function source(): RatingSourceName
    {
        return RatingSourceName::Imdb;
    }

    public function url(): string
    {
        return config('ratings.sources.imdb.dataset_url');
    }

    public function rows(string $path): iterable
    {
        if (file_get_contents($path, length: 2) !== "\x1f\x8b") {
            throw new RatingSyncFailed('The IMDb dataset is not gzipped.');
        }

        $stream = @gzopen($path, 'rb');

        if ($stream === false) {
            throw new RatingSyncFailed('The IMDb dataset could not be opened.');
        }

        try {
            $header = gzgets($stream);

            if ($header === false || rtrim($header, "\r\n") !== self::EXPECTED_HEADER) {
                throw new RatingSyncFailed('The IMDb dataset header does not match.');
            }

            while (($line = gzgets($stream)) !== false) {
                yield $this->parse(rtrim($line, "\r\n"));
            }

            if (! gzeof($stream)) {
                throw new RatingSyncFailed('The IMDb dataset is corrupt.');
            }
        } finally {
            gzclose($stream);
        }
    }

    /** A missing rating (`\N`) or a malformed line reads as null. */
    private function parse(string $line): ?RatingRow
    {
        $fields = explode("\t", $line);

        if (count($fields) !== 3) {
            return null;
        }

        [$imdbId, $rating, $votes] = $fields;

        if (preg_match('/^tt\d{1,12}$/D', $imdbId) !== 1 || ! is_numeric($rating) || ! ctype_digit($votes) || strlen($votes) > 10) {
            return null;
        }

        return RatingRow::validated($imdbId, (float) $rating, (int) $votes);
    }
}
