<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardFavouriteController extends Controller
{
    public function toggle(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('view', $board);

        $isFavourite = $board->members()->where('users.id', $request->user()->id)->first()?->pivot->is_favourite;

        $board->members()->updateExistingPivot($request->user()->id, ['is_favourite' => ! $isFavourite]);

        return back();
    }
}
