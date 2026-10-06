<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    /**
     * Bloque l'acces aux routes metier tant que l'utilisateur n'a pas
     * remplace son mot de passe temporaire (compte invite ou reinitialise).
     * Les routes du groupe "auth" (me, logout, change-password, ...) ne
     * passent pas par ce middleware afin que l'utilisateur puisse toujours
     * consulter son profil, se deconnecter, ou changer son mot de passe.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
