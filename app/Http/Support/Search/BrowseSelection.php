<?php

namespace App\Http\Support\Search;

use App\Enums\TitleKind;
use App\Services\Catalog\Search\BrowseOptions;
use App\Services\Catalog\Sources\Listings\BrowseFilters;

/**
 * What Browse offers for a request and which of it the request has chosen: the genres and Streaming services the
 * tab offers, and the filters, each of the visitor's values checked against what is offered, so that only an offered
 * value ever reaches the source. A genre the tab doesn't offer is carried over to the tab's genre of the same name, if
 * it has one; Streaming services are kept once each, in id order, up to the most allowed, and stay across tabs where
 * both kinds offer them; a search by Name takes no filters, though it still offers the lists so that choosing one can
 * leave it. The lists come from the cache alone when the request reads only the cache, and a list the source can't
 * give is empty, which leaves its filter ignored.
 */
class BrowseSelection
{
    private ?BrowseFilters $filters = null;

    /** @var list<array{id: int, name: string}>|null */
    private ?array $genres = null;

    /** @var list<array{id: int, name: string}>|null */
    private ?array $streamingServices = null;

    public function __construct(private SearchRequest $request, private BrowseOptions $options) {}

    /**
     * The genres the tab offers, in the source's order.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(): array
    {
        return $this->genres ??= $this->genresOf($this->request->kind);
    }

    /**
     * The Streaming services the tab offers in the visitor's country, most prominent first.
     *
     * @return list<array{id: int, name: string}>
     */
    public function streamingServices(): array
    {
        return $this->streamingServices ??= $this->streamingServicesOf($this->request->kind);
    }

    /** The filters of the request, none in a search by Name. */
    public function filters(): BrowseFilters
    {
        return $this->filters ??= $this->request->isSearching()
            ? new BrowseFilters
            : new BrowseFilters(
                $this->genreOnTab(),
                $this->request->requestedSort,
                $this->streamingServicesOnTab(),
                $this->request->country,
                $this->request->requestedHighlyRated,
                $this->request->requestedEra,
                $this->request->kind === TitleKind::Movie ? $this->request->requestedRuntimeBand : null,
                $this->request->requestedOriginalLanguage,
            );
    }

    /**
     * The requested Streaming services the tab offers, once each in id order, the first few by the most allowed.
     *
     * @return list<int>
     */
    private function streamingServicesOnTab(): array
    {
        $offered = array_column($this->streamingServices(), 'id');
        $selected = array_slice(array_values(array_filter($this->request->requestedStreamingServices, fn (int $id): bool => in_array($id, $offered, true))), 0, config('catalog.search.maximum_streaming_services'));
        sort($selected);

        return $selected;
    }

    /**
     * The requested genre as the tab's own genre id: the id when the tab offers it, else the id of the tab's genre
     * with the same name as that id has in the other kind's list, else null. A value that isn't a plain id, and
     * any while the tab's list is unavailable, is ignored.
     */
    private function genreOnTab(): ?int
    {
        $id = $this->request->requestedGenre;
        $genres = $this->genres();

        if ($id === null || $genres === []) {
            return null;
        }

        if (array_find($genres, fn (array $genre): bool => $genre['id'] === $id) !== null) {
            return $id;
        }

        if (! config('catalog.tv_shows.enabled')) {
            return null;
        }

        $otherGenres = $this->genresOf($this->request->kind === TitleKind::Movie ? TitleKind::TvShow : TitleKind::Movie);
        $name = array_find($otherGenres, fn (array $genre): bool => $genre['id'] === $id)['name'] ?? null;

        return array_find($genres, fn (array $genre): bool => $genre['name'] === $name)['id'] ?? null;
    }

    /**
     * A kind's genres, from the cache alone when the request reads only the cache.
     *
     * @return list<array{id: int, name: string}>
     */
    private function genresOf(TitleKind $kind): array
    {
        return $this->request->cacheMode->readsCacheOnly() ? $this->options->cachedGenres($kind) : $this->options->genres($kind);
    }

    /**
     * A kind's Streaming services in the visitor's country, from the cache alone when the request reads only the cache.
     *
     * @return list<array{id: int, name: string}>
     */
    private function streamingServicesOf(TitleKind $kind): array
    {
        return $this->request->cacheMode->readsCacheOnly() ? $this->options->cachedStreamingServices($kind, $this->request->country) : $this->options->streamingServices($kind, $this->request->country);
    }
}
