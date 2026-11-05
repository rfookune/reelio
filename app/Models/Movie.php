<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable|null $release_date
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable $last_viewed_at
 * @property CarbonImmutable|null $removed_at
 */
#[Fillable(['tmdb_id', 'slug', 'title', 'original_title', 'overview', 'tagline', 'release_date', 'runtime', 'poster_path', 'backdrop_path', 'original_language', 'vote_average', 'vote_count', 'imdb_id', 'synced_at', 'last_viewed_at', 'removed_at'])]
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
            'vote_average' => 'float',
            'vote_count' => 'integer',
        ];
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function canonicalUrl(?string $referrer = null): string
    {
        return route('movies.show', ['slug' => $this->slug ?? 'movie', 'id' => $this->id, 'referrer' => $referrer]);
    }
}
