<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Models\Board;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $boards = $request->user()->boards()
            ->whereNull('archived_at')
            ->latest()
            ->get();

        return Inertia::render('boards/Index', [
            'boards' => $boards,
        ]);
    }

    public function archived(Request $request): Response
    {
        $boards = $request->user()->boards()
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->get();

        return Inertia::render('boards/Archived', [
            'boards' => $boards,
        ]);
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $board = $request->user()->boards()->create($request->validated());

        return to_route('boards.show', $board);
    }

    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
        ]);

        return Inertia::render('boards/Show', [
            'board' => $board,
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

        return to_route('boards.index');
    }

    public function restore(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => null]);

        return to_route('boards.archived');
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        abort_if($board->archived_at === null, 422, 'Only archived boards can be permanently deleted.');

        $board->delete();

        return to_route('boards.archived');
    }
}
