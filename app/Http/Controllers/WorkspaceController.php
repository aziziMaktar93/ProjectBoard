<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Board;
use App\Models\Card;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $workspaces = $request->user()->workspaces()
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->addSelect([
                'is_favourite' => DB::table('workspace_user')
                    ->select('is_favourite')
                    ->whereColumn('workspace_user.workspace_id', 'workspaces.id')
                    ->where('workspace_user.user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->withCount('boards')
            ->orderByDesc('is_favourite')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $workspaces->each(function (Workspace $workspace) {
            $workspace->is_favourite = (bool) $workspace->is_favourite;
        });

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
            'filters' => ['search' => $search],
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

        $search = trim((string) $request->string('search'));

        $boards = $workspace->boards()
            ->whereHas('members', fn ($query) => $query->whereKey($request->user()->id))
            ->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->addSelect([
                'is_favourite' => DB::table('board_user')
                    ->select('is_favourite')
                    ->whereColumn('board_user.board_id', 'boards.id')
                    ->where('board_user.user_id', $request->user()->id)
                    ->limit(1),
            ])
            ->withCount(['cards' => fn ($query) => $query->whereNull('cards.archived_at')])
            ->with([
                'members' => fn ($query) => $query->orderBy('name'),
                'cards' => fn ($query) => $query->whereNull('cards.archived_at'),
                'cards.checklists.items',
            ])
            ->orderByDesc('is_favourite')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $boards->each(function (Board $board) {
            $items = $board->cards->flatMap(fn (Card $card) => $card->checklists)->flatMap(fn ($checklist) => $checklist->items);

            $board->checklist_progress = $items->isEmpty()
                ? null
                : (int) round($items->filter(fn ($item) => $item->is_checked)->count() / $items->count() * 100);

            $board->is_favourite = (bool) $board->is_favourite;

            $board->unsetRelation('cards');
        });

        $members = $workspace->members()->orderBy('name')->get();

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'boards' => $boards,
            'members' => $members,
            'filters' => ['search' => $search],
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
