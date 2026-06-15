<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMahasiswa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'mahasiswa') {
            abort(403, 'Halaman ini hanya dapat diakses oleh Mahasiswa.');
        }

        return $next($request);
    }
}
