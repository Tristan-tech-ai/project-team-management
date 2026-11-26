<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which companies work with which clients (PKL supervisor, 26 Nov 2026): picking a company in the project form lists only its
 * clients. A client without any row here is general (umum) and stays available to every company, so the module SQL rows and the
 * Technical Guide rule "Client adalah master terpisah" keep working. New table through an admin-app migration (Technical Guide 2.3);
 * the module SQL tables companies and clients are not changed. No foreign keys: on a new installation the migrations run before
 * the module SQL is imported, and both masters are soft-deleted rather than removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_company', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('company_id');
            $t->timestamps();
            $t->unique(['client_id', 'company_id']);
            $t->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_company');
    }
};
