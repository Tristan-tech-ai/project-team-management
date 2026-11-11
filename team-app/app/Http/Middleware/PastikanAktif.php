<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna yang dinonaktifkan setelah masuk langsung kehilangan akses pada request berikutnya. */
class PastikanAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Akun ini sedang dinonaktifkan. Hubungi admin.']);
        }

        return $next($request);
    }
}
