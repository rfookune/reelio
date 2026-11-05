<?php

namespace App\Catalog;

/**
 * What the Catalog knows right now: fresh from the source or cache, stale because the source failed,
 * or unavailable. A `null` value with a fresh status is the source's answer that the thing doesn't exist.
 *
 * @template TValue
 */
readonly class CatalogResult
{
    /** @param TValue|null $value */
    private function __construct(public CatalogStatus $status, public mixed $value) {}

    /**
     * @template T
     *
     * @param  T|null  $value
     * @return self<T>
     */
    public static function fresh(mixed $value): self
    {
        return new self(CatalogStatus::Fresh, $value);
    }

    /**
     * @template T
     *
     * @param  T  $value
     * @return self<T>
     */
    public static function stale(mixed $value): self
    {
        return new self(CatalogStatus::Stale, $value);
    }

    /** @return self<never> */
    public static function unavailable(): self
    {
        return new self(CatalogStatus::Unavailable, null);
    }

    public function isUnavailable(): bool
    {
        return $this->status === CatalogStatus::Unavailable;
    }
}
