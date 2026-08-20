<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaces = $request->user()->workspaces()
            ->withCount('boards')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $request->user()->ownedWorkspaces()->create($request->validated());
        $workspace->members()->attach($request->user()->id);

        return to_route('workspaces.show', $workspace);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()
            ->whereHas('members', fn ($query) => $query->whereKey($request->user()->id))
            ->whereNull('archived_at')
            ->latest()
            ->get();
        $members = $workspace->members()->orderBy('name')->get();

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'boards' => $boards,
            'members' => $members,
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $workspace->update($request->validated());

        return back();
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return to_route('workspaces.index');
    }
}
