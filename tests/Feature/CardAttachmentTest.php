<?php

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a board member can upload an attachment to a card', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/attachments", ['file' => $file]);

    $response->assertRedirect();
    expect($card->attachments()->where('name', 'spec.pdf')->exists())->toBeTrue();

    $attachment = $card->attachments()->first();
    Storage::assertExists($attachment->path);

    expect($card->activities()->where('type', 'attachment_added')->exists())->toBeTrue();
});

test('an attachment with a disallowed file type is rejected', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $file = UploadedFile::fake()->create('payload.html', 10, 'text/html');

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/attachments", ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect($card->attachments()->exists())->toBeFalse();
});

test('a non-board-member cannot upload an attachment to a card', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $outsider = User::factory()->create();
    $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');

    $response = $this->actingAs($outsider)->post("/cards/{$card->id}/attachments", ['file' => $file]);

    $response->assertForbidden();
    expect($card->attachments()->count())->toBe(0);
});

test('an attachment upload requires a file', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/attachments", []);

    $response->assertSessionHasErrors('file');
});

test('a board member can view an attachment inline', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);

    $response = $this->actingAs($owner)->get("/attachments/{$attachment->id}/view");

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('a non-board-member cannot view an attachment inline', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->get("/attachments/{$attachment->id}/view");

    $response->assertForbidden();
});

test('a board member can download an attachment', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);

    $response = $this->actingAs($owner)->get("/attachments/{$attachment->id}/download");

    $response->assertOk();
});

test('a non-board-member cannot download an attachment', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->get("/attachments/{$attachment->id}/download");

    $response->assertForbidden();
});

test('a board member can delete an attachment', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);

    $response = $this->actingAs($owner)->delete("/attachments/{$attachment->id}");

    $response->assertRedirect();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::assertMissing('attachments/1/spec.pdf');
    expect($card->activities()->where('type', 'attachment_removed')->exists())->toBeTrue();
});

test('a non-board-member cannot delete an attachment', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Storage::put('attachments/1/spec.pdf', 'contents');
    $attachment = Attachment::factory()->for($card)->create(['path' => 'attachments/1/spec.pdf']);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/attachments/{$attachment->id}");

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
});

test('the board show page includes card attachments', function () {
    Storage::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Attachment::factory()->for($card)->create(['name' => 'spec.pdf']);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.attachments.0.name', 'spec.pdf')
    );
});
