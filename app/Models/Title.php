<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable $last_viewed_at
 * @property CarbonImmutable|null $removed_at
 */
#[Fillable(['kind', 'tmdb_id', 'slug', 'title', 'original_title', 'overview', 'tagline', 'poster_path', 'backdrop_path', 'original_language', 'imdb_id', 'synced_at', 'last_viewed_at', 'removed_at'])]
class Title extends Model
{
    /** @use HasFactory<TitleFactory> */
    use HasFactory;

    protected $table = 'titles';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'synced_at' => 'immutable_datetime',
            'last_viewed_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_title', 'title_id');
    }

    /** @return HasMany<Availability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'title_id');
    }

    /** @return HasOne<MovieDetail, $this> */
    public function movieDetails(): HasOne
    {
        return $this->hasOne(MovieDetail::class, 'title_id');
    }

    /** @return HasOne<TvShowDetail, $this> */
    public function tvShowDetails(): HasOne
    {
        return $this->hasOne(TvShowDetail::class, 'title_id');
    }

    /** @return HasMany<Season, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class, 'title_id');
    }

    /** @return HasMany<Episode, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class, 'title_id');
    }

    /** @return HasMany<UserTitle, $this> */
    public function userTitles(): HasMany
    {
        return $this->hasMany(UserTitle::class, 'title_id');
    }

    /** A Removed Title: the Catalog source no longer has it, so none of its details are kept. */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /**
     * Whether the Title holds details from the Catalog source. A Removed Title holds none, and neither does one
     * cleared at TMDB's 6-month cap until a refresh restores it.
     */
    public function hasSourceDetails(): bool
    {
        return $this->synced_at !== null;
    }
}
