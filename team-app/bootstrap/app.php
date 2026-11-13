<?php

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => \App\Http\Middleware\EnsureUserIsActive::class]);
        // Technical Guide 4.1: every data-changing action is logged to audit_logs.
        $middleware->appendToGroup('web', \App\Http\Middleware\LogUserActions::class.':team');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    // Technical Guide 2.3: schema and initial data are managed only from admin-app. The Team App has no migrations or seeders,
    // and commands that write schema or initial data are refused here so they never run by accident.
    ->booted(function (): void {
        Event::listen(CommandStarting::class, function (CommandStarting $e): void {
            if (preg_match('/^(migrate|db:seed|db:wipe|schema:dump|make:migration|make:seeder|make:factory)\b/', (string) $e->command)) {
                throw new RuntimeException('Team App tidak mengelola database. Jalankan migrasi dan seeder dari admin-app (Petunjuk Teknis 2.3).');
            }
        });
    })->create();
