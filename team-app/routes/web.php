<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\Bersama\OperasionalController;
use App\Http\Controllers\Bersama\TaskAksiController;
use App\Http\Controllers\Tim\TimController;
use Illuminate\Support\Facades\Route;

// Team App (mobile-first). Endpoint aksi sama persis dengan Admin (Bersama\*), izin diperiksa di server setiap request.
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [MasukController::class, 'create'])->name('login');
    Route::post('/masuk', [MasukController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'aktif'])->group(function () {
    Route::post('/keluar', [MasukController::class, 'destroy'])->name('logout');
    Route::get('/', [TimController::class, 'beranda'])->name('beranda');

    Route::middleware('can:tasks.view')->group(function () {
        Route::get('/tasks', [TimController::class, 'tasks'])->name('tasks.index');
        Route::get('/tasks/baru', [TimController::class, 'create'])->middleware('can:tasks.create')->name('tasks.create');
        Route::get('/tasks/{task}', [TimController::class, 'show'])->name('tasks.show');
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
        Route::get('/review', [TimController::class, 'review'])->middleware('can:tasks.review')->name('review.index');
    });

    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects', [TimController::class, 'projects'])->name('projects.index');
        Route::get('/projects/{project}', [TimController::class, 'project'])->name('projects.show');
        Route::get('/projects/{project}/board', [TimController::class, 'board'])->name('projects.board');
        Route::post('/projects/{project}/tasks', [TaskAksiController::class, 'store'])->name('projects.tasks.store');
        Route::get('/projects/{project}/activities/baru', [TimController::class, 'activityCreate'])->name('projects.activities.create');
        Route::post('/projects/{project}/activities', [OperasionalController::class, 'aktivitas'])->name('projects.activities.store');
    });

    Route::get('/kalender', [TimController::class, 'calendar'])->middleware('can:calendar.view')->name('calendar.index');
    Route::get('/calendar/events', [OperasionalController::class, 'events'])->middleware('can:calendar.view')->name('calendar.events');
    Route::get('/aktivitas', [TimController::class, 'activities'])->middleware('can:activities.view')->name('activities.index');
    Route::post('/schedule-changes', [OperasionalController::class, 'ajukanJadwal'])->name('schedule-changes.store');
    Route::post('/schedule-changes/{scheduleChange}/decision', [OperasionalController::class, 'putuskanJadwal'])->name('schedule-changes.decision');

    Route::get('/akun', [AkunController::class, 'edit'])->name('akun.edit');
    Route::put('/akun', [AkunController::class, 'update'])->name('akun.update');
    Route::put('/akun/password', [AkunController::class, 'password'])->name('akun.password');
});
