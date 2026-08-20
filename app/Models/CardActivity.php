<?php

namespace App\Models;

use Database\Factories\CardActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardActivity extends Model
{
    /** @use HasFactory<CardActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'card_id',
        'user_id',
        'type',
        'body',
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
