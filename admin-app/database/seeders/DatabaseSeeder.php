<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Technical Guide 10.4: Initial always, Demo only in the local environment.
    public function run(): void
    {
        $this->call(InitialWorkspaceSeeder::class);
        if (app()->environment('local')) {
            $this->call(DemoWorkspaceSeeder::class);
        }
    }
}
