<?php

namespace App\Models;

use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title_id', 'season_id', 'tmdb_id', 'season_number', 'episode_number', 'name', 'overview', 'air_date', 'runtime', 'episode_type', 'removed_at'])]
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

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

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
