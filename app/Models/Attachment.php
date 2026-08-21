<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'card_id',
        'user_id',
        'name',
        'path',
        'size',
        'mime_type',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
