<?php

namespace App\Http\Middleware;

use App\Enums\Surface;
use App\Enums\UserTitleAction;
use App\Http\Requests\UserMovieActionRequest;
use App\Http\Support\TitleActions\MovieByCatalogId;
use App\Http\Support\Visitor\IntendedUrl;
use App\Http\Support\Visitor\PendingGuestAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for `auth` on a Watchlist or Watched action: a signed-in user's request goes through, while a guest's
 * is stored as their pending action and they are sent to sign in, to return to the page they were on. Nothing is
 * saved and no Movie row is created until they sign in.
 */
class StorePendingGuestAction
{
    public function __construct(private MovieByCatalogId $movies) {}

    /** The middleware for a route taking `$action`. */
    public static function for(UserTitleAction $action): string
    {
        return self::class.':'.$action->value;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $action): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $validated = $request->validate((new UserMovieActionRequest)->rules());
        PendingGuestAction::store($request, $this->movies->catalogId($request->route('tmdbId')), UserTitleAction::from($action), Surface::from($validated['source']));
        IntendedUrl::setToLastPage($request);

        return redirect()->route('login');
    }
}
