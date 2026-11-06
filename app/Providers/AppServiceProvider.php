<?php

namespace App\Providers;

use App\Services\Catalog\CacheKey;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\GuardedCatalogSource;
use App\Services\Catalog\Sources\TmdbCatalogSource;
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
        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('csp-reports', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->ip())
            ->response(fn () => response()->noContent()));
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
}
