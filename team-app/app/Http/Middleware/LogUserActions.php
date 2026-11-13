<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Technical Guide 4.1: every Super Admin action is logged. Every data-changing request (POST, PUT, PATCH, DELETE)
 * from a logged-in user is written to audit_logs, including login and logout, for every role; secret fields are replaced
 * with [disembunyikan] and files keep only their name. The middleware parameter names the app (admin or team).
 * A logging failure never fails the user action; it is reported to the application log instead.
 */
class LogUserActions
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const SECRET_PATTERN = '/pass|sandi|token|secret|key/i';

    public function handle(Request $request, Closure $next, string $app = 'admin'): Response
    {
        $before = $request->user();
        $response = $next($request);
        $user = $request->user() ?? $before;
        if ($user && in_array($request->method(), self::METHODS, true)) {
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'role' => $user->roleSlugs()[0] ?? null,
                    'app' => mb_substr($app, 0, 20),
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                    'input' => self::sanitize($request->except(['_token', '_method'])),
                    'status' => $response->getStatusCode(),
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    /** Secret fields are hidden, files are replaced by their name, text is cut to 300 characters. */
    public static function sanitize(array $fields): array
    {
        $result = [];
        foreach ($fields as $k => $v) {
            if (is_string($k) && preg_match(self::SECRET_PATTERN, $k)) {
                $result[$k] = '[disembunyikan]';
            } elseif ($v instanceof UploadedFile) {
                $result[$k] = 'berkas: '.mb_substr($v->getClientOriginalName(), 0, 120);
            } elseif (is_array($v)) {
                $result[$k] = self::sanitize($v);
            } elseif (is_string($v)) {
                $result[$k] = mb_substr($v, 0, 300);
            } else {
                $result[$k] = $v;
            }
        }

        return $result;
    }
}
