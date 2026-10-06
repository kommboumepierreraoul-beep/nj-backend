<?php

namespace App\Http\Middleware;

use App\Models\SystemTrace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! collect($roles)->contains(fn (string $role) => $user->hasRole($role))) {
            SystemTrace::record('auth.role_denied', user: $user, context: ['required_roles' => $roles]);

            return response()->json(['message' => 'Acces refuse.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
