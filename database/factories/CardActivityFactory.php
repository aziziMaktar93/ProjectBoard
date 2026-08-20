<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardActivity>
 */
class CardActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'user_id' => User::factory(),
            'type' => 'comment',
            'body' => fake()->sentence(),
            'data' => null,
        ];
    }
}
