<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Models\Post;
use App\Models\PostRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostRevision>
 */
class PostRevisionFactory extends Factory
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
            'version' => 1,
            'base_version' => null,
            'state' => RevisionState::Applied,
            'source' => RevisionSource::Dashboard,
            'client_name' => null,
            'note' => null,
            'unguarded' => false,
            'title' => fake()->sentence(),
            'content_html' => '<p>'.fake()->paragraph().'</p>',
            'excerpt' => null,
            'slug' => fake()->unique()->slug(2),
            'status' => PostStatus::Draft,
            'edits' => null,
        ];
    }

    /**
     * Indicate that the revision is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RevisionState::Pending,
            'version' => null,
            'base_version' => 1,
        ]);
    }
}
