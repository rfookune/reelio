<?php

use App\Http\Exceptions\ErrorPageException;
use App\Http\Exceptions\MovieRemoved;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordGuestPage;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Support\UserMovies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->prepend(SecurityHeaders::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RecordGuestPage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * The shared error page for an HTTP error the app raises itself, with the exception's title, description and
         * headers. JSON requests get the framework's default rendering.
         */
        $exceptions->render(function (ErrorPageException $exception, Request $request): ?Response {
            if ($request->expectsJson()) {
                return null;
            }

            $response = Inertia::render('Error', [
                'status' => $exception->getStatusCode(),
                'title' => $exception->getMessage(),
                'message' => $exception->description,
                ...($exception instanceof MovieRemoved && $exception->movie !== null ? [
                    'removedMovieId' => $exception->movie->tmdb_id,
                    'userMovies' => fn (): array => UserMovies::for($request->user(), [$exception->movie]),
                ] : []),
            ])->toResponse($request)->setStatusCode($exception->getStatusCode());
            $response->headers->add($exception->getHeaders());

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
