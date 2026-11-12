<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManagementController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\AkunController;
use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\Bersama\OperasionalController;
use App\Http\Controllers\Bersama\TaskAksiController;
use App\Http\Controllers\Master\ClientController;
use App\Http\Controllers\Master\CompanyController;
use App\Http\Controllers\Master\DivisionController;
use App\Http\Controllers\Master\MemberController;
use App\Http\Controllers\Master\PositionController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\GeneralSettingController;
use App\Http\Controllers\Settings\MenuController;
use App\Http\Controllers\Settings\RolePermissionController;
use Illuminate\Support\Facades\Route;

// Admin App (desktop-first). Setiap route diperiksa izinnya di server (can:/Policy); menu hanya navigasi.
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [MasukController::class, 'create'])->name('login');
    Route::post('/masuk', [MasukController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'aktif'])->group(function () {
    Route::post('/keluar', [MasukController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect()->route('dashboard.index'))->name('beranda');

    // 1. Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('can:dashboard.view')->name('dashboard.index');

    // 2. Projects
    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/data', [ProjectController::class, 'data'])->name('projects.data');
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::get('/projects/pratinjau-template', [ProjectController::class, 'preview'])->name('projects.preview');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('/projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
        Route::get('/projects/{project}/tasks', [ProjectController::class, 'tasks'])->name('projects.tasks');
        Route::get('/projects/{project}/tasks/data', [ProjectController::class, 'tasksData'])->name('projects.tasks.data');
        Route::get('/projects/{project}/team', [ProjectController::class, 'team'])->name('projects.team');
        Route::put('/projects/{project}/team', [ProjectController::class, 'updateTeam'])->name('projects.team.update');
        Route::get('/projects/{project}/activities', [ProjectController::class, 'activities'])->name('projects.activities');
        Route::get('/projects/{project}/calendar', [ProjectController::class, 'calendar'])->name('projects.calendar');
        Route::post('/projects/{project}/periods', [ProjectController::class, 'periode'])->name('projects.periods.store');
        Route::post('/projects/{project}/tasks', [TaskAksiController::class, 'store'])->name('projects.tasks.store');
        Route::post('/projects/{project}/activities', [OperasionalController::class, 'aktivitas'])->name('projects.activities.store');
    });

    // 3. Tasks
    Route::middleware('can:tasks.view')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/data', [TaskController::class, 'data'])->name('tasks.data');
        Route::get('/tasks/saya', [TaskController::class, 'mine'])->name('tasks.mine');
        Route::get('/tasks/saya/data', [TaskController::class, 'mineData'])->name('tasks.mine.data');
        Route::get('/tasks/review', [TaskController::class, 'review'])->middleware('can:tasks.review')->name('tasks.review');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::put('/tasks/{task}', [TaskAksiController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskAksiController::class, 'destroy'])->name('tasks.destroy');
        Route::patch('/tasks/{task}/status', [TaskAksiController::class, 'status'])->name('tasks.status');
        Route::put('/tasks/{task}/assignees', [TaskAksiController::class, 'assignees'])->name('tasks.assignees');
        Route::post('/tasks/{task}/subtasks', [TaskAksiController::class, 'subtask'])->name('tasks.subtasks.store');
        Route::patch('/subtasks/{subtask}/toggle', [TaskAksiController::class, 'toggle'])->name('subtasks.toggle');
        Route::post('/tasks/{task}/submissions', [TaskAksiController::class, 'submit'])->name('tasks.submissions.store');
        Route::post('/submissions/{submission}/review', [TaskAksiController::class, 'review'])->name('submissions.review');
        Route::post('/tasks/{task}/waktu', [TaskAksiController::class, 'waktu'])->name('tasks.time.store');
        Route::get('/lampiran/{attachment}', [TaskAksiController::class, 'unduh'])->name('attachments.show');
    });

    // 4. Calendar
    Route::get('/calendar', fn () => view('admin.calendar'))->middleware('can:calendar.view')->name('calendar.index');
    Route::get('/calendar/events', [OperasionalController::class, 'events'])->middleware('can:calendar.view')->name('calendar.events');

    // 5. Activities
    Route::middleware('can:activities.view')->group(function () {
        Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
        Route::get('/activities/data', [ActivityController::class, 'data'])->name('activities.data');
        Route::get('/activities/rapat-kunjungan', [ActivityController::class, 'meetings'])->name('activities.meetings');
        Route::get('/activities/hambatan-perubahan', [ActivityController::class, 'impacts'])->name('activities.impacts');
    });
    Route::post('/schedule-changes', [OperasionalController::class, 'ajukanJadwal'])->name('schedule-changes.store');
    Route::post('/schedule-changes/{scheduleChange}/decision', [OperasionalController::class, 'putuskanJadwal'])->name('schedule-changes.decision');

    // 6. Templates
    Route::middleware('can:templates.view')->group(function () {
        Route::get('/templates/projects', [TemplateController::class, 'projects'])->name('templates.projects.index');
        Route::get('/templates/projects/data', [TemplateController::class, 'projectsData'])->name('templates.projects.data');
        Route::get('/templates/projects/create', [TemplateController::class, 'createProject'])->name('templates.projects.create');
        Route::post('/templates/projects', [TemplateController::class, 'storeProject'])->name('templates.projects.store');
        Route::get('/templates/projects/{template}/edit', [TemplateController::class, 'editProject'])->name('templates.projects.edit');
        Route::put('/templates/projects/{template}', [TemplateController::class, 'updateProject'])->name('templates.projects.update');
        Route::get('/templates/tasks', [TemplateController::class, 'tasks'])->name('templates.tasks.index');
        Route::get('/templates/tasks/data', [TemplateController::class, 'tasksData'])->name('templates.tasks.data');
        Route::get('/templates/tasks/create', [TemplateController::class, 'createTask'])->name('templates.tasks.create');
        Route::post('/templates/tasks', [TemplateController::class, 'storeTask'])->name('templates.tasks.store');
        Route::get('/templates/tasks/{template}/edit', [TemplateController::class, 'editTask'])->name('templates.tasks.edit');
        Route::put('/templates/tasks/{template}', [TemplateController::class, 'updateTask'])->name('templates.tasks.update');
    });

    // 7. Management
    Route::middleware('can:management.view')->group(function () {
        Route::get('/management', [ManagementController::class, 'index'])->name('management.index');
        Route::get('/management/projects', [ManagementController::class, 'projects'])->name('management.projects');
        Route::get('/management/teams', [ManagementController::class, 'teams'])->name('management.teams');
        Route::get('/management/reports', [ManagementController::class, 'reports'])->name('management.reports');
        Route::get('/management/export', [ManagementController::class, 'export'])->middleware('can:management.export')->name('management.export');
    });

    // 8. Master Data
    foreach ([['companies', CompanyController::class, 'company'], ['clients', ClientController::class, 'client'], ['divisions', DivisionController::class, 'division'],
        ['positions', PositionController::class, 'position'], ['members', MemberController::class, 'member']] as [$nama, $kelas, $param]) {
        Route::middleware("can:{$nama}.view")->group(function () use ($nama, $kelas, $param) {
            Route::get("/master/{$nama}/data", [$kelas, 'data'])->name("{$nama}.data");
            Route::resource("master/{$nama}", $kelas)->except(['show'])->names($nama)->parameters(["master/{$nama}" => $param]);
        });
    }
    Route::patch('/master/divisions/{division}/toggle', [DivisionController::class, 'toggle'])->middleware('can:divisions.manage')->name('divisions.toggle');
    Route::patch('/master/positions/{position}/toggle', [PositionController::class, 'toggle'])->middleware('can:positions.manage')->name('positions.toggle');

    // 9. System Settings
    Route::get('/settings/roles', [RolePermissionController::class, 'index'])->middleware('can:settings.roles.view')->name('settings.roles.index');
    Route::put('/settings/roles/{role}', [RolePermissionController::class, 'update'])->middleware('can:settings.roles.manage')->name('settings.roles.update');
    Route::get('/settings/menus', [MenuController::class, 'index'])->middleware('can:settings.menus.manage')->name('settings.menus.index');
    Route::put('/settings/menus', [MenuController::class, 'update'])->middleware('can:settings.menus.manage')->name('settings.menus.update');
    Route::get('/settings/general', [GeneralSettingController::class, 'edit'])->middleware('can:settings.system.manage')->name('settings.general');
    Route::put('/settings/general', [GeneralSettingController::class, 'update'])->middleware('can:settings.system.manage')->name('settings.general.update');
    Route::get('/settings/audit', [AuditLogController::class, 'index'])->middleware('can:settings.system.manage')->name('settings.audit');

    // Akun
    Route::get('/akun', [AkunController::class, 'edit'])->name('akun.edit');
    Route::put('/akun', [AkunController::class, 'update'])->name('akun.update');
    Route::put('/akun/password', [AkunController::class, 'password'])->name('akun.password');
});
