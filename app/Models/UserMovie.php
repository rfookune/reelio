<?php

namespace App\Models;

use Database\Factories\UserMovieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMovie extends UserTitle
{
    /** @use HasFactory<UserMovieFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('movie', fn (Builder $query) => $query->whereHas('movie'));
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'title_id');
    }
}
