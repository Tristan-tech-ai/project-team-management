<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings of one project that the module SQL has no column for (PKL supervisor, 26 Nov 2026). result_optional: tasks of this
 * project may be sent for review without a link or a file, for work such as maintenance or research with nothing to hand in.
 * New table through an admin-app migration (Technical Guide 2.3); the module SQL table projects is not changed. No foreign key:
 * on a new installation the migrations run before the module SQL is imported, and projects are soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id')->unique();
            $t->boolean('result_optional')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settings');
    }
};
