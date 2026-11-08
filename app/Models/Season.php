<?php

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title_id', 'tmdb_id', 'season_number', 'name', 'overview', 'poster_path', 'air_date', 'episode_count', 'removed_at'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    /** @param Builder<Season> $query */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN season_number = 0 THEN 1 ELSE 0 END')->orderBy('season_number');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['air_date' => 'immutable_date', 'removed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Title, $this> */
    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    /** @return HasMany<Episode, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }
}
