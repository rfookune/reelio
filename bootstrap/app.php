<?php

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\MovieRemoved;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Renders the shared error page for an HTTP exception the app raises itself, keeping the exception's headers.
         * JSON requests get the framework's default rendering.
         */
        $errorPage = function (HttpException $exception, Request $request, string $title, string $message): ?Response {
            if ($request->expectsJson()) {
                return null;
            }

            $response = Inertia::render('Error', ['status' => $exception->getStatusCode(), 'title' => $title, 'message' => $message])
                ->toResponse($request)
                ->setStatusCode($exception->getStatusCode());
            $response->headers->add($exception->getHeaders());

            return $response;
        };

        $exceptions->render(fn (DetailsUnavailable $exception, Request $request) => $errorPage(
            $exception,
            $request,
            'Details temporarily unavailable',
            'We can\'t reach our movie data source right now. Please try again in a moment.',
        ));

        $exceptions->render(fn (MovieRemoved $exception, Request $request) => $errorPage(
            $exception,
            $request,
            'This movie is no longer available.',
            'Our data source, TMDB, has removed this movie, so we can\'t show its details any more.',
        ));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
