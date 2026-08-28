<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceFavouriteController extends Controller
{
    public function toggle(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('view', $workspace);

        $isFavourite = $workspace->members()->where('users.id', $request->user()->id)->first()?->pivot->is_favourite;

        $workspace->members()->updateExistingPivot($request->user()->id, ['is_favourite' => ! $isFavourite]);

        return back();
    }
}
