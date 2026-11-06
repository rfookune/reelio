<?php

namespace App\Catalog;

/**
 * How a Streaming service offers a Movie in a country. Case order is tab order.
 */
enum OfferType: string
{
    case Stream = 'stream';
    case Free = 'free';
    case Rent = 'rent';
    case Buy = 'buy';

    public function label(): string
    {
        return match ($this) {
            self::Stream => 'Stream',
            self::Free => 'Free',
            self::Rent => 'Rent',
            self::Buy => 'Buy',
        };
    }

    /** Shown with the offer type's list, when it needs explaining. */
    public function caption(): ?string
    {
        return match ($this) {
            self::Free => 'Free, may include ads',
            default => null,
        };
    }
}
