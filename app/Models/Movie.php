<?php

namespace App\Models;

use App\Enums\Surface;
use App\Enums\TitleKind;
use Carbon\CarbonImmutable;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $release_date
 * @property int|null $runtime
 */
#[Fillable(['kind', 'tmdb_id', 'slug', 'title', 'original_title', 'overview', 'tagline', 'release_date', 'runtime', 'poster_path', 'backdrop_path', 'original_language', 'imdb_id', 'synced_at', 'last_viewed_at', 'removed_at'])]
class Movie extends Title
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    protected $with = ['details'];

    protected static function booted(): void
    {
        static::addGlobalScope('kind', fn (Builder $query) => $query->where($query->qualifyColumn('kind'), TitleKind::Movie));
        static::creating(function (self $title): void {
            $title->kind = TitleKind::Movie;
        });
    }

    /** @return HasOne<MovieDetail, $this> */
    public function details(): HasOne
    {
        return $this->hasOne(MovieDetail::class, 'title_id')->withDefault();
    }

    /** @return Attribute<CarbonImmutable|null, string|CarbonImmutable|null> */
    protected function releaseDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CarbonImmutable => $this->details->release_date,
            set: function (string|CarbonImmutable|null $value): array {
                $this->details->release_date = $value;

                return [];
            },
        );
    }

    /** @return Attribute<int|null, int|null> */
    protected function runtime(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->details->runtime,
            set: function (?int $value): array {
                $this->details->runtime = $value;

                return [];
            },
        );
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            if (! parent::save($options)) {
                return false;
            }

            if (! $this->details->exists || $this->details->isDirty()) {
                $this->details()->save($this->details);
            }

            return true;
        });
    }

    /** @return HasMany<UserMovie, $this> */
    public function userMovies(): HasMany
    {
        return $this->hasMany(UserMovie::class, 'title_id');
    }

    public function canonicalUrl(?Surface $referrer = null): string
    {
        return route('movies.show', ['slug' => $this->slug ?? 'movie', 'id' => $this->id, 'referrer' => $referrer?->value]);
    }
}
