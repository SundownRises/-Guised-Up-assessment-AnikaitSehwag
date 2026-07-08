<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        // Generate a fixed 384-dim mock embedding
        $embedding = array_map(fn($i) => round(sin($i * 0.1) * 0.3, 6), range(0, 383));

        return [
            'user_id' => User::factory(),
            'text' => fake()->paragraph(3),
            'image_url' => null,
            'authenticity_score' => fake()->randomFloat(2, 0.5, 1.0),
            'embedding' => $embedding,
        ];
    }
}
