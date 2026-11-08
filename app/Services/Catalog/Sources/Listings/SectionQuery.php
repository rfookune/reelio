<?php

namespace App\Services\Catalog\Sources\Listings;

use App\Services\Catalog\Enums\SectionKind;
use InvalidArgumentException;

/**
 * Which list a Discover section reads from the Catalog source: a fixed list, Coming soon's window of US release
 * dates, or one genre's popular Movies above a vote floor.
 */
readonly class SectionQuery
{
    private function __construct(
        public SectionKind $kind,
        public ?int $genreId = null,
        public ?int $minimumVotes = null,
        public ?string $releasedFrom = null,
        public ?string $releasedUntil = null,
    ) {}

    /** A fixed list: Trending, Popular or Top rated. */
    public static function of(SectionKind $kind): self
    {
        if (in_array($kind, [SectionKind::ComingSoon, SectionKind::Genre], true)) {
            throw new InvalidArgumentException("The {$kind->value} list needs its own parameters.");
        }

        return new self($kind);
    }

    /** Movies with a US theatrical or digital release between the two dates (Y-m-d), both included. */
    public static function comingSoon(string $releasedFrom, string $releasedUntil): self
    {
        return new self(SectionKind::ComingSoon, releasedFrom: $releasedFrom, releasedUntil: $releasedUntil);
    }

    /** One genre's Movies by popularity, each with at least the given number of votes. */
    public static function genre(int $genreId, int $minimumVotes): self
    {
        return new self(SectionKind::Genre, genreId: $genreId, minimumVotes: $minimumVotes);
    }

    /** Names the list, whatever its dates, as `DiscoverSection::key()` names its section. */
    public function key(): string
    {
        return $this->kind === SectionKind::Genre ? "genre-{$this->genreId}" : $this->kind->value;
    }
}
