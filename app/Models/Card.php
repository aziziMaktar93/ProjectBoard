<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_list_id',
        'name',
        'description',
        'position',
        'color',
        'due_date',
        'archived_at',
        'cover_attachment_id',
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

    public function boardList(): BelongsTo
    {
        return $this->belongsTo(BoardList::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CardActivity::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_user')->withTimestamps();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label')->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function coverAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'cover_attachment_id');
    }
}
