<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'background_color' => '#0079BF',
            'archived_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Board $board) {
            if (! $board->workspace_id) {
                $board->workspace_id = Workspace::factory()->create(['owner_id' => $board->user_id])->id;
            }
        })->afterCreating(function (Board $board) {
            $board->members()->syncWithoutDetaching([$board->user_id]);
            $board->workspace->members()->syncWithoutDetaching([$board->user_id]);
        });
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
