<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
            ['name' => 'Fantasy', 'slug' => 'fantasy'],
            ['name' => 'Romance', 'slug' => 'romance'],
            ['name' => 'Mystery', 'slug' => 'mystery'],
            ['name' => 'Rain', 'slug' => 'rain'],
            ['name' => 'Nature', 'slug' => 'nature'],
            ['name' => 'City Life', 'slug' => 'city-life'],
            ['name' => 'Morning', 'slug' => 'morning'],
            ['name' => 'Evening', 'slug' => 'evening'],
            ['name' => 'Laravel', 'slug' => 'laravel'],
            ['name' => 'PHP', 'slug' => 'php'],
            ['name' => 'JavaScript', 'slug' => 'javascript'],
            ['name' => 'Tutorial', 'slug' => 'tutorial'],
            ['name' => 'Tips', 'slug' => 'tips'],
            ['name' => 'Meditation', 'slug' => 'meditation'],
            ['name' => 'Gratitude', 'slug' => 'gratitude'],
            ['name' => 'Inspiration', 'slug' => 'inspiration'],
            ['name' => 'Motivation', 'slug' => 'motivation'],
            ['name' => 'Adventure', 'slug' => 'adventure'],
            ['name' => 'Mountains', 'slug' => 'mountains'],
            ['name' => 'Beach', 'slug' => 'beach'],
            ['name' => 'Food', 'slug' => 'food'],
            ['name' => 'Culture', 'slug' => 'culture'],
            ['name' => 'History', 'slug' => 'history'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(
                ['slug' => $tag['slug']],
                $tag
            );
        }

        $this->command->info('Tags seeded successfully.');
    }
}
