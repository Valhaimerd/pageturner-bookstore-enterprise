<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fiction',
                'slug' => 'fiction',
                'description' => 'Stories, novels, and literary works.',
                'is_active' => true,
            ],
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Programming, software, and IT books.',
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Entrepreneurship, finance, and management.',
                'is_active' => true,
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'Physics, biology, chemistry, and general science.',
                'is_active' => true,
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Learning resources, study skills, and teaching materials.',
                'is_active' => true,
            ],
            [
                'name' => 'History',
                'slug' => 'history',
                'description' => 'Historical events, biographies, and civilizations.',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
