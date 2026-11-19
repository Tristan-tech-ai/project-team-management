<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routine tasks in a project template ("Admin sosmed, every day", "Insight monitoring, every day 10.00-15.00"): the template task
 * is created once per project or period with one checklist item per day. New table through an admin-app migration
 * (Technical Guide 2.3); the module SQL table project_template_tasks is not changed. No foreign key to it: on a new installation
 * the migrations run before the module SQL is imported (README "Memasang dari nol"), so the rows are removed by the app instead
 * (TemplateController when a template's task list is replaced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_task_routines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_template_task_id')->unique();
            $t->string('repeat_rule', 20); // daily | weekdays
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_task_routines');
    }
};
