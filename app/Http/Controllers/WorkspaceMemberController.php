<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    public function search(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('update', $workspace);

        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['users' => []]);
        }

        $existingMemberIds = $workspace->members()->pluck('users.id');

        $users = User::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->whereNotIn('id', $existingMemberIds)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $workspace->members()->syncWithoutDetaching([$validated['user_id']]);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser->id === $workspace->owner_id || $currentUser->id === $user->id,
            403
        );

        abort_if($user->id === $workspace->owner_id, 422, 'The workspace owner cannot be removed.');

        $workspace->members()->detach($user->id);

        return back();
    }
}
