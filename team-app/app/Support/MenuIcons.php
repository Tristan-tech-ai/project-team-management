<?php

namespace App\Support;

/**
 * Default icon per submenu route, used when menu_items.icon is empty (the module SQL only sets icons on parent menus).
 * Every destination in the sidebar gets its own recognisable icon; Menu Management can override it.
 */
final class MenuIcons
{
    public const BY_ROUTE = [
        'dashboard.index' => 'layout-dashboard',
        'projects.index' => 'folder-kanban',
        'tasks.index' => 'list-todo',
        'tasks.mine' => 'square-check',
        'tasks.review' => 'clipboard-check',
        'calendar.index' => 'calendar-days',
        'activities.index' => 'activity',
        'activities.meetings' => 'users',
        'activities.impacts' => 'triangle-alert',
        'templates.projects.index' => 'copy',
        'templates.tasks.index' => 'list-checks',
        'management.index' => 'chart-no-axes-combined',
        'management.projects' => 'square-kanban',
        'management.teams' => 'users-round',
        'management.reports' => 'file-text',
        'companies.index' => 'building-2',
        'clients.index' => 'contact',
        'divisions.index' => 'network',
        'positions.index' => 'id-card',
        'members.index' => 'user-cog',
        'settings.roles.index' => 'shield-check',
        'settings.menus.index' => 'layout-grid',
        'settings.general' => 'sliders-horizontal',
    ];

    public static function forRoute(?string $route, ?string $fallback = null): string
    {
        return self::BY_ROUTE[$route] ?? $fallback ?? 'folder';
    }
}
