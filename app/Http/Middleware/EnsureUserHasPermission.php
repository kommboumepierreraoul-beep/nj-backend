<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! collect($permissions)->every(fn (string $permission) => $user->hasPermission($permission))) {
            return response()->json(['message' => 'Permission insuffisante.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
