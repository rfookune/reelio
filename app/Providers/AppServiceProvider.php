<?php

namespace App\Providers;

use App\Http\Support\RateLimitKey;
use App\Http\Support\Search\SearchPage;
use App\Http\Support\SeoRoutes;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\GuardedCatalogSource;
use App\Services\Catalog\Sources\Tmdb\TmdbCatalogSource;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\Ratings;
use App\Services\Ratings\Sources\StoredRatingSource;
use App\Services\Ratings\Sync\Datasets\ImdbRatingDataset;
use App\Services\Ratings\Sync\Datasets\RatingDatasets;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CatalogSource::class, fn (Application $app): CatalogSource => new GuardedCatalogSource($app->make(TmdbCatalogSource::class), $app->make(CacheKey::class)));

        $this->app->bind(Ratings::class, fn (): Ratings => new Ratings(array_map(
            fn (RatingSourceName $source): StoredRatingSource => new StoredRatingSource($source),
            RatingSourceName::cases(),
        )));

        $this->app->tag([ImdbRatingDataset::class], 'rating-datasets');
        $this->app->bind(RatingDatasets::class, fn (Application $app): RatingDatasets => new RatingDatasets(iterator_to_array($app->tagged('rating-datasets'))));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureServerRendering();
        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters. Search and the Movie picker each have their own budget; Watchlist and Watched actions and Undo share one.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('csp-reports', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->ip())
            ->response(fn () => response()->noContent()));

        /*
         * Reloading Search's `userMovies` after a Watchlist action reads only the cache, and the action has its own
         * budget, so it doesn't count against search's.
         */
        RateLimiter::for('search', fn (Request $request): Limit => SearchPage::reloadsUserMoviesOnly($request)
            ? Limit::none()
            : Limit::perMinute(60)->by(RateLimitKey::for($request)));

        RateLimiter::for('search-ratings', fn (Request $request): Limit => Limit::perMinute(60)->by(RateLimitKey::for($request)));

        RateLimiter::for('user-movie-actions', fn (Request $request): Limit => Limit::perMinute(60)->by(RateLimitKey::for($request)));

        RateLimiter::for('movie-picker', fn (Request $request): Limit => Limit::perMinute(30)->by(RateLimitKey::for($request)));

        RateLimiter::for('profile-update', fn (Request $request): Limit => Limit::perMinute(6)->by(RateLimitKey::for($request)));

        RateLimiter::for('account-deletion', fn (Request $request): Limit => Limit::perMinute(5)->by(RateLimitKey::for($request)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Render on the server only the `seo.ssr_routes`: Discover, Movie pages and the
     * About, Privacy, Terms and Accessibility pages. Search, auth, the Watchlist and Watched pages and settings are
     * rendered on the client, and so is any page while the SSR server is down. Setting a condition replaces
     * Inertia's own `inertia.ssr.enabled` check, so the condition repeats it.
     */
    private function configureServerRendering(): void
    {
        Inertia::disableSsr(fn (): bool => ! config('inertia.ssr.enabled') || ! SeoRoutes::isServerRendered(request()));
    }
}
