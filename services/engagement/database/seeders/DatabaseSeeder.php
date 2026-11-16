<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // The demo data of seeds/demo.json (task 1289): README, Demo data.
        $this->call([DemoSeeder::class]);
    }
}
