<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Petunjuk Teknis 10.4: Initial selalu, Demo hanya pada environment local.
    public function run(): void
    {
        $this->call(InitialWorkspaceSeeder::class);
        if (app()->environment('local')) {
            $this->call(DemoWorkspaceSeeder::class);
        }
    }
}
