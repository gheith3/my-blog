<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $categories = Category::all();
        $tags = Tag::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UsersSeeder first.');

            return;
        }

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');

            return;
        }

        // Create Arabic posts first
        $this->createArabicPosts($users->first(), $categories, $tags);

        // Create 20 published posts
        Post::factory(20)
            ->published()
            ->recycle($users)
            ->recycle($categories)
            ->create()
            ->each(function ($post) use ($tags) {
                $post->tags()->attach(
                    $tags->random(rand(2, 5))->pluck('id')
                );
            });

        // Create 10 draft posts
        Post::factory(10)
            ->draft()
            ->recycle($users)
            ->recycle($categories)
            ->create()
            ->each(function ($post) use ($tags) {
                $post->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')
                );
            });

        // Create 5 archived posts
        Post::factory(5)
            ->archived()
            ->recycle($users)
            ->recycle($categories)
            ->create()
            ->each(function ($post) use ($tags) {
                $post->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')
                );
            });

        $publishedCount = Post::where('status', PostStatus::Published)->count();
        $draftCount = Post::where('status', PostStatus::Draft)->count();
        $archivedCount = Post::where('status', PostStatus::Archived)->count();

        $this->command->info("Posts seeded successfully: {$publishedCount} published, {$draftCount} drafts, {$archivedCount} archived.");
    }

    /**
     * Create Arabic posts
     */
    private function createArabicPosts(User $user, $categories, $tags): void
    {
        $arabicPosts = [
            [
                'title' => 'رحلتي في عالم البرمجة',
                'content' => '<p>منذ أن كنت صغيراً، كنت مهتماً بالتكنولوجيا والحواسيب. كانت البرمجة بالنسبة لي مجالاً ساحراً يتيح لي تحويل الأفكار إلى واقع.</p><p>بدأت رحلتي مع لغة PHP، ثم انتقلت إلى Laravel الذي غيّر طريقة تفكيري في تطوير تطبيقات الويب. اليوم، أشارك معكم بعض الدروس التي تعلمتها خلال هذه الرحلة.</p><h3>أهم الدروس</h3><ul><li>التعلم المستمر هو المفتاح</li><li>لا تخف من ارتكاب الأخطاء</li><li>شارك معرفتك مع الآخرين</li></ul>',
                'category_slug' => 'tech-notes',
                'tag_slugs' => ['laravel', 'php', 'tutorial'],
            ],
            [
                'title' => 'صباح هادئ مع القهوة',
                'content' => '<p>أحب تلك اللحظات الصباحية حيث يكون العالم هادئاً والجميع نائمين. أجلس على الشرفة بفنجان قهوتي، أستمع إلى صوت العصافير وأشاهب شروق الشمس.</p><p>هذه اللحظات البسيطة هي ما يمنحني الطاقة لبقية اليوم. إنها فرصة للتأمل والتفكير في أهدافي وطموحاتي.</p><blockquote><p>"السعادة تكمن في اللحظات الصغيرة التي نعيشها بكل وعي."</p></blockquote>',
                'category_slug' => 'reflections',
                'tag_slugs' => ['morning', 'nature', 'meditation'],
            ],
            [
                'title' => 'قصة قصيرة: الغريب في المطر',
                'content' => '<p>كان يوماً ممطراً في مدينة غريبة. وقفت على حافة الجسر، أشاهب قطرات المطر تنساب على سطح النهر. لم أكن أعرف أحداً هنا، ولم يعرفني أحد.</p><p>فجأة، اقترب رجل عجوز يحمل مظلة كبيرة. ابتسم لي وقال: "المطر جميل، أليس كذلك؟"</p><p>كانت تلك البداية لصداقة غير متوقعة في أغرب الأوقات.</p>',
                'category_slug' => 'short-stories',
                'tag_slugs' => ['rain', 'mystery', 'inspiration'],
            ],
            [
                'title' => 'رحلة إلى الجبال',
                'content' => '<p>قبل شهر، قررت أن أترك ضوضاء المدينة خلفي وأتجه إلى الجبال. كنت أحتاج إلى الهدوء والانعزال عن العالم الرقمي.</p><p>المشي لساعات بين الأشجار، والتنفس في الهواء النقي، والنوم تحت النجوم... كل هذا أعاد لي توازني وطاقتي.</p><h3>نصائح للمسافرين</h3><ol><li>حضّر معداتك بعناية</li><li>احترم الطبيعة</li><li>خذ وقتك للاستمتاع بالمناظر</li></ol>',
                'category_slug' => 'travel',
                'tag_slugs' => ['mountains', 'adventure', 'nature'],
            ],
            [
                'title' => 'فن الامتنان',
                'content' => '<p>في عالمنا السريع، ننسى أحياناً أن نتوقف ونشكر على النعم التي نتمتع بها. الصحة، العائلة، الأصدقاء، العمل... كل هذه نعم لا تقدر بثمن.</p><p>بدأت منذ فترة ممارسة يومية بسيطة: قبل النوم، أكتب ثلاثة أشياء أشعر بالامتنان لوجودها في حياتي. هذه العادة غيّرت نظرتي للحياة.</p><p>جربها بنفسك، وسترى الفرق.</p>',
                'category_slug' => 'mindfulness',
                'tag_slugs' => ['gratitude', 'meditation', 'motivation'],
            ],
        ];

        foreach ($arabicPosts as $postData) {
            $category = $categories->firstWhere('slug', $postData['category_slug']);
            $postTags = $tags->whereIn('slug', $postData['tag_slugs']);

            if ($category) {
                $post = Post::firstOrCreate(
                    ['slug' => Str::slug($postData['title'])],
                    [
                        'user_id' => $user->id,
                        'category_id' => $category->id,
                        'title' => $postData['title'],
                        'content' => $postData['content'],
                        'status' => PostStatus::Published,
                        'published_at' => now()->subDays(rand(1, 30)),
                    ]
                );

                $post->tags()->sync($postTags->pluck('id'));
            }
        }

        $this->command->info('Arabic posts seeded successfully.');
    }
}
