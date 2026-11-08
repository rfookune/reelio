<?php

namespace App\Http\Controllers;

use App\Enums\TitleKind;
use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\MoviePageDetails;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TitleCastController extends Controller
{
    /**
     * A Title's whole billed cast and crew, for a Movie or a TV Show, whose routes set the `kind`. For a TV Show it is
     * the series-wide cast. Like the Title's own page, a stale slug redirects, a Removed Title is a 410 and the
     * details are refreshed when due, but no view is recorded: the visitor came from that page. A Title without
     * details is refreshed before its slug is checked, and is a 503 while that fails. A TV Show is a 404 with the
     * TV flag off.
     */
    public function __invoke(Request $request, string $slug, string $id, string $kind, Catalog $catalog): Response|RedirectResponse
    {
        $kind = TitleKind::from($kind);
        abort_if($kind === TitleKind::TvShow && ! config('catalog.tv_shows.enabled'), 404);
        $title = $catalog->findTitle($id);
        abort_unless($kind === TitleKind::Movie ? $title instanceof Movie : $title instanceof TvShow, 404);
        throw_if($title->isRemoved(), TitleRemoved::class, $title, $request->user());

        $credits = $title->hasSourceDetails() ? null : $this->credits($catalog, $title, $request->user());
        $routeName = $kind === TitleKind::Movie ? 'movies.cast' : 'tv-shows.cast';
        $routeParameters = ['slug' => $title->slug ?? $kind->defaultSlug(), 'id' => $title->id];

        if ($slug !== $routeParameters['slug']) {
            return redirect()->route($routeName, $routeParameters, 301);
        }

        $credits ??= $this->credits($catalog, $title, $request->user());

        return Inertia::render('titles/Cast', [
            'kind' => $kind->value,
            'imageBaseUrl' => config('catalog.image_base_url'),
            'canonicalUrl' => route($routeName, $routeParameters),
            'titleUrl' => $title->canonicalUrl(),
            'title' => [
                'name' => $title->title,
                'year' => ($title instanceof Movie ? $title->release_date : $title->details->first_air_date)?->year,
                ...$credits,
            ],
        ]);
    }

    /**
     * The Title's cast and crew, refreshing its details when due, which makes the page a 410 when the Title proves
     * removed and a 503 while it has no details. `detailsUnavailable` is set when the stored details have no cast to
     * show, as when the source is down and nothing is cached.
     *
     * @return array{cast: list<array<string, mixed>>, crew: list<array<string, mixed>>, detailsUnavailable: bool}
     */
    private function credits(Catalog $catalog, Movie|TvShow $title, ?User $user): array
    {
        if ($title instanceof Movie) {
            $result = (new MoviePageDetails($catalog, $title, $user))->result();

            return ['cast' => $result->value->cast ?? [], 'crew' => $result->value->crew ?? [], 'detailsUnavailable' => $result->isUnavailable()];
        }

        $result = $catalog->tvShowDetails($title);
        throw_if($result->isRemoved(), TitleRemoved::class, $title, $user);
        throw_if($result->isUnavailable() || ! $title->hasSourceDetails(), DetailsUnavailable::class, $title, $user);
        $pageData = $catalog->tvShowPageData($title);

        return ['cast' => $pageData->cast ?? [], 'crew' => $pageData->crew ?? [], 'detailsUnavailable' => $pageData === null];
    }
}
