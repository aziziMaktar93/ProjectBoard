<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Card;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function archived(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->get();

        return Inertia::render('boards/Archived', [
            'workspace' => $workspace,
            'boards' => $boards,
        ]);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace): RedirectResponse
    {
        $board = $workspace->boards()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $board->members()->attach($request->user()->id);

        return to_route('boards.show', $board);
    }

    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'workspace.members',
            'members' => fn ($query) => $query->orderBy('name'),
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards.checklists' => fn ($query) => $query->orderBy('position'),
            'lists.cards.checklists.items' => fn ($query) => $query->orderBy('position'),
        ]);

        $archivedLists = $board->lists()->whereNotNull('archived_at')->orderByDesc('archived_at')->get();
        $archivedCards = Card::whereIn('board_list_id', $board->lists()->pluck('id'))
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get();

        return Inertia::render('boards/Show', [
            'board' => $board,
            'archivedLists' => $archivedLists,
            'archivedCards' => $archivedCards,
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $board->update($request->validated());

        return back();
    }

    public function archive(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => now()]);

        return to_route('workspaces.show', $board->workspace_id);
    }

    public function restore(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => null]);

        return to_route('boards.archived', $board->workspace_id);
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        abort_if($board->archived_at === null, 422, 'Only archived boards can be permanently deleted.');

        $board->delete();

        return to_route('boards.archived', $board->workspace_id);
    }
}
