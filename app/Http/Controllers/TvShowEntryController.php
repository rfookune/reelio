<?php

namespace App\Http\Controllers;

use App\Enums\Surface;
use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Support\Visitor\RateLimitKey;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Exceptions\CreationLimited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TvShowEntryController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, Catalog $catalog): RedirectResponse
    {
        $catalogId = filter_var($tmdbId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($catalogId === false, 404);
        try {
            $result = $catalog->findOrCreateTvShow($catalogId, RateLimitKey::for($request));
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex']);
        }
        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        abort_if($result->value === null, 404);

        return redirect($result->value->canonicalUrl(Surface::tvShowReferrerFrom($request->query('referrer'))), 301)->header('X-Robots-Tag', 'noindex');
    }
}
