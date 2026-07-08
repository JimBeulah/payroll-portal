<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user holds one of the given roles.
     *
     * Usage: ->middleware('role:admin') or ->middleware('role:admin,hr')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless($user->hasRole(...$roles), 403);

        return $next($request);
    }
}
