<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Cache;

/**
 * Tells the owner when the Catalog source rejects our credentials, a configuration error that retrying won't fix.
 */
class MisconfigurationReporter
{
    public function __construct(private CacheKey $keys) {}

    /** Reports a rejected token at most once every five minutes; any other failure is left alone. */
    public function reportIfMisconfigured(SourceUnavailable $exception, CatalogContext $context): void
    {
        if ($exception instanceof SourceMisconfigured && Cache::add($this->keys->build('misconfigured-reported', [], $context), true, 300)) {
            report($exception);
        }
    }
}
