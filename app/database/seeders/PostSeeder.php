<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

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
}
