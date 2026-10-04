<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            // ar_name must never be null: the dashboard category select plucks
            // the locale column and a null label crashes Filament in ar locale.
            'ar_name' => fake()->randomElement(['خواطر', 'تقنية', 'أدب', 'سرد', 'ثقافة', 'يوميات', 'قراءات', 'تأملات']).' '.$name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'ar_description' => null,
        ];
    }

    /**
     * Indicate that the category is soft deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }
}
