<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDosen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Kaprodi/kajur/dekan boleh akses route dosen (fitur switch mode)
        $allowed = ['dosen', 'kaprodi', 'kajur', 'dekan', 'wakil_dekan'];

        if (! $user || ! in_array($user->role, $allowed)) {
            abort(403, 'Halaman ini hanya dapat diakses oleh Dosen.');
        }

        return $next($request);
    }
}
