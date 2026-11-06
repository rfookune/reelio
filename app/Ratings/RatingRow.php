<?php

namespace App\Ratings;

/**
 * One title's score from one Rating source, before it is stored.
 */
readonly class RatingRow
{
    /** The largest value Postgres's `integer` column holds. */
    private const MAXIMUM_VOTE_COUNT = 2_147_483_647;

    private function __construct(public string $sourceId, public float $value, public int $voteCount) {}

    /** A showable score: an identifier, a value from 0 to 10 and at least one vote. Anything else is null. */
    public static function validated(string $sourceId, ?float $value, ?int $voteCount): ?self
    {
        if ($sourceId === '' || strlen($sourceId) > 255
            || $value === null || $value < 0 || $value > 10
            || $voteCount === null || $voteCount < 1 || $voteCount > self::MAXIMUM_VOTE_COUNT) {
            return null;
        }

        return new self($sourceId, $value, $voteCount);
    }
}
