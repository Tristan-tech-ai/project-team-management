<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Masuk dan keluar untuk kedua aplikasi (Auth Laravel). Pengguna nonaktif tidak bisa masuk. */
class MasukController extends Controller
{
    public function create(): View
    {
        return view('auth.masuk');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:180'], 'password' => ['required', 'string', 'max:200']]);
        $kunci = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.']);
        }
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }
        if (! $request->user()->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun ini sedang dinonaktifkan. Hubungi admin.']);
        }
        RateLimiter::clear($kunci);
        $request->session()->regenerate();

        return redirect()->intended(route('beranda'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
