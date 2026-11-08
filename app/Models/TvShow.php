<?php

namespace App\Models;

use Database\Factories\TvShowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TvShow extends Title
{
    /** @use HasFactory<TvShowFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('kind', fn (Builder $query) => $query->where($query->qualifyColumn('kind'), 'tv'));
        static::creating(function (self $title): void {
            $title->kind = 'tv';
        });
        static::created(fn (self $title) => $title->details()->create());
    }

    /** @return HasOne<TvShowDetail, $this> */
    public function details(): HasOne
    {
        return $this->hasOne(TvShowDetail::class, 'title_id');
    }
}
