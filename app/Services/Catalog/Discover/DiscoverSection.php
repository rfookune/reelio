<?php

namespace App\Services\Catalog\Discover;

use App\Services\Catalog\Enums\SectionKind;
use Closure;

/**
 * One Discover section as it was last built, in rank order. A genre collection carries its genre.
 */
readonly class DiscoverSection
{
    /**
     * @param  list<DiscoverMovie>  $movies
     * @param  array{id: int, name: string}|null  $genre
     */
    public function __construct(
        public SectionKind $kind,
        public int $builtAt,
        public array $movies,
        public ?array $genre = null,
    ) {}

    /** Names the section within a payload and on the page: its kind, or its genre for a genre collection. */
    public function key(): string
    {
        return $this->genre === null ? $this->kind->value : "genre-{$this->genre['id']}";
    }

    public function title(): string
    {
        return $this->genre['name'] ?? $this->kind->label();
    }

    /**
     * The section without the Movies an earlier section has.
     *
     * @param  array<int, true>  $seen  catalog ids
     */
    public function without(array $seen): self
    {
        return $this->filter(fn (DiscoverMovie $movie): bool => ! isset($seen[$movie->tmdbId]));
    }

    /** @param  Closure(DiscoverMovie): bool  $keep */
    public function filter(Closure $keep): self
    {
        return new self($this->kind, $this->builtAt, array_values(array_filter($this->movies, $keep)), $this->genre);
    }

    /**
     * Cache-safe form: the cache store does not unserialize objects.
     *
     * @return array{kind: string, builtAt: int, movies: list<array<string, mixed>>, genre: array{id: int, name: string}|null}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'builtAt' => $this->builtAt, 'movies' => array_map(fn (DiscoverMovie $movie): array => $movie->toArray(), $this->movies), 'genre' => $this->genre];
    }

    /** The section from its cached form, or null when that form no longer fits; a Movie that no longer fits is left out. */
    public static function fromArray(mixed $data): ?self
    {
        $kind = is_array($data) && is_string($data['kind'] ?? null) ? SectionKind::tryFrom($data['kind']) : null;

        if ($kind === null || ! is_int($data['builtAt'] ?? null) || ! is_array($data['movies'] ?? null)) {
            return null;
        }

        $genre = $data['genre'] ?? null;
        $genre = is_array($genre) && is_int($genre['id'] ?? null) && is_string($genre['name'] ?? null) ? ['id' => $genre['id'], 'name' => $genre['name']] : null;

        if (($kind === SectionKind::Genre) !== ($genre !== null)) {
            return null;
        }

        return new self($kind, $data['builtAt'], array_values(array_filter(array_map(DiscoverMovie::fromArray(...), $data['movies']))), $genre);
    }
}
