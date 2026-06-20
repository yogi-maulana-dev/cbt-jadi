<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Siswa yang wajib mengganti password & mengisi email (login pertama) diarahkan
 * ke halaman "Lengkapi Akun" sebelum bisa mengakses dashboard/ujian.
 */
class EnsureAccountCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'siswa' && $user->must_change_password
            && ! $request->routeIs('akun.lengkapi')) {
            return redirect()->route('akun.lengkapi');
        }

        return $next($request);
    }
}
