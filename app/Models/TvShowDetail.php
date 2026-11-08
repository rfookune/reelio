<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_air_date', 'last_air_date', 'status', 'season_count', 'episode_count'])]
class TvShowDetail extends Model
{
    protected $primaryKey = 'title_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['first_air_date' => 'immutable_date', 'last_air_date' => 'immutable_date'];
    }

    /** @return BelongsTo<Title, $this> */
    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
