<?php

namespace App\Http\Support;

use App\Http\Exceptions\DetailsUnavailable;
use App\Models\TvShow;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Exceptions\CreationLimited;
use Illuminate\Http\Request;

class TvShowByCatalogId
{
    public function __construct(private Catalog $catalog) {}

    public function catalogId(string $tmdbId): int
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $id = filter_var($tmdbId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }

    public function find(string $tmdbId): ?TvShow
    {
        return TvShow::query()->where('tmdb_id', $this->catalogId($tmdbId))->first();
    }

    public function findOrCreate(Request $request, string $tmdbId): TvShow
    {
        try {
            $result = $this->catalog->findOrCreateTvShow($this->catalogId($tmdbId), RateLimitKey::for($request));
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex']);
        }
        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        abort_if($result->value === null, 404);

        return $result->value;
    }
}
