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
            ['name' => 'Sci-Fi', 'ar_name' => 'خيال علمي', 'slug' => 'sci-fi'],
            ['name' => 'Fantasy', 'ar_name' => 'فانتازيا', 'slug' => 'fantasy'],
            ['name' => 'Romance', 'ar_name' => 'رومانسي', 'slug' => 'romance'],
            ['name' => 'Mystery', 'ar_name' => 'غموض', 'slug' => 'mystery'],
            ['name' => 'Rain', 'ar_name' => 'مطر', 'slug' => 'rain'],
            ['name' => 'Nature', 'ar_name' => 'طبيعة', 'slug' => 'nature'],
            ['name' => 'City Life', 'ar_name' => 'حياة المدينة', 'slug' => 'city-life'],
            ['name' => 'Morning', 'ar_name' => 'صباح', 'slug' => 'morning'],
            ['name' => 'Evening', 'ar_name' => 'مساء', 'slug' => 'evening'],
            ['name' => 'Laravel', 'ar_name' => 'لارافيل', 'slug' => 'laravel'],
            ['name' => 'PHP', 'ar_name' => 'بي إتش بي', 'slug' => 'php'],
            ['name' => 'JavaScript', 'ar_name' => 'جافاسكريبت', 'slug' => 'javascript'],
            ['name' => 'Tutorial', 'ar_name' => 'درس تعليمي', 'slug' => 'tutorial'],
            ['name' => 'Tips', 'ar_name' => 'نصائح', 'slug' => 'tips'],
            ['name' => 'Meditation', 'ar_name' => 'تأمل', 'slug' => 'meditation'],
            ['name' => 'Gratitude', 'ar_name' => 'امتنان', 'slug' => 'gratitude'],
            ['name' => 'Inspiration', 'ar_name' => 'إلهام', 'slug' => 'inspiration'],
            ['name' => 'Motivation', 'ar_name' => 'تحفيز', 'slug' => 'motivation'],
            ['name' => 'Adventure', 'ar_name' => 'مغامرة', 'slug' => 'adventure'],
            ['name' => 'Mountains', 'ar_name' => 'جبال', 'slug' => 'mountains'],
            ['name' => 'Beach', 'ar_name' => 'شاطئ', 'slug' => 'beach'],
            ['name' => 'Food', 'ar_name' => 'طعام', 'slug' => 'food'],
            ['name' => 'Culture', 'ar_name' => 'ثقافة', 'slug' => 'culture'],
            ['name' => 'History', 'ar_name' => 'تاريخ', 'slug' => 'history'],
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
