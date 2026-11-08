<?php

namespace App\Services\Catalog\Maintenance;

use App\Models\Genre;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\SourceUnavailable;

class GenreRefresh
{
    public function __construct(private CatalogSource $source, private MisconfigurationReporter $misconfiguration) {}

    /**
     * Brings the stored genres up to date with the source's list: renamed ones get their new name and new ones are
     * added. A genre the source no longer lists is kept with its Movie links; each Movie's refresh replaces those.
     * When the source fails, the stored genres stay as they are.
     */
    public function refreshGenres(CatalogProfile $profile): void
    {
        $context = new CatalogContext(config('catalog.language'), profile: $profile);

        try {
            $genres = $this->source->genres($context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return;
        }

        Genre::query()->upsert($genres, ['id'], ['name']);
    }
}
