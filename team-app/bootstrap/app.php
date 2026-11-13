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
        $middleware->alias(['aktif' => \App\Http\Middleware\PastikanAktif::class]);
        // Petunjuk Teknis 4.1: semua aksi yang mengubah data dicatat ke audit_logs.
        $middleware->appendToGroup('web', \App\Http\Middleware\CatatAksi::class.':team');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    // Petunjuk Teknis 2.3: schema dan data awal hanya dikelola dari admin-app. Team App tidak punya migrasi atau seeder,
    // dan perintah yang menulis schema atau data awal ditolak di sini supaya tidak terjalankan tanpa sengaja.
    ->booted(function (): void {
        Event::listen(CommandStarting::class, function (CommandStarting $e): void {
            if (preg_match('/^(migrate|db:seed|db:wipe|schema:dump|make:migration|make:seeder|make:factory)\b/', (string) $e->command)) {
                throw new RuntimeException('Team App tidak mengelola database. Jalankan migrasi dan seeder dari admin-app (Petunjuk Teknis 2.3).');
            }
        });
    })->create();
