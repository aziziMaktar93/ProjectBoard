<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->word(),
            'color' => fake()->randomElement([
                '#4bce97', '#f5cd47', '#fea362', '#f87168', '#9f8fef',
                '#579dff', '#6cc3e0', '#94c748', '#e774bb', '#8590a2',
            ]),
        ];
    }
}
