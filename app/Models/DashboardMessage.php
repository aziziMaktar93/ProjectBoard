<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'dashboard_conversation_id',
        'role',
        'content',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DashboardConversation::class, 'dashboard_conversation_id');
    }
}
