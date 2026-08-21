<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Board;
use App\Models\Card;
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
            ->withCount(['cards' => fn ($query) => $query->whereNull('cards.archived_at')])
            ->with([
                'members' => fn ($query) => $query->orderBy('name'),
                'cards' => fn ($query) => $query->whereNull('cards.archived_at'),
                'cards.checklists.items',
            ])
            ->latest()
            ->get();

        $boards->each(function (Board $board) {
            $items = $board->cards->flatMap(fn (Card $card) => $card->checklists)->flatMap(fn ($checklist) => $checklist->items);

            $board->checklist_progress = $items->isEmpty()
                ? null
                : (int) round($items->filter(fn ($item) => $item->is_checked)->count() / $items->count() * 100);

            $board->unsetRelation('cards');
        });

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
