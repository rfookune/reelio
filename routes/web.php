<?php

use App\Enums\UserTitleAction;
use App\Http\Controllers\AvailabilityLinkController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\EpisodeWatchedController;
use App\Http\Controllers\MovieCastController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MovieEntryController;
use App\Http\Controllers\MoviePickerController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SearchRatingsController;
use App\Http\Controllers\SeasonWatchedController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TvShowAvailabilityLinkController;
use App\Http\Controllers\TvShowController;
use App\Http\Controllers\TvShowEntryController;
use App\Http\Controllers\TvShowUndoController;
use App\Http\Controllers\TvShowWatchedController;
use App\Http\Controllers\TvShowWatchlistController;
use App\Http\Controllers\UndoController;
use App\Http\Controllers\UserListController;
use App\Http\Controllers\WatchedController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\StorePendingGuestAction;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;

Route::get('/', DiscoverController::class)->name('home');
Route::inertia('about', 'About')->name('about');
Route::inertia('privacy', 'Privacy')->name('privacy');
Route::inertia('terms', 'Terms')->name('terms');
Route::inertia('accessibility', 'Accessibility')->name('accessibility');
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemaps/{page}.xml', [SitemapController::class, 'show'])->whereNumber('page')->name('sitemap.show');

Route::get('search', SearchController::class)->middleware('throttle:search')->name('search');
Route::get('search/ratings', SearchRatingsController::class)->middleware('throttle:search-ratings')->name('search.ratings');
Route::get('watchlist', UserListController::class)->middleware(['auth', EncryptHistory::class])->name('watchlist');
Route::get('watched', UserListController::class)->middleware(['auth', EncryptHistory::class])->name('watched');
Route::get('watchlist/pick', MoviePickerController::class)->middleware(['auth', EncryptHistory::class, 'throttle:movie-picker'])->name('watchlist.pick');

Route::scopeBindings()->middleware('throttle:user-movie-actions')->group(function () {
    Route::put('tv-shows/tmdb/{tmdbId}/watchlist', [TvShowWatchlistController::class, 'update'])->whereNumber('tmdbId')->name('tv-shows.watchlist.update');
    Route::delete('tv-shows/tmdb/{tmdbId}/watchlist', [TvShowWatchlistController::class, 'destroy'])->whereNumber('tmdbId')->name('tv-shows.watchlist.destroy');
    Route::put('tv-shows/{tvShow}/seasons/{season}/episodes/{episode}/watched', [EpisodeWatchedController::class, 'update'])->name('tv-shows.episodes.watched.update');
    Route::delete('tv-shows/{tvShow}/seasons/{season}/episodes/{episode}/watched', [EpisodeWatchedController::class, 'destroy'])->name('tv-shows.episodes.watched.destroy');
    Route::put('tv-shows/{tvShow}/seasons/{season}/watched', [SeasonWatchedController::class, 'update'])->name('tv-shows.seasons.watched.update');
    Route::delete('tv-shows/{tvShow}/seasons/{season}/watched', [SeasonWatchedController::class, 'destroy'])->name('tv-shows.seasons.watched.destroy');
    Route::put('tv-shows/tmdb/{tmdbId}/watched', [TvShowWatchedController::class, 'update'])->whereNumber('tmdbId')->name('tv-shows.watched.update');
    Route::delete('tv-shows/tmdb/{tmdbId}/watched', [TvShowWatchedController::class, 'destroy'])->whereNumber('tmdbId')->name('tv-shows.watched.destroy');
    Route::post('tv-shows/tmdb/{tmdbId}/undo', TvShowUndoController::class)->middleware('auth')->whereNumber('tmdbId')->name('tv-shows.undo');
});

Route::get('tv-shows/tmdb/{tmdbId}/watch', TvShowAvailabilityLinkController::class)->whereNumber('tmdbId')->name('tv-shows.watch');
Route::get('tv-shows/tmdb/{tmdbId}', TvShowEntryController::class)->whereNumber('tmdbId')->name('tv-shows.entry');
Route::get('tv-shows/{slug}-{id}', [TvShowController::class, 'show'])->where('slug', '[^/]+')->whereNumber('id')->name('tv-shows.show');

Route::get('movies/tmdb/{tmdbId}', MovieEntryController::class)->whereNumber('tmdbId')->name('movies.entry');

Route::middleware('throttle:user-movie-actions')->group(function () {
    Route::put('movies/tmdb/{tmdbId}/watchlist', [WatchlistController::class, 'update'])->middleware(StorePendingGuestAction::for(UserTitleAction::AddToWatchlist))->whereNumber('tmdbId')->name('movies.watchlist.update');
    Route::delete('movies/tmdb/{tmdbId}/watchlist', [WatchlistController::class, 'destroy'])->middleware(StorePendingGuestAction::for(UserTitleAction::RemoveFromWatchlist))->whereNumber('tmdbId')->name('movies.watchlist.destroy');
    Route::put('movies/tmdb/{tmdbId}/watched', [WatchedController::class, 'update'])->middleware(StorePendingGuestAction::for(UserTitleAction::MarkAsWatched))->whereNumber('tmdbId')->name('movies.watched.update');
    Route::delete('movies/tmdb/{tmdbId}/watched', [WatchedController::class, 'destroy'])->middleware(StorePendingGuestAction::for(UserTitleAction::ClearWatched))->whereNumber('tmdbId')->name('movies.watched.destroy');
    Route::post('movies/tmdb/{tmdbId}/undo', UndoController::class)->middleware('auth')->whereNumber('tmdbId')->name('movies.undo');
});
Route::get('movies/{id}/watch', AvailabilityLinkController::class)->whereNumber('id')->name('movies.watch');
Route::get('movies/{slug}-{id}', [MovieController::class, 'show'])->where('slug', '[^/]+')->whereNumber('id')->name('movies.show');
Route::get('movies/{slug}-{id}/casts', MovieCastController::class)->where('slug', '[^/]+')->whereNumber('id')->name('movies.cast');

Route::put('country', CountryController::class)->name('country.update');

Route::post('csp-report', CspReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:csp-reports')
    ->name('csp-report');

require __DIR__.'/settings.php';
