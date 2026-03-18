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
                'ar_name' => 'قصص قصيرة',
                'slug' => 'short-stories',
                'description' => 'Fictional short stories and creative writing.',
                'ar_description' => 'قصص خيالية قصيرة وكتابة إبداعية.',
            ],
            [
                'name' => 'Reflections',
                'ar_name' => 'تأملات',
                'slug' => 'reflections',
                'description' => 'Personal thoughts, musings, and life reflections.',
                'ar_description' => 'أفكار شخصية وتأملات في الحياة.',
            ],
            [
                'name' => 'Tech Notes',
                'ar_name' => 'ملاحظات تقنية',
                'slug' => 'tech-notes',
                'description' => 'Notes and tutorials about technology and programming.',
                'ar_description' => 'ملاحظات ودروس حول التكنولوجيا والبرمجة.',
            ],
            [
                'name' => 'Travel',
                'ar_name' => 'سفر',
                'slug' => 'travel',
                'description' => 'Travel experiences and destination guides.',
                'ar_description' => 'تجارب السفر وأدلة الوجهات.',
            ],
            [
                'name' => 'Mindfulness',
                'ar_name' => 'تأمل',
                'slug' => 'mindfulness',
                'description' => 'Meditation, mindfulness practices, and mental wellness.',
                'ar_description' => 'تأمل وممارسات اليقظة الذهنية والصحة النفسية.',
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
