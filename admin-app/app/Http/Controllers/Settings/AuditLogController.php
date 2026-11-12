<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Log aktivitas: semua aksi yang mengubah data dari kedua app (Petunjuk Teknis 4.1), hanya untuk Super Admin. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $cari = mb_substr(trim((string) $request->query('cari', '')), 0, 100);
        $pola = '%'.addcslashes($cari, '\\%_').'%';
        $log = AuditLog::query()->with('user:id,name,email')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('route', 'like', $pola)->orWhere('path', 'like', $pola)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $pola)->orWhere('email', 'like', $pola))))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.settings.audit', ['log' => $log, 'cari' => $cari]);
    }
}
