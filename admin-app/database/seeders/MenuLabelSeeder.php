<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * U8 plain language: the module SQL names some menus in technical English. On install they get everyday Indonesian titles,
 * but only while a title still equals the SQL original (a title renamed in Menu settings is never overwritten). Slugs, routes and
 * permissions are untouched, and the 9 menus / 23 submenus stay the same.
 */
class MenuLabelSeeder extends Seeder
{
    /** slug => [title in the module SQL, everyday title] */
    public const LABELS = [
        'projects' => ['Projects', 'Project'],
        'tasks' => ['Tasks', 'Task'],
        'calendar' => ['Calendar', 'Kalender'],
        'activities' => ['Activities', 'Aktivitas'],
        'templates' => ['Templates', 'Template'],
        'management' => ['Management', 'Manajemen'],
        'master-data' => ['Master Data', 'Data Master'],
        'system-settings' => ['System Settings', 'Pengaturan'],
        'management-overview' => ['Management Overview', 'Ringkasan Manajemen'],
        'settings-roles' => ['Roles & Permissions', 'Role & Hak Akses'],
        'settings-menus' => ['Menu Management', 'Pengaturan Menu'],
    ];

    public function run(): void
    {
        foreach (self::LABELS as $slug => [$original, $title]) {
            DB::table('menu_items')->where('slug', $slug)->where('title', $original)->update(['title' => $title, 'updated_at' => now()]);
        }
    }
}
