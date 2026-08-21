<?php

namespace App\Models;

use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'card_id',
        'name',
        'position',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
