<?php

namespace App\Models;

use App\Catalog\Enums\OfferType;
use Database\Factories\AvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Streaming service offering a Movie in one country, in one way.
 *
 * @property OfferType $offer_type
 */
#[Fillable(['movie_id', 'streaming_service_id', 'country', 'offer_type', 'display_priority'])]
class Availability extends Model
{
    /** @use HasFactory<AvailabilityFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['offer_type' => OfferType::class, 'display_priority' => 'integer'];
    }

    /** @return BelongsTo<StreamingService, $this> */
    public function streamingService(): BelongsTo
    {
        return $this->belongsTo(StreamingService::class);
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
