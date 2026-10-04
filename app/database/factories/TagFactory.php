<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            // See CategoryFactory: ar_name must never be null.
            'ar_name' => fake()->randomElement(['خاطرة', 'تقنية', 'أدب', 'سرد', 'ثقافة', 'يوميات', 'قراءة', 'تأمل']).' '.$name,
            'slug' => Str::slug($name),
        ];
    }

    /**
     * Indicate that the tag is soft deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }
}
