<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardLabelRequest;
use App\Http\Requests\Boards\UpdateBoardLabelRequest;
use App\Models\Board;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardLabelController extends Controller
{
    public function store(StoreBoardLabelRequest $request, Board $board): RedirectResponse
    {
        $board->labels()->create($request->validated());

        return back();
    }

    public function update(UpdateBoardLabelRequest $request, Label $label): RedirectResponse
    {
        $label->update($request->validated());

        return back();
    }

    public function destroy(Request $request, Label $label): RedirectResponse
    {
        Gate::authorize('update', $label->board);

        $label->delete();

        return back();
    }
}
