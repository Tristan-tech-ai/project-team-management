<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPeriod;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membuat project dari template (Petunjuk Teknis 8.1 dan 8.5): project, divisi, anggota unik (PIC otomatis anggota),
 * periode pertama untuk recurring/ongoing, lalu task + subtask dari template, semuanya dalam SATU transaksi.
 * Gagal di langkah mana pun = rollback semuanya.
 */
class ProjectTemplateService
{
    public function __construct(private ProjectTeamService $tim) {}

    /** Pratinjau task/subtask yang akan dibuat, tanpa menulis apa pun. */
    public function pratinjau(?ProjectTemplate $template, ?Carbon $mulai = null): array
    {
        if (! $template) {
            return [];
        }
        $mulai ??= now();
        $semua = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $hasil = [];
        foreach ($semua->whereNull('parent_id') as $t) {
            $hasil[] = [
                'title' => $t->title, 'task_type' => $t->task_type, 'estimated_minutes' => $t->estimated_minutes,
                'due_at' => $t->relative_due_days !== null ? $mulai->copy()->addDays($t->relative_due_days) : null,
                'subtasks' => $semua->where('parent_id', $t->id)->pluck('title')->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array{company_id:int, client_id:?int, name:string, code:?string, description:?string, project_type:string, status:?string,
     *               priority:?string, pic_user_id:int, start_at:?Carbon, due_at:?Carbon, recurrence_unit:?string, recurrence_interval:?int,
     *               project_template_id:?int, divisions:array<int>, members:array<int>, notes:?string}  $d
     */
    public function buatProject(array $d, User $oleh): Project
    {
        return DB::transaction(function () use ($d, $oleh) {
            $project = Project::create([
                'company_id' => $d['company_id'], 'client_id' => $d['client_id'] ?? null, 'project_template_id' => $d['project_template_id'] ?? null,
                'pic_user_id' => $d['pic_user_id'], 'created_by' => $oleh->id,
                'code' => $d['code'] ?: $this->kodeBaru(), 'name' => $d['name'], 'description' => $d['description'] ?? null,
                'project_type' => $d['project_type'], 'status' => $d['status'] ?? 'active', 'priority' => $d['priority'] ?? 'normal',
                'start_at' => $d['start_at'] ?? now(), 'baseline_due_at' => $d['due_at'] ?? null, 'due_at' => $d['due_at'] ?? null,
                'recurrence_unit' => $d['project_type'] === 'one_time' ? null : ($d['recurrence_unit'] ?? 'month'),
                'recurrence_interval' => $d['project_type'] === 'one_time' ? null : ($d['recurrence_interval'] ?? 1),
                'notes' => $d['notes'] ?? null,
            ]);
            $this->tim->aturTim($project, $d['divisions'] ?? [], $d['members'] ?? [], $oleh);

            $periode = null;
            if ($project->project_type !== 'one_time') {
                $periode = $this->periodeBerikut($project, $oleh, Carbon::instance($project->start_at));
            }
            if (! empty($d['project_template_id'])) {
                $this->generate($project, ProjectTemplate::query()->findOrFail($d['project_template_id']), $periode, $oleh);
            }

            return $project->refresh();
        });
    }

    /**
     * Membuka periode baru untuk project recurring/ongoing (manual, dokumen 8.5 dan catatan SQL C).
     * Project induk tetap berjalan; task periode lama tetap di periode lama.
     */
    public function bukaPeriode(Project $project, User $oleh, ?Carbon $mulai = null, bool $generate = true): ProjectPeriod
    {
        if ($project->project_type === 'one_time') {
            throw ValidationException::withMessages(['period' => 'Project sekali jadi tidak punya periode.']);
        }

        return DB::transaction(function () use ($project, $oleh, $mulai, $generate) {
            $p = Project::query()->lockForUpdate()->findOrFail($project->id);
            $terakhir = $p->periods()->orderByDesc('starts_at')->first();
            $mulai ??= $terakhir ? Carbon::instance($terakhir->ends_at)->addSecond() : now();
            if ($terakhir && $terakhir->status === 'active') {
                $terakhir->update(['status' => 'closed', 'completed_at' => now()]);
            }
            $periode = $this->periodeBerikut($p, $oleh, $mulai);
            if ($generate && $p->project_template_id) {
                $this->generate($p, ProjectTemplate::query()->findOrFail($p->project_template_id), $periode, $oleh);
            }

            return $periode;
        });
    }

    private function periodeBerikut(Project $project, User $oleh, Carbon $mulai): ProjectPeriod
    {
        $unit = $project->recurrence_unit ?: 'month';
        $n = max(1, (int) ($project->recurrence_interval ?: 1));
        $lokal = $mulai->copy()->setTimezone(\App\Support\Waktu::zona());
        if ($unit === 'week') {
            $awal = $lokal->copy()->startOfWeek();
            $akhir = $awal->copy()->addWeeks($n)->subSecond();
            $kode = $awal->format('o').'-W'.$awal->format('W');
            $nama = 'Minggu '.$awal->format('W').' '.$awal->format('o');
        } else {
            $awal = $lokal->copy()->startOfMonth();
            $akhir = $awal->copy()->addMonthsNoOverflow($n)->subSecond();
            $kode = $awal->format('Y-m');
            $nama = 'Periode '.\App\Support\Waktu::tanggal($awal).' sampai '.\App\Support\Waktu::tanggal($akhir);
        }
        if ($project->periods()->where('period_code', $kode)->exists()) {
            throw ValidationException::withMessages(['period' => "Periode {$kode} sudah ada di project ini."]);
        }

        return ProjectPeriod::create(['project_id' => $project->id, 'period_code' => $kode, 'name' => $nama,
            'starts_at' => $awal->utc(), 'ends_at' => $akhir->utc(), 'status' => 'active', 'created_by' => $oleh->id]);
    }

    /** Membuat task + subtask dari template project. PIC task = PIC project, dan otomatis menjadi assignee. */
    public function generate(Project $project, ProjectTemplate $template, ?ProjectPeriod $periode, User $oleh): int
    {
        $mulai = $periode ? Carbon::instance($periode->starts_at) : Carbon::instance($project->start_at ?? now());
        $semua = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $jumlah = 0;
        foreach ($semua->whereNull('parent_id')->values() as $i => $tt) {
            $due = $tt->relative_due_days !== null ? $mulai->copy()->addDays($tt->relative_due_days) : ($periode ? Carbon::instance($periode->ends_at) : $project->due_at);
            $task = Task::create([
                'project_id' => $project->id, 'project_period_id' => $periode?->id, 'parent_id' => null, 'pic_user_id' => $project->pic_user_id,
                'created_by' => $oleh->id, 'title' => $tt->title, 'description' => $tt->description, 'task_type' => $tt->task_type ?: 'general',
                'status' => 'backlog', 'priority' => 'normal', 'estimated_minutes' => $tt->estimated_minutes,
                'baseline_due_at' => $due, 'due_at' => $due, 'sort_order' => $i,
            ]);
            $this->tim->pastikanAssignee($task, (int) $project->pic_user_id, $oleh);
            TaskActivity::create(['task_id' => $task->id, 'user_id' => $oleh->id, 'activity_type' => 'created', 'description' => 'Dibuat dari template '.$template->name]);
            $jumlah++;
            foreach ($semua->where('parent_id', $tt->id)->values() as $j => $st) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $periode?->id, 'parent_id' => $task->id, 'pic_user_id' => $project->pic_user_id,
                    'created_by' => $oleh->id, 'title' => $st->title, 'task_type' => $st->task_type ?: 'general', 'status' => 'backlog',
                    'estimated_minutes' => $st->estimated_minutes, 'sort_order' => $j]);
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /** Menerapkan template task mandiri (task + checklist) ke project. */
    public function terapkanTemplateTask(Project $project, TaskTemplate $tt, array $isi, User $oleh): Task
    {
        return DB::transaction(function () use ($project, $tt, $isi, $oleh) {
            $task = Task::create($isi + ['project_id' => $project->id, 'created_by' => $oleh->id, 'task_template_id' => $tt->id,
                'task_type' => $isi['task_type'] ?? $tt->task_type, 'estimated_minutes' => $isi['estimated_minutes'] ?? $tt->default_estimated_minutes]);
            foreach ($tt->subtasks()->orderBy('sort_order')->get() as $j => $s) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $task->project_period_id, 'parent_id' => $task->id,
                    'pic_user_id' => $task->pic_user_id, 'created_by' => $oleh->id, 'title' => $s->title, 'status' => 'backlog', 'sort_order' => $j,
                    'estimated_minutes' => $s->estimated_minutes]);
            }

            return $task;
        });
    }

    private function kodeBaru(): string
    {
        do {
            $kode = 'PRJ-'.now()->format('ym').'-'.Str::upper(Str::random(4));
        } while (Project::withTrashed()->where('code', $kode)->exists());

        return $kode;
    }
}
