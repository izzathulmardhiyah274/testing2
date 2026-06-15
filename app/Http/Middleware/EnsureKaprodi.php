<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKaprodi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['kaprodi', 'kajur'])) {
            abort(403, 'Halaman ini hanya dapat diakses oleh Kaprodi.');
        }

        return $next($request);
    }
}
