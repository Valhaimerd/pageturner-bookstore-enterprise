<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            CategorySeeder::class,
            BookSeeder::class,
            DemoOrderSeeder::class,
            DemoReviewSeeder::class,
            DemoCartSeeder::class,
            DemoActivitySeeder::class,
        ]);

        if (filter_var(env('MASS_BOOK_SEED_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            $this->call(MassBookSeeder::class);
        }
    }
}
