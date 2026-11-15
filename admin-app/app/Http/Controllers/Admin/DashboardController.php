<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Services\ManagementMetricsService;
use App\Services\Scope;
use App\Services\TodoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** System Summary: what needs me now (I1), KPIs, delays, active projects, latest activities (Technical Guide 5.1). */
class DashboardController extends Controller
{
    public function index(Request $request, ManagementMetricsService $m, TodoService $todo): View
    {
        $u = $request->user();

        return view('admin.dashboard', [
            'firstSteps' => $u->isSuperAdmin() ? $this->firstSteps() : [],
            'todo' => $todo->for($u),
            'kpi' => $m->kpi($u),
            'overdue' => Scope::tasks($u)->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())
                ->with(['project:id,name', 'pic:id,name'])->orderBy('tasks.due_at')->limit(6)->get(),
            'active' => $m->monitoring($u)->where('status', 'active')->take(6),
            'review' => $u->hasPermission('tasks.review') ? TaskSubmission::query()->where('status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
                ->with(['task:id,title,project_id', 'submitter:id,name'])->oldest('submitted_at')->limit(5)->get() : collect(),
            'activities' => ProjectActivity::query()->whereIn('project_id', Scope::projects($u)->select('projects.id'))->with(['project:id,name', 'creator:id,name'])->latest('id')->limit(6)->get(),
            'myTasks' => Scope::tasks($u)->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')
                ->whereIn('tasks.id', \Illuminate\Support\Facades\DB::table('task_assignees')->select('task_id')->where('user_id', $u->id))->count(),
        ]);
    }

    /** I8 first steps on a new installation, ticked from the data itself (Super Admin only). */
    private function firstSteps(): array
    {
        return [
            ['Lengkapi data perusahaan (email atau telepon)', \App\Models\Company::query()->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))->exists(), route('companies.index')],
            ['Tambah anggota tim', \Illuminate\Support\Facades\DB::table('user_profiles')->where('is_active', 1)->count() >= 3, route('members.create')],
            ['Periksa template project', \App\Models\ProjectTemplate::query()->where('is_active', 1)->exists(), route('templates.projects.index')],
            ['Buat project pertama', \App\Models\Project::query()->exists(), route('projects.create')],
        ];
    }
}
