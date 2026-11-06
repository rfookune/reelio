<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StreamingServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform where a Movie can be watched, keyed by TMDB's id for it.
 *
 * @property CarbonImmutable $fetched_at
 */
#[Fillable(['id', 'name', 'logo_path', 'fetched_at'])]
class StreamingService extends Model
{
    /** @use HasFactory<StreamingServiceFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fetched_at' => 'immutable_datetime'];
    }
}
