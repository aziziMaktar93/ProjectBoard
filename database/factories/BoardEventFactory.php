<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardEvent>
 */
class BoardEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'start_date' => fake()->dateTimeBetween('-1 week', '+1 month')->format('Y-m-d'),
            'end_date' => null,
            'color' => fake()->randomElement([
                '#4bce97', '#f5cd47', '#fea362', '#f87168', '#9f8fef',
                '#579dff', '#6cc3e0', '#94c748', '#e774bb', '#8590a2',
            ]),
        ];
    }
}
