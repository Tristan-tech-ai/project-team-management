<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Global search in the Admin header (prototypes 7.1-7.3: "Cari project, task, atau anggota").
 * Every group uses the same data scope as its own page, so a search never shows data outside the user's access.
 */
class SearchController extends Controller
{
    public const LIMIT = 10;

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $keyword = mb_substr(trim((string) $request->query('keyword', '')), 0, 100);
        $results = ['projects' => collect(), 'tasks' => collect(), 'members' => collect()];
        if (mb_strlen($keyword) >= 2) {
            // Every word must match somewhere (in any order), so "pendaftaran 2027" finds "Campaign Pendaftaran Sekolah 2027".
            $words = array_slice(array_values(array_filter(preg_split('/\s+/u', $keyword))), 0, 5);
            $like = fn (string $w) => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $w).'%';
            $each = fn ($q, array $columns) => $q->where(function ($all) use ($words, $columns, $like) {
                foreach ($words as $w) {
                    $all->where(function ($one) use ($w, $columns, $like) {
                        foreach ($columns as $c) {
                            $one->orWhere($c, 'like', $like($w));
                        }
                    });
                }
            });
            $results['projects'] = $each(Scope::projects($user), ['projects.name', 'projects.code'])
                ->with(['client:id,name', 'pic:id,name'])->orderBy('projects.name')->limit(self::LIMIT)->get();
            $results['tasks'] = $each(Scope::tasks($user), ['tasks.title'])
                ->with(['project:id,name', 'pic:id,name'])->orderByRaw('tasks.due_at is null')->orderBy('tasks.due_at')->limit(self::LIMIT)->get();
            // Email is searchable only for users who may see the member master data; teammates are found by name.
            $results['members'] = $each($this->members($user), $user->can('members.view') ? ['users.name', 'users.email'] : ['users.name'])
                ->orderBy('users.name')->limit(self::LIMIT)->get(['users.id', 'users.name', 'users.email']);
        }

        if ($request->expectsJson()) {
            return response()->json($this->forPalette($user, $results));
        }

        return view('admin.search', ['keyword' => $keyword, 'results' => $results,
            'canViewMembers' => $user->can('members.view'), 'canManageMembers' => $user->can('members.manage')]);
    }

    /**
     * I9 command palette: the same results (same scope) as short rows with a link; at most 5 per group.
     * Tasks open in the side panel (I3); a member links to the edit page for users who may manage members.
     */
    private function forPalette(User $user, array $results): array
    {
        $manage = $user->can('members.manage');

        return [
            'projects' => $results['projects']->filter(fn ($p) => $user->can('view', $p))->take(5)->map(fn ($p) => ['label' => $p->name,
                'detail' => collect([$p->code, $p->client?->name ?? 'Internal'])->filter()->implode(' · '), 'url' => route('projects.show', $p)])->values(),
            'tasks' => $results['tasks']->take(5)->map(fn ($t) => ['label' => $t->title,
                'detail' => collect([$t->project?->name, $t->pic ? 'PIC '.$t->pic->name : null])->filter()->implode(' · '), 'url' => route('tasks.show', $t), 'panel' => true])->values(),
            // Without member management a person opens the all-tasks list filtered to their tasks (what a lead usually wants to see).
            'members' => $results['members']->take(5)->map(fn ($m) => ['label' => $m->name,
                'detail' => $manage ? $m->email : 'Lihat task-nya',
                'url' => $manage ? route('members.edit', $m->id) : route('tasks.index', ['assignee' => $m->id])])->values(),
        ];
    }

    /** Members: master data scope for users who may view Anggota Tim, otherwise only teammates in projects within scope. */
    private function members(User $user)
    {
        if ($user->can('members.view')) {
            return Scope::masterMembers($user);
        }

        return User::query()->whereIn('users.id', DB::table('project_members')->select('user_id')->where('is_active', 1)
            ->whereIn('project_id', Scope::projects($user)->select('projects.id')));
    }
}
