<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technical Guide 4.1: Super Admin "every action is logged". Every data-changing action (POST, PUT, PATCH, DELETE)
 * from the Admin App and the Team App is written here by the LogUserActions middleware; secret fields (passwords, tokens) are never stored.
 * New table through an admin-app migration (Technical Guide 2.3: later schema changes go through admin-app migrations);
 * tables from the module SQL are not changed.
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
