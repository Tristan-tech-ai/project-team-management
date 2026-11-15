<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Technical Guide 10.4: Initial always, Demo only in the local environment. MenuLabelSeeder: everyday menu titles (U8).
    public function run(): void
    {
        $this->call(InitialWorkspaceSeeder::class);
        $this->call(MenuLabelSeeder::class);
        if (app()->environment('local')) {
            $this->call(DemoWorkspaceSeeder::class);
        }
    }
}
