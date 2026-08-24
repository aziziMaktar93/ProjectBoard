<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardEventRequest;
use App\Http\Requests\Boards\StoreGeneralEventRequest;
use App\Http\Requests\Boards\UpdateBoardEventRequest;
use App\Models\Board;
use App\Models\BoardEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardEventController extends Controller
{
    public function store(StoreBoardEventRequest $request, Board $board): RedirectResponse
    {
        $board->events()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function storeGeneral(StoreGeneralEventRequest $request): RedirectResponse
    {
        BoardEvent::create([
            ...$request->validated(),
            'board_id' => null,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function update(UpdateBoardEventRequest $request, BoardEvent $event): RedirectResponse
    {
        $event->update($request->validated());

        return back();
    }

    public function destroy(Request $request, BoardEvent $event): RedirectResponse
    {
        if ($event->board_id === null) {
            abort_unless($event->user_id === $request->user()->id, 403);
        } else {
            Gate::authorize('update', $event->board);
        }

        $event->delete();

        return back();
    }
}
