<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaces = $request->user()->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name']);
        $workspaceIds = $workspaces->pluck('id');

        $members = User::query()
            ->whereHas('workspaces', fn ($query) => $query->whereIn('workspaces.id', $workspaceIds))
            ->with(['workspaces' => fn ($query) => $query->whereIn('workspaces.id', $workspaceIds)->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'workspaces' => $member->workspaces->map(fn ($workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                ]),
            ]);

        return Inertia::render('Members', [
            'members' => $members,
            'workspaces' => $workspaces,
        ]);
    }
}
