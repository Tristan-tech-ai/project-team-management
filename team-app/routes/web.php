<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Shared\OperationsController;
use App\Http\Controllers\Shared\TaskActionController;
use App\Http\Controllers\Team\TeamController;
use Illuminate\Support\Facades\Route;

// Team App (mobile-first). Action endpoints are exactly the same as in the Admin App (Shared\*); permissions are checked on the server on every request.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [TeamController::class, 'home'])->name('home');

    Route::middleware('can:tasks.view')->group(function () {
        Route::get('/tasks', [TeamController::class, 'tasks'])->name('tasks.index');
        Route::get('/tasks/data', [TeamController::class, 'tasksData'])->name('tasks.data');
        Route::get('/tasks/create', [TeamController::class, 'create'])->middleware('can:tasks.create')->name('tasks.create');
        Route::get('/tasks/{task}', [TeamController::class, 'show'])->name('tasks.show');
        Route::put('/tasks/{task}', [TaskActionController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskActionController::class, 'destroy'])->name('tasks.destroy');
        Route::patch('/tasks/{task}/status', [TaskActionController::class, 'status'])->name('tasks.status');
        Route::put('/tasks/{task}/assignees', [TaskActionController::class, 'assignees'])->name('tasks.assignees');
        Route::post('/tasks/{task}/subtasks', [TaskActionController::class, 'subtask'])->name('tasks.subtasks.store');
        Route::patch('/subtasks/{subtask}/toggle', [TaskActionController::class, 'toggle'])->name('subtasks.toggle');
        Route::post('/tasks/{task}/submissions', [TaskActionController::class, 'submit'])->name('tasks.submissions.store');
        Route::post('/submissions/{submission}/review', [TaskActionController::class, 'review'])->name('submissions.review');
        Route::post('/tasks/{task}/time-entries', [TaskActionController::class, 'time'])->name('tasks.time.store');
        Route::get('/attachments/{attachment}', [TaskActionController::class, 'download'])->name('attachments.show');
        Route::get('/review', [TeamController::class, 'review'])->middleware('can:tasks.review')->name('review.index');
    });

    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects', [TeamController::class, 'projects'])->name('projects.index');
        Route::get('/projects/{project}', [TeamController::class, 'project'])->name('projects.show');
        Route::get('/projects/{project}/board', [TeamController::class, 'board'])->name('projects.board');
        Route::post('/projects/{project}/tasks', [TaskActionController::class, 'store'])->name('projects.tasks.store');
        Route::get('/projects/{project}/activities/create', [TeamController::class, 'activityCreate'])->name('projects.activities.create');
        Route::post('/projects/{project}/activities', [OperationsController::class, 'activities'])->name('projects.activities.store');
    });

    Route::get('/calendar', [TeamController::class, 'calendar'])->middleware('can:calendar.view')->name('calendar.index');
    Route::get('/calendar/events', [OperationsController::class, 'events'])->middleware('can:calendar.view')->name('calendar.events');
    Route::get('/activities', [TeamController::class, 'activities'])->middleware('can:activities.view')->name('activities.index');
    Route::post('/schedule-changes', [OperationsController::class, 'proposeSchedule'])->name('schedule-changes.store');
    Route::post('/schedule-changes/{scheduleChange}/decision', [OperationsController::class, 'decideSchedule'])->name('schedule-changes.decision');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('/account/photo/{user}', [AccountController::class, 'photo'])->name('account.photo');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
});
