<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Petunjuk Teknis 4.1: Super Admin "semua aksi dicatat". Setiap aksi yang mengubah data (POST, PUT, PATCH, DELETE)
 * dari Admin App dan Team App dicatat di sini oleh middleware CatatAksi; isian rahasia (kata sandi, token) tidak disimpan.
 * Tabel baru lewat migrasi admin-app (Petunjuk Teknis 2.3: perubahan schema berikutnya lewat migrasi admin-app);
 * tabel dari SQL modul tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('role', 30)->nullable();
            $t->string('app', 20);
            $t->string('method', 10);
            $t->string('route', 120)->nullable();
            $t->string('path', 255);
            $t->json('input')->nullable();
            $t->unsignedSmallInteger('status');
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
