<?php

use App\Http\Exceptions\ErrorPageException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordGuestPage;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Inertia\ExceptionResponse;
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
        /** Render every application 429 consistently, including limits outside the route middleware. */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $request->attributes->set('monitoring.exception_class', $exception::class);

            if ($response->getStatusCode() !== 429) {
                return $response;
            }

            $message = "You're going too fast. Try again in a moment.";
            $headers = array_filter([
                'Retry-After' => $response->headers->get('Retry-After', '60'),
                'X-RateLimit-Limit' => $response->headers->get('X-RateLimit-Limit'),
                'X-RateLimit-Remaining' => $response->headers->get('X-RateLimit-Remaining'),
                'X-Robots-Tag' => 'noindex',
            ], fn (?string $value): bool => $value !== null);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429, $headers);
            }

            $error = new ExceptionResponse($exception, $request, $response, app(Router::class), app(Kernel::class));
            $response = $error->render('Error', [
                'status' => 429,
                'title' => 'Too many requests',
                'message' => $message,
                'exceptionClass' => $exception::class,
            ])->withSharedData()->toResponse($request);
            $response->headers->add($headers);

            return $response;
        });

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
                'exceptionClass' => $exception::class,
                'title' => $exception->getMessage(),
                'message' => $exception->description,
                ...($exception->data === null ? [] : ['data' => $exception->data]),
            ])->toResponse($request)->setStatusCode($exception->getStatusCode());
            $response->headers->add($exception->getHeaders());

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
