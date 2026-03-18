<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Short Stories',
                'slug' => 'short-stories',
                'description' => 'Fictional short stories and creative writing.',
            ],
            [
                'name' => 'Reflections',
                'slug' => 'reflections',
                'description' => 'Personal thoughts, musings, and life reflections.',
            ],
            [
                'name' => 'Tech Notes',
                'slug' => 'tech-notes',
                'description' => 'Notes and tutorials about technology and programming.',
            ],
            [
                'name' => 'Travel',
                'slug' => 'travel',
                'description' => 'Travel experiences and destination guides.',
            ],
            [
                'name' => 'Mindfulness',
                'slug' => 'mindfulness',
                'description' => 'Meditation, mindfulness practices, and mental wellness.',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('Categories seeded successfully.');
    }
}
