<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Petunjuk Teknis 4.1: semua aksi Super Admin dicatat. Setiap request yang mengubah data (POST, PUT, PATCH, DELETE)
 * dari pengguna yang masuk dicatat ke audit_logs, termasuk masuk dan keluar, untuk semua role; isian rahasia diganti
 * [disembunyikan] dan berkas hanya namanya. Parameter middleware menyebut aplikasinya (admin atau team).
 * Kegagalan mencatat tidak menggagalkan aksi pengguna, tetapi dilaporkan ke log aplikasi.
 */
class CatatAksi
{
    private const METODE = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const RAHASIA = '/pass|sandi|token|secret|key/i';

    public function handle(Request $request, Closure $next, string $app = 'admin'): Response
    {
        $sebelum = $request->user();
        $response = $next($request);
        $user = $request->user() ?? $sebelum;
        if ($user && in_array($request->method(), self::METODE, true)) {
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'role' => $user->roleSlugs()[0] ?? null,
                    'app' => mb_substr($app, 0, 20),
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                    'input' => self::bersihkan($request->except(['_token', '_method'])),
                    'status' => $response->getStatusCode(),
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    /** Isian rahasia disembunyikan, berkas diganti namanya, teks dipotong 300 huruf. */
    public static function bersihkan(array $isian): array
    {
        $hasil = [];
        foreach ($isian as $k => $v) {
            if (is_string($k) && preg_match(self::RAHASIA, $k)) {
                $hasil[$k] = '[disembunyikan]';
            } elseif ($v instanceof UploadedFile) {
                $hasil[$k] = 'berkas: '.mb_substr($v->getClientOriginalName(), 0, 120);
            } elseif (is_array($v)) {
                $hasil[$k] = self::bersihkan($v);
            } elseif (is_string($v)) {
                $hasil[$k] = mb_substr($v, 0, 300);
            } else {
                $hasil[$k] = $v;
            }
        }

        return $hasil;
    }
}
