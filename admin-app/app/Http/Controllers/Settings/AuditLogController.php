<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Activity log: every data-changing action from both apps (Technical Guide 4.1), Super Admin only. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $keyword = mb_substr(trim((string) $request->query('keyword', '')), 0, 100);
        $pattern = '%'.addcslashes($keyword, '\\%_').'%';
        $log = AuditLog::query()->with('user:id,name,email')
            ->when($keyword !== '', fn ($q) => $q->where(fn ($w) => $w->where('route', 'like', $pattern)->orWhere('path', 'like', $pattern)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern))))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.settings.audit', ['log' => $log, 'keyword' => $keyword]);
    }
}
