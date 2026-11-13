<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Scope;
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

    public function index(Request $request): View
    {
        $user = $request->user();
        $keyword = mb_substr(trim((string) $request->query('keyword', '')), 0, 100);
        $results = ['projects' => collect(), 'tasks' => collect(), 'members' => collect()];
        if (mb_strlen($keyword) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword).'%';
            $results['projects'] = Scope::projects($user)
                ->where(fn ($w) => $w->where('projects.name', 'like', $like)->orWhere('projects.code', 'like', $like))
                ->with(['client:id,name', 'pic:id,name'])->orderBy('projects.name')->limit(self::LIMIT)->get();
            $results['tasks'] = Scope::tasks($user)->where('tasks.title', 'like', $like)
                ->with(['project:id,name', 'pic:id,name'])->orderByRaw('tasks.due_at is null')->orderBy('tasks.due_at')->limit(self::LIMIT)->get();
            // Email is searchable only for users who may see the member master data; teammates are found by name.
            $results['members'] = $this->members($user)
                ->where(fn ($w) => $user->can('members.view') ? $w->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like) : $w->where('users.name', 'like', $like))
                ->orderBy('users.name')->limit(self::LIMIT)->get(['users.id', 'users.name', 'users.email']);
        }

        return view('admin.search', ['keyword' => $keyword, 'results' => $results,
            'canViewMembers' => $user->can('members.view'), 'canManageMembers' => $user->can('members.manage')]);
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
