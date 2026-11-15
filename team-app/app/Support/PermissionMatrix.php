<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * U7: permissions shown as a tick table (view, add, edit, delete, plus named special actions) instead of permission codes.
 * Every permission in the database appears exactly once: one not listed here lands in "Lainnya" so nothing can hide.
 */
final class PermissionMatrix
{
    /** section => [row label => [column => permission slug|list of special actions]] ; 'manage' fills Tambah, Ubah and Hapus at once. */
    public const LAYOUT = [
        'Pekerjaan' => [
            'Dashboard' => ['view' => 'dashboard.view'],
            'Project' => ['view' => 'projects.view', 'create' => 'projects.create', 'update' => 'projects.update', 'delete' => 'projects.delete',
                'extra' => ['projects.manage_team' => 'Atur tim project', 'projects.approve_schedule' => 'Setujui geser jadwal project']],
            'Task' => ['view' => 'tasks.view', 'create' => 'tasks.create', 'update' => 'tasks.update', 'delete' => 'tasks.delete',
                'extra' => ['tasks.assign' => 'Atur pelaksana', 'tasks.move_status' => 'Pindah status', 'tasks.submit' => 'Kirim hasil',
                    'tasks.review' => 'Review hasil', 'tasks.approve_schedule' => 'Setujui geser deadline']],
            'Kalender' => ['view' => 'calendar.view'],
            'Aktivitas' => ['view' => 'activities.view', 'create' => 'activities.create', 'update' => 'activities.update', 'delete' => 'activities.delete'],
            'Template' => ['view' => 'templates.view', 'manage' => 'templates.manage'],
            'Laporan & monitoring' => ['view' => 'management.view', 'extra' => ['management.export' => 'Unduh laporan']],
        ],
        'Data master' => [
            'Company' => ['view' => 'companies.view', 'manage' => 'companies.manage'],
            'Client' => ['view' => 'clients.view', 'manage' => 'clients.manage'],
            'Divisi' => ['view' => 'divisions.view', 'manage' => 'divisions.manage'],
            'Jabatan' => ['view' => 'positions.view', 'manage' => 'positions.manage'],
            'Anggota tim' => ['view' => 'members.view', 'manage' => 'members.manage'],
        ],
        'Pengaturan' => [
            'Role & hak akses' => ['view' => 'settings.roles.view', 'manage' => 'settings.roles.manage'],
            'Menu' => ['manage' => 'settings.menus.manage'],
            'Pengaturan sistem' => ['manage' => 'settings.system.manage'],
        ],
    ];

    /**
     * @param  Collection<int, object{id:int, slug:string, name:string}>  $permissions
     * @return array<string, list<array{label:string, view:?object, create:?object, update:?object, delete:?object, manage:?object, extra:list<array{0:object,1:string}>}>>
     */
    public static function build(Collection $permissions): array
    {
        $bySlug = $permissions->keyBy('slug');
        $used = [];
        $sections = [];
        $take = function (?string $slug) use ($bySlug, &$used) {
            if ($slug === null || ! $bySlug->has($slug)) {
                return null;
            }
            $used[$slug] = true;

            return $bySlug->get($slug);
        };
        foreach (self::LAYOUT as $section => $rows) {
            foreach ($rows as $label => $cols) {
                $extra = [];
                foreach ($cols['extra'] ?? [] as $slug => $name) {
                    if ($p = $take($slug)) {
                        $extra[] = [$p, $name];
                    }
                }
                $row = ['label' => $label, 'view' => $take($cols['view'] ?? null), 'create' => $take($cols['create'] ?? null), 'update' => $take($cols['update'] ?? null),
                    'delete' => $take($cols['delete'] ?? null), 'manage' => $take($cols['manage'] ?? null), 'extra' => $extra];
                if ($row['view'] || $row['create'] || $row['update'] || $row['delete'] || $row['manage'] || $extra) {
                    $sections[$section][] = $row;
                }
            }
        }
        $rest = $permissions->reject(fn ($p) => isset($used[$p->slug]))->values();
        if ($rest->isNotEmpty()) {
            $sections['Lainnya'][] = ['label' => 'Izin lain', 'view' => null, 'create' => null, 'update' => null, 'delete' => null, 'manage' => null,
                'extra' => $rest->map(fn ($p) => [$p, $p->name])->all()];
        }

        return $sections;
    }
}
