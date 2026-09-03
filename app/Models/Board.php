<?php

namespace App\Models;

use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'background_color',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class);
    }

    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(Card::class, BoardList::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user')->withPivot(['role', 'is_favourite', 'chat_last_read_at'])->withTimestamps();
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BoardEvent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BoardMessage::class);
    }
}
