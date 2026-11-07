<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $release_date
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable $last_viewed_at
 * @property CarbonImmutable|null $removed_at
 */
#[Fillable(['tmdb_id', 'slug', 'title', 'original_title', 'overview', 'tagline', 'release_date', 'runtime', 'poster_path', 'backdrop_path', 'original_language', 'imdb_id', 'synced_at', 'last_viewed_at', 'removed_at'])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'release_date' => 'immutable_date',
            'synced_at' => 'immutable_datetime',
            'last_viewed_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
            'runtime' => 'integer',
        ];
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /** @return HasMany<Availability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /** @return HasMany<UserMovie, $this> */
    public function userMovies(): HasMany
    {
        return $this->hasMany(UserMovie::class);
    }

    /** A Removed Movie: the Catalog source no longer has it, so none of its details are kept. */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function canonicalUrl(?string $referrer = null): string
    {
        return route('movies.show', ['slug' => $this->slug ?? 'movie', 'id' => $this->id, 'referrer' => $referrer]);
    }
}
