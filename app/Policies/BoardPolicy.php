<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $board->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Board $board): bool
    {
        if ($user->id === $board->user_id) {
            return true;
        }

        return $board->members()->where('users.id', $user->id)->wherePivot('role', '!=', 'viewer')->exists();
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function manageDueDates(User $user, Board $board): bool
    {
        if ($user->id === $board->user_id) {
            return true;
        }

        return $board->members()->where('users.id', $user->id)->wherePivot('role', 'hod')->exists();
    }
}
