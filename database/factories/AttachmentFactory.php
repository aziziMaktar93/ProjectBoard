<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'user_id' => User::factory(),
            'name' => fake()->word().'.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'size' => fake()->numberBetween(1024, 5_000_000),
            'mime_type' => 'application/pdf',
        ];
    }
}
