<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardAttachmentRequest;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\CardActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CardAttachmentController extends Controller
{
    public function store(StoreCardAttachmentRequest $request, Card $card): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store("attachments/{$card->id}");

        $attachment = $card->attachments()->create([
            'user_id' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'attachment_added',
            'data' => ['attachment_name' => $attachment->name],
        ]);

        return back();
    }

    public function view(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->card->boardList->board);

        return Storage::response($attachment->path, $attachment->name);
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->card->boardList->board);

        return Storage::download($attachment->path, $attachment->name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $attachment->card->boardList->board);

        Storage::delete($attachment->path);

        CardActivity::create([
            'card_id' => $attachment->card_id,
            'user_id' => $request->user()->id,
            'type' => 'attachment_removed',
            'data' => ['attachment_name' => $attachment->name],
        ]);

        $attachment->delete();

        return back();
    }
}
