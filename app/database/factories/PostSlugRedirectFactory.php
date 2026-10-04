<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostSlugRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostSlugRedirect>
 */
class PostSlugRedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'old_slug' => fake()->unique()->slug(2),
        ];
    }
}
