<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Enums\SectionKind;

/**
 * One Discover section as it was last built, in rank order.
 */
readonly class DiscoverSection
{
    /** @param  list<DiscoverMovie>  $movies */
    public function __construct(
        public SectionKind $kind,
        public int $builtAt,
        public array $movies,
    ) {}

    /**
     * The section without the Movies an earlier section has.
     *
     * @param  array<int, true>  $seen  catalog ids
     */
    public function without(array $seen): self
    {
        return new self($this->kind, $this->builtAt, array_values(array_filter($this->movies, fn (DiscoverMovie $movie): bool => ! isset($seen[$movie->tmdbId]))));
    }

    /**
     * Cache-safe form: the cache store does not unserialize objects.
     *
     * @return array{kind: string, builtAt: int, movies: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'builtAt' => $this->builtAt, 'movies' => array_map(fn (DiscoverMovie $movie): array => $movie->toArray(), $this->movies)];
    }

    /** The section from its cached form, or null when that form no longer fits; a Movie that no longer fits is left out. */
    public static function fromArray(mixed $data): ?self
    {
        $kind = is_array($data) && is_string($data['kind'] ?? null) ? SectionKind::tryFrom($data['kind']) : null;

        if ($kind === null || ! is_int($data['builtAt'] ?? null) || ! is_array($data['movies'] ?? null)) {
            return null;
        }

        return new self($kind, $data['builtAt'], array_values(array_filter(array_map(DiscoverMovie::fromArray(...), $data['movies']))));
    }
}
