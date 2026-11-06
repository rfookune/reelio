<?php

namespace App\Ratings;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Owns the `ratings` table. A score that is missing, out of range or has no votes is never stored, so an
 * absent rating is simply an absent row.
 */
class RatingStore
{
    private const IMPORT_TABLE = 'rating_import';

    private const INSERT_BATCH_SIZE = 2000;

    private const MINIMUM_SHARE_OF_LIVE_ROWS = 0.9;

    /**
     * Records one score, or removes the source's row for the title when the score isn't showable. A switched-off
     * source records nothing, so its rows are left as they were.
     */
    public function record(RatingSourceName $source, string $sourceId, ?float $value, ?int $voteCount): void
    {
        if (! $source->isEnabled()) {
            return;
        }

        $row = RatingRow::validated($sourceId, $value, $voteCount);

        if ($row === null) {
            DB::table('ratings')->where('source', $source->value)->where('source_id', $sourceId)->delete();

            return;
        }

        DB::table('ratings')->upsert(
            [['source' => $source->value, 'source_id' => $row->sourceId, 'value' => $row->value, 'vote_count' => $row->voteCount, 'as_of' => now()]],
            ['source', 'source_id'],
            ['value', 'vote_count', 'as_of'],
        );
    }

    /**
     * Replaces every row of a source with the given rows, in one transaction: they stream into a temporary table
     * that lives only for that transaction, are checked (at least 90% of the source's live rows must be valid),
     * then replace the source's rows. Readers see the old rows until the commit, and a refusal rolls everything
     * back. Keeping it all in one transaction also keeps it on one database connection behind a pooler.
     * Unreadable rows (null) and repeated title ids are skipped and counted.
     *
     * @param  iterable<RatingRow|null>  $rows
     * @return array{imported: int, skipped: int}
     *
     * @throws RatingReplacementRefused
     */
    public function replaceSource(RatingSourceName $source, iterable $rows): array
    {
        try {
            return DB::transaction(function () use ($source, $rows): array {
                $this->createImportTable();
                $counts = $this->load($rows);
                $live = DB::table('ratings')->where('source', $source->value)->count();

                if ($counts['imported'] === 0 || $counts['imported'] < $live * self::MINIMUM_SHARE_OF_LIVE_ROWS) {
                    throw new RatingReplacementRefused("The {$source->label()} dataset has {$counts['imported']} valid rows against {$live} live rows.");
                }

                DB::table('ratings')->where('source', $source->value)->delete();
                DB::statement(
                    'INSERT INTO ratings (source, source_id, value, vote_count, as_of) SELECT ?, source_id, value, vote_count, ? FROM '.self::IMPORT_TABLE,
                    [$source->value, now()->toDateTimeString()],
                );

                return $counts;
            });
        } catch (QueryException $exception) {
            throw new RatingReplacementRefused("The {$source->label()} dataset could not be stored.", previous: $exception);
        }
    }

    public function hasRows(RatingSourceName $source): bool
    {
        return DB::table('ratings')->where('source', $source->value)->exists();
    }

    /** When the source's newest stored row was written. */
    public function latestAsOf(RatingSourceName $source): ?CarbonImmutable
    {
        $asOf = DB::table('ratings')->where('source', $source->value)->max('as_of');

        return $asOf === null ? null : CarbonImmutable::parse($asOf);
    }

    /**
     * @param  iterable<RatingRow|null>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function load(iterable $rows): array
    {
        $batch = [];
        $read = 0;
        $imported = 0;

        foreach ($rows as $row) {
            $read++;

            if ($row === null) {
                continue;
            }

            $batch[] = ['source_id' => $row->sourceId, 'value' => $row->value, 'vote_count' => $row->voteCount];

            if (count($batch) === self::INSERT_BATCH_SIZE) {
                $imported += DB::table(self::IMPORT_TABLE)->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $imported += DB::table(self::IMPORT_TABLE)->insertOrIgnore($batch);
        }

        return ['imported' => $imported, 'skipped' => $read - $imported];
    }

    /**
     * Dropped when the transaction commits. Inside an outer transaction (as in tests) it can outlive one call, so
     * it is created only if missing and emptied.
     */
    private function createImportTable(): void
    {
        DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS '.self::IMPORT_TABLE.' (source_id varchar(255) PRIMARY KEY, value numeric(5, 3) NOT NULL, vote_count integer NOT NULL) ON COMMIT DROP');
        DB::statement('TRUNCATE '.self::IMPORT_TABLE);
    }
}
