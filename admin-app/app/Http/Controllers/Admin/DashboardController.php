<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Services\ManagementMetricsService;
use App\Services\Scope;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ringkasan Sistem: KPI, keterlambatan, project aktif, review, aktivitas terakhir (Petunjuk Teknis 5.1). */
class DashboardController extends Controller
{
    public function index(Request $request, ManagementMetricsService $m): View
    {
        $u = $request->user();

        return view('admin.dashboard', [
            'kpi' => $m->kpi($u),
            'terlambat' => Scope::tasks($u)->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())
                ->with(['project:id,name', 'pic:id,name'])->orderBy('tasks.due_at')->limit(6)->get(),
            'aktif' => $m->monitoring($u)->where('status', 'active')->take(6),
            'review' => $u->hasPermission('tasks.review') ? TaskSubmission::query()->where('status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
                ->with(['task:id,title,project_id', 'submitter:id,name'])->oldest('submitted_at')->limit(5)->get() : collect(),
            'aktivitas' => ProjectActivity::query()->whereIn('project_id', Scope::projects($u)->select('projects.id'))->with(['project:id,name', 'creator:id,name'])->latest('id')->limit(6)->get(),
            'tugasSaya' => Scope::tasks($u)->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')
                ->whereIn('tasks.id', \Illuminate\Support\Facades\DB::table('task_assignees')->select('task_id')->where('user_id', $u->id))->count(),
        ]);
    }
}
