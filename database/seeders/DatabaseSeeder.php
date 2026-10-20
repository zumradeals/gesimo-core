<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Au Lot 1, seules les données DÉMO existent ; elles refusent de se charger en production. */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
