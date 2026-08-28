# Dashboard AI Assistant (Chatbox) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only Q&A + board-linking AI chat widget to the Dashboard page, sharing Gemini infrastructure with the existing board assistant but with no create/apply capability of its own.

**Architecture:** A new `DashboardChatController` (JSON endpoints, mirroring the board assistant's `show`/`sendMessage` shape) backed by two new tables (`dashboard_conversations`, `dashboard_messages` — one conversation per user, no board dimension). It calls a new `GeminiClient::converse()` method (plain text, no function-calling) built from a system prompt assembled from a new `DashboardStatsService` (extracted from `DashboardController`) plus the user's full board list. The frontend is a second floating chat bubble, `DashboardChatWidget.vue`, mounted only on `Dashboard.vue`, which client-side-renders `[[Board Name]]` markers in replies as links.

**Tech Stack:** Laravel 12 (PHP 8.2), Pest, Vue 3 + TypeScript, Inertia v2, the existing `App\Services\GeminiClient` (Google Gemini REST API).

## Global Constraints

- No create/edit/delete capability from this chat — Q&A and board-linking only. There is no "propose then apply" flow and no function-calling/tool declarations for this feature.
- The AI's context is always the user's **full, unfiltered** data (every board across every workspace the user belongs to) — independent of the Dashboard page's current workspace/board filter.
- One ongoing conversation per **user** (no board dimension) — no multi-thread UI.
- The widget is a floating bottom-right bubble on the Dashboard page only, same visual pattern as the board assistant's, but a separate Vue component instance.
- Board references in replies use the text convention `[[Board Name]]`, resolved to links client-side — not a Gemini function call.
- Reuses `csrfFetch.ts` unchanged. Reuses the `aiEnabled` gating pattern (`filled(config('services.gemini.key'))`) and the `throttle:20,1` rate limit on the send route, exactly as the board assistant already does.
- The `DashboardStatsService` extraction must not change `DashboardController`'s existing behavior — `tests/Feature/DashboardTest.php`'s existing suite must keep passing unmodified.
- Every PHP change must pass `vendor/bin/pint --dirty --format agent` before being considered done. Every backend behavior change needs a Pest test. Only ever run Pint with `--dirty` — a full unscoped `vendor/bin/pint --format agent` run in this codebase has previously reformatted unrelated pre-existing files outside the current task's scope.

---

### Task 1: Extract `DashboardStatsService`

**Files:**
- Create: `app/Services/DashboardStatsService.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardStatsServiceTest.php`

**Interfaces:**
- Consumes: nothing new — operates on an already-queried `Illuminate\Support\Collection` of `App\Models\Card` models, each eager-loaded with `boardList.board`, `checklists.items.members`, and `members` (the exact eager-load set `DashboardController::buildReportData()` already uses).
- Produces: `DashboardStatsService::build(Collection $cards): array{stats: array<string, mixed>, tasksByBoard: Collection, workload: Collection}`. The `stats` array has exactly these keys: `total`, `completed`, `overdue`, `dueSoon`, `checklistProgress`, `checklistItemsOverdue`, `checklistItemsDueSoon`. `tasksByBoard` is a `Collection` of `['name' => string, 'count' => int]` (top 8, sorted descending). `workload` is a `Collection` of `['user' => User, 'count' => int]` (top 8, sorted descending, merging card members and checklist-item members). Task 4 calls this directly with its own `$cards` collection.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DashboardStatsServiceTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardStatsService;

test('build computes total, completed, overdue, and due-soon counts', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    Card::factory()->for($list)->create(['name' => 'No due date']);
    Card::factory()->for($list)->overdue()->create(['name' => 'Overdue']);
    Card::factory()->for($list)->create(['name' => 'Due soon', 'due_date' => now()->addDays(2)->toDateString()]);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['stats']['total'])->toBe(3);
    expect($result['stats']['overdue'])->toBe(1);
    expect($result['stats']['dueSoon'])->toBe(1);
});

test('build computes checklist progress and checklist due-date stats', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $checklist->items()->create(['name' => 'Done', 'is_checked' => true, 'position' => 0]);
    $checklist->items()->create([
        'name' => 'Overdue item',
        'is_checked' => false,
        'position' => 1,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['stats']['checklistProgress'])->toBe(50);
    expect($result['stats']['checklistItemsOverdue'])->toBe(1);
});

test('build groups tasks by board and merges card and checklist-item workload', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($user->id);
    $checklist = Checklist::factory()->for($card)->create();
    $item = $checklist->items()->create(['name' => 'Step', 'is_checked' => false, 'position' => 0]);
    $item->members()->attach($user->id);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['tasksByBoard']->first())->toBe(['name' => 'Engineering', 'count' => 1]);
    expect($result['workload']->first()['count'])->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardStatsServiceTest`
Expected: FAIL — `App\Services\DashboardStatsService` not found.

- [ ] **Step 3: Write `DashboardStatsService`**

Create `app/Services/DashboardStatsService.php` with this exact body, lifted verbatim from `DashboardController::buildReportData()`'s existing stats/tasksByBoard/workload computation (lines computing `$today` through `$workload` in the current file) — only the input changes from an inline `$cards` variable to a method parameter:

```php
<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    /**
     * @param  Collection<int, Card>  $cards  Each must be eager-loaded with 'boardList.board', 'checklists.items.members', and 'members'.
     * @return array{stats: array<string, mixed>, tasksByBoard: Collection, workload: Collection}
     */
    public function build(Collection $cards): array
    {
        $today = now()->toDateString();
        $weekAhead = now()->addDays(7)->toDateString();

        $allChecklistItems = $cards->flatMap(fn (Card $card) => $card->checklists)->flatMap(fn ($checklist) => $checklist->items);

        $isCompleted = function (Card $card): bool {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);

            return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);
        };

        $stats = [
            'total' => $cards->count(),
            'completed' => $cards->filter($isCompleted)->count(),
            'overdue' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date < $today && ! $isCompleted($card))->count(),
            'dueSoon' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date >= $today && $card->due_date <= $weekAhead)->count(),
            'checklistProgress' => $allChecklistItems->isEmpty()
                ? null
                : (int) round($allChecklistItems->filter(fn ($item) => $item->is_checked)->count() / $allChecklistItems->count() * 100),
            'checklistItemsOverdue' => $allChecklistItems->filter(fn ($item) => $item->due_date && $item->due_date < $today && ! $item->is_checked)->count(),
            'checklistItemsDueSoon' => $allChecklistItems->filter(fn ($item) => $item->due_date && ! $item->is_checked && $item->due_date >= $today && $item->due_date <= $weekAhead)->count(),
        ];

        $tasksByBoard = $cards
            ->groupBy(fn (Card $card) => $card->boardList->board->name)
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(8)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();

        $workload = $cards
            ->flatMap(fn (Card $card) => $card->members)
            ->merge($allChecklistItems->flatMap(fn ($item) => $item->members))
            ->groupBy('id')
            ->map(fn ($group) => ['user' => $group->first(), 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        return [
            'stats' => $stats,
            'tasksByBoard' => $tasksByBoard,
            'workload' => $workload,
        ];
    }
}
```

- [ ] **Step 4: Update `DashboardController::buildReportData()` to use the service**

In `app/Http/Controllers/DashboardController.php`, add `use App\Services\DashboardStatsService;` to the imports. Replace this block (the `$today` line through the `$workload` assignment — everything from `$today = now()->toDateString();` down to the closing `->values();` of `$workload`, i.e. the code your `DashboardStatsService::build()` now contains) with:

```php
        $statsData = app(DashboardStatsService::class)->build($cards);
        $stats = $statsData['stats'];
        $tasksByBoard = $statsData['tasksByBoard'];
        $workload = $statsData['workload'];
```

Leave everything else in `buildReportData()` unchanged — `$cards` itself is still queried exactly as before (it's still needed afterward for `$tasksByList`), and `$tasksByList`, `$recentActivity`, `$boards` stay exactly as they are now.

- [ ] **Step 5: Run tests to verify everything still passes**

Run: `php artisan test --compact --filter=DashboardStatsServiceTest`
Expected: PASS (3 tests).

Run: `php artisan test --compact --filter=DashboardTest`
Expected: PASS, same test count as before this change (this is the regression check for the refactor — the Dashboard page's behavior must be byte-for-byte unchanged).

- [ ] **Step 6: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/DashboardStatsService.php app/Http/Controllers/DashboardController.php tests/Feature/DashboardStatsServiceTest.php
git commit -m "refactor: extract DashboardStatsService from DashboardController"
```

---

### Task 2: `GeminiClient::converse()`

**Files:**
- Modify: `app/Services/GeminiClient.php`
- Modify: `tests/Unit/GeminiClientTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `GeminiClient::converse(string $systemInstruction, array $messages): string` where `$messages` is `array<int, array{role: string, content: string}>` (oldest first, last entry is the new user turn) — same message-history shape `reply()` already takes. Throws `App\Exceptions\GeminiApiException` on HTTP failure, connection failure, unexpected response shape, or empty text — same failure contract as `reply()`. Task 4 calls this directly.

This task also extracts a private `sendRequest()` helper inside `GeminiClient` so `reply()` and `converse()` share the same HTTP-call-plus-error-handling code instead of duplicating it. This changes `reply()`'s internals but must not change its public behavior — the existing `reply()` tests in `tests/Unit/GeminiClientTest.php` are your regression check.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/GeminiClientTest.php` (it already has `use App\Exceptions\GeminiApiException;`, `use App\Services\GeminiClient;`, and `use Illuminate\Support\Facades\Http;` at the top — no new imports needed):

```php
test('converse returns concatenated text from the response', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => 'Your overdue count is 2.']]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-3.6-flash');
    $result = $client->converse('You are a dashboard assistant.', [['role' => 'user', 'content' => 'how many overdue tasks?']]);

    expect($result)->toBe('Your overdue count is 2.');
});

test('converse throws GeminiApiException when the HTTP request fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-3.6-flash');

    expect(fn () => $client->converse('You are a dashboard assistant.', [['role' => 'user', 'content' => 'hi']]))
        ->toThrow(GeminiApiException::class);
});

test('converse throws GeminiApiException on connection failure', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'));

    $client = new GeminiClient('fake-key', 'gemini-3.6-flash');

    expect(fn () => $client->converse('You are a dashboard assistant.', [['role' => 'user', 'content' => 'hi']]))
        ->toThrow(GeminiApiException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=GeminiClientTest`
Expected: the 3 new tests FAIL — `converse()` method doesn't exist. The pre-existing `reply()` tests should still PASS (unchanged so far).

- [ ] **Step 3: Extract `sendRequest()` and add `converse()`**

In `app/Services/GeminiClient.php`, replace the entire `reply()` method body with a version that delegates the HTTP call to a new private `sendRequest()` helper, and add the new `converse()` method plus `sendRequest()` itself. The rest of the file (`systemInstruction()`, `toContents()`, `toolDeclarations()`, `toolActionFromFunctionCall()`, `isArrayOfStringables()`) stays exactly as it is — do not touch those methods.

Replace:

```php
    public function reply(string $boardName, array $lists, array $messages): array
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(20)
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent",
                    [
                        'system_instruction' => [
                            'parts' => [['text' => $this->systemInstruction($boardName, $lists)]],
                        ],
                        'contents' => $this->toContents($messages),
                        'tools' => [['function_declarations' => $this->toolDeclarations()]],
                    ]
                );
        } catch (ConnectionException $exception) {
            throw new GeminiApiException('Gemini API request failed.', previous: $exception);
        }

        if ($response->failed()) {
            throw new GeminiApiException("Gemini API request failed with status {$response->status()}.");
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts)) {
            throw new GeminiApiException('Gemini API returned an unexpected response shape.');
        }

        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                return $this->toolActionFromFunctionCall($part['functionCall']);
            }
        }

        $text = collect($parts)->pluck('text')->filter()->implode("\n");

        if ($text === '') {
            throw new GeminiApiException('Gemini API returned an empty response.');
        }

        return ['content' => $text, 'tool_action' => null];
    }
```

With:

```php
    public function reply(string $boardName, array $lists, array $messages): array
    {
        $data = $this->sendRequest([
            'system_instruction' => [
                'parts' => [['text' => $this->systemInstruction($boardName, $lists)]],
            ],
            'contents' => $this->toContents($messages),
            'tools' => [['function_declarations' => $this->toolDeclarations()]],
        ]);

        $parts = data_get($data, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            throw new GeminiApiException('Gemini API returned an unexpected response shape.');
        }

        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                return $this->toolActionFromFunctionCall($part['functionCall']);
            }
        }

        $text = collect($parts)->pluck('text')->filter()->implode("\n");

        if ($text === '') {
            throw new GeminiApiException('Gemini API returned an empty response.');
        }

        return ['content' => $text, 'tool_action' => null];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  Oldest first; the last entry is the new user message.
     */
    public function converse(string $systemInstruction, array $messages): string
    {
        $data = $this->sendRequest([
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => $this->toContents($messages),
        ]);

        $parts = data_get($data, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            throw new GeminiApiException('Gemini API returned an unexpected response shape.');
        }

        $text = collect($parts)->pluck('text')->filter()->implode("\n");

        if ($text === '') {
            throw new GeminiApiException('Gemini API returned an empty response.');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sendRequest(array $body): array
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(20)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent", $body);
        } catch (ConnectionException $exception) {
            throw new GeminiApiException('Gemini API request failed.', previous: $exception);
        }

        if ($response->failed()) {
            throw new GeminiApiException("Gemini API request failed with status {$response->status()}.");
        }

        return $response->json();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=GeminiClientTest`
Expected: PASS, all tests — the pre-existing `reply()`-related tests (text reply, `create_lists` tool action, `create_cards` tool action, HTTP failure, connection failure, malformed args) plus the 3 new `converse()` tests.

- [ ] **Step 5: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/GeminiClient.php tests/Unit/GeminiClientTest.php
git commit -m "feat: add GeminiClient::converse() for plain-text replies"
```

---

### Task 3: `dashboard_conversations` / `dashboard_messages` schema and models

**Files:**
- Create: `database/migrations/2026_08_28_030000_create_dashboard_conversations_table.php`
- Create: `database/migrations/2026_08_28_030001_create_dashboard_messages_table.php`
- Create: `app/Models/DashboardConversation.php`
- Create: `app/Models/DashboardMessage.php`
- Test: `tests/Feature/DashboardConversationTest.php`

**Interfaces:**
- Produces: `DashboardConversation` (fillable `user_id`; relations `user(): BelongsTo`, `messages(): HasMany` ordered by `id` ascending) and `DashboardMessage` (fillable `dashboard_conversation_id`, `role`, `content`; relation `conversation(): BelongsTo`). Task 4 uses `DashboardConversation::firstOrCreate(['user_id' => ...])` and `$conversation->messages()->create([...])` directly.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DashboardConversationTest.php`:

```php
<?php

use App\Models\DashboardConversation;
use App\Models\User;

test('a dashboard conversation belongs to a user and lists its messages oldest first', function () {
    $user = User::factory()->create();

    $conversation = DashboardConversation::create(['user_id' => $user->id]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'first']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'second']);

    expect($conversation->user->id)->toBe($user->id);
    expect($conversation->fresh()->messages->pluck('content')->all())->toBe(['first', 'second']);
});

test('a dashboard conversation is unique per user', function () {
    $user = User::factory()->create();
    DashboardConversation::create(['user_id' => $user->id]);

    expect(fn () => DashboardConversation::create(['user_id' => $user->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardConversationTest`
Expected: FAIL — `DashboardConversation` class not found.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_28_030000_create_dashboard_conversations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_conversations');
    }
};
```

`database/migrations/2026_08_28_030001_create_dashboard_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_messages');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/DashboardConversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardConversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DashboardMessage::class)->orderBy('id');
    }
}
```

`app/Models/DashboardMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'dashboard_conversation_id',
        'role',
        'content',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DashboardConversation::class, 'dashboard_conversation_id');
    }
}
```

- [ ] **Step 5: Run the migration and re-run the test**

Run: `php artisan migrate --no-interaction`
Run: `php artisan test --compact --filter=DashboardConversationTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add database/migrations/2026_08_28_030000_create_dashboard_conversations_table.php database/migrations/2026_08_28_030001_create_dashboard_messages_table.php app/Models/DashboardConversation.php app/Models/DashboardMessage.php tests/Feature/DashboardConversationTest.php
git commit -m "feat: add dashboard_conversations/dashboard_messages schema and models"
```

---

### Task 4: `DashboardChatController`

**Files:**
- Create: `app/Http/Requests/DashboardChat/StoreDashboardMessageRequest.php`
- Create: `app/Http/Controllers/DashboardChatController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardChatTest.php`

**Interfaces:**
- Consumes: `DashboardConversation`/`DashboardMessage` (Task 3); `DashboardStatsService::build()` (Task 1); `GeminiClient::converse()` (Task 2); `User::boardMemberships(): BelongsToMany` (existing, `app/Models/User.php`).
- Produces: routes `dashboard-chat.show` (`GET dashboard/ai/conversation`) and `dashboard-chat.messages.store` (`POST dashboard/ai/messages`, body `{content: string}`, throttled `20,1`). `show` returns JSON `{messages: DashboardMessage[]}`. `sendMessage` returns JSON `{message: DashboardMessage, reply: DashboardMessage}` on success or `{message: DashboardMessage, error: string}` (HTTP 502) on a Gemini failure. Task 6 consumes both.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DashboardChatTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\DashboardConversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function fakeDashboardGeminiTextReply(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => $text]]]],
            ],
        ], 200),
    ]);
}

test('a user can open their dashboard conversation, created lazily', function () {
    $user = User::factory()->create();

    expect(DashboardConversation::count())->toBe(0);

    $response = $this->actingAs($user)->getJson('/dashboard/ai/conversation');

    $response->assertOk()->assertJson(['messages' => []]);
    expect(DashboardConversation::where('user_id', $user->id)->exists())->toBeTrue();
});

test('two users get separate dashboard conversations', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)->getJson('/dashboard/ai/conversation')->assertOk();
    $this->actingAs($userB)->getJson('/dashboard/ai/conversation')->assertOk();

    expect(DashboardConversation::count())->toBe(2);
});

test('a guest cannot open or send messages to the dashboard conversation', function () {
    $this->getJson('/dashboard/ai/conversation')->assertUnauthorized();
    $this->postJson('/dashboard/ai/messages', ['content' => 'hi'])->assertUnauthorized();
});

test('sending a message stores it and stores a plain-text assistant reply', function () {
    fakeDashboardGeminiTextReply('You have 2 overdue tasks.');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'how many overdue tasks?']);

    $response->assertOk();
    $response->assertJsonPath('reply.content', 'You have 2 overdue tasks.');

    $conversation = DashboardConversation::where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages->first()->role)->toBe('user');
    expect($conversation->messages->last()->role)->toBe('assistant');
});

test('a failed gemini call keeps the user message but does not create a bogus assistant reply', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'how am I doing?']);

    $response->assertStatus(502);
    $response->assertJsonPath('error', "AI couldn't respond, try again.");

    $conversation = DashboardConversation::where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->role)->toBe('user');
});

test('the ai context reflects the users full board set, not any dashboard filter', function () {
    $capturedBody = null;
    Http::fake(function ($request) use (&$capturedBody) {
        $capturedBody = $request->data();

        return Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'ok']]]]],
        ], 200);
    });

    $user = User::factory()->create();
    $workspaceA = Workspace::factory()->for($user, 'owner')->create();
    $boardA = Board::factory()->for($workspaceA)->for($user)->create(['name' => 'Board A']);
    $listA = BoardList::factory()->for($boardA)->create();
    Card::factory()->for($listA)->create();

    $workspaceB = Workspace::factory()->for($user, 'owner')->create();
    $boardB = Board::factory()->for($workspaceB)->for($user)->create(['name' => 'Board B']);
    $listB = BoardList::factory()->for($boardB)->create();
    Card::factory()->for($listB)->create();

    $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'summarize my boards']);

    $systemText = $capturedBody['system_instruction']['parts'][0]['text'];
    expect($systemText)->toContain('Board A');
    expect($systemText)->toContain('Board B');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardChatTest`
Expected: FAIL — route `dashboard-chat.show` / controller not found.

- [ ] **Step 3: Write `StoreDashboardMessageRequest`**

```php
<?php

namespace App\Http\Requests\DashboardChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 4: Write `DashboardChatController`**

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Http\Requests\DashboardChat\StoreDashboardMessageRequest;
use App\Models\Card;
use App\Models\DashboardConversation;
use App\Models\DashboardMessage;
use App\Services\DashboardStatsService;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $conversation = DashboardConversation::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'messages' => $conversation->messages,
        ]);
    }

    public function sendMessage(StoreDashboardMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = DashboardConversation::firstOrCreate(['user_id' => $user->id]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
        ]);

        $boardIds = $user->boardMemberships()->pluck('boards.id');

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['boardList.board', 'checklists.items.members', 'members'])
            ->get();

        $statsData = app(DashboardStatsService::class)->build($cards);
        $boards = $user->boardMemberships()->get(['boards.id', 'boards.name']);

        $systemInstruction = $this->buildSystemInstruction($statsData['stats'], $statsData['tasksByBoard'], $statsData['workload'], $boards);

        $history = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn (DashboardMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        try {
            $content = app(GeminiClient::class)->converse($systemInstruction, $history);
        } catch (GeminiApiException $exception) {
            report($exception);

            return response()->json([
                'message' => $userMessage,
                'error' => "AI couldn't respond, try again.",
            ], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
        ]);

        return response()->json([
            'message' => $userMessage,
            'reply' => $assistantMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function buildSystemInstruction(array $stats, Collection $tasksByBoard, Collection $workload, Collection $boards): string
    {
        $statsSummary = "Total tasks: {$stats['total']}, Completed: {$stats['completed']}, "
            ."Overdue: {$stats['overdue']}, Due within 7 days: {$stats['dueSoon']}, "
            .'Checklist progress: '.($stats['checklistProgress'] !== null ? "{$stats['checklistProgress']}%" : 'n/a').', '
            ."Checklist items overdue: {$stats['checklistItemsOverdue']}, "
            ."Checklist items due soon: {$stats['checklistItemsDueSoon']}";

        $boardSummary = $tasksByBoard->isEmpty()
            ? '(no active tasks)'
            : $tasksByBoard->map(fn (array $board) => "- {$board['name']}: {$board['count']} task(s)")->implode("\n");

        $workloadSummary = $workload->isEmpty()
            ? '(no assigned members)'
            : $workload->map(fn (array $entry) => "- {$entry['user']->name}: {$entry['count']} task(s)")->implode("\n");

        $boardNames = $boards->pluck('name')->implode(', ');
        $boardNames = $boardNames === '' ? '(no boards)' : $boardNames;

        return <<<TEXT
            You are a helpful assistant embedded in the Dashboard of a Trello-style project management app called Trellow.
            Answer questions about the user's overall task progress using only the data below. Do not make up numbers not given here.

            Overall stats: {$statsSummary}

            Tasks by board:
            {$boardSummary}

            Workload by member:
            {$workloadSummary}

            When you refer to a specific board by name, wrap it in double square brackets exactly like [[Board Name]], using
            only these exact board names: {$boardNames}. Do not invent board names. You cannot create, edit, or delete
            anything on the board — you can only answer questions and point the user at a board. Keep replies short and practical.
            TEXT;
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the import alongside the other controller imports:

```php
use App\Http\Controllers\DashboardChatController;
```

And add these two routes directly after the existing `dashboard.report` route:

```php
Route::get('dashboard/ai/conversation', [DashboardChatController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard-chat.show');

Route::post('dashboard/ai/messages', [DashboardChatController::class, 'sendMessage'])
    ->middleware(['auth', 'verified', 'throttle:20,1'])
    ->name('dashboard-chat.messages.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardChatTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Requests/DashboardChat/StoreDashboardMessageRequest.php app/Http/Controllers/DashboardChatController.php routes/web.php tests/Feature/DashboardChatTest.php
git commit -m "feat: add dashboard chat show/sendMessage endpoints"
```

---

### Task 5: `aiEnabled` dashboard prop

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Produces: the `Dashboard` Inertia page gains a boolean prop `aiEnabled`, `true` iff `config('services.gemini.key')` is non-empty. Task 6's `Dashboard.vue` consumes this prop directly.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/DashboardTest.php` (imports `User`, `Board`, `Workspace` already present in this file per the existing board-assistant `aiEnabled` test pattern used on `BoardTest.php`):

```php
test('aiEnabled is true only when a gemini api key is configured', function () {
    $user = User::factory()->create();

    config(['services.gemini.key' => null]);
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('aiEnabled', false));

    config(['services.gemini.key' => 'fake-key']);
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('aiEnabled', true));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: the new test FAILs — `aiEnabled` prop missing from the Inertia response. All other `DashboardTest` tests still pass.

- [ ] **Step 3: Add the prop**

In `app/Http/Controllers/DashboardController.php`, in `index()`, add a line inside the `Inertia::render('Dashboard', [...])` array, after the existing `'filters' => [...],` entry:

```php
            'aiEnabled' => filled(config('services.gemini.key')),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardTest`
Expected: PASS, all tests.

- [ ] **Step 5: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "feat: expose aiEnabled flag on the dashboard page"
```

---

### Task 6: `DashboardChatWidget.vue` and mounting it on the Dashboard page

**Files:**
- Create: `resources/js/components/DashboardChatWidget.vue`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/pages/Dashboard.vue`

**Interfaces:**
- Consumes: routes `dashboard-chat.show`, `dashboard-chat.messages.store` (Task 4); `aiEnabled` prop (Task 5); `csrfFetch` from `@/lib/csrfFetch` (existing, unchanged); the `boards: { id: number; name: string; workspace_id: number }[]` prop `Dashboard.vue` already receives (existing, unchanged — this is the user's full/unfiltered board list, exactly what's needed for `[[Board Name]]` resolution, so no new backend data is needed for this).
- Produces: `<DashboardChatWidget :ai-enabled="boolean" :boards="{ id: number; name: string }[]" />`, a self-contained floating widget with no emitted events and no parent-managed state.

This task is frontend-only. There is no backend behavior to test with Pest; verify with `npx eslint --fix` and `npm run build` (no browser-automation tool is available in this environment, so this cannot be visually confirmed — say so when reporting the task done).

- [ ] **Step 1: Add the `DashboardMessage` type**

In `resources/js/types/index.ts`, add near the existing `AiMessage`/`AiToolAction` interfaces:

```typescript
export interface DashboardMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    updated_at: string;
}
```

- [ ] **Step 2: Write `DashboardChatWidget.vue`**

Create `resources/js/components/DashboardChatWidget.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { DashboardMessage } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Send, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    aiEnabled: boolean;
    boards: { id: number; name: string }[];
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<DashboardMessage[]>([]);
const scrollRef = ref<HTMLElement | null>(null);

async function scrollToBottom() {
    await nextTick();
    scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight });
}

async function toggleOpen() {
    open.value = !open.value;

    if (open.value && props.aiEnabled && !loaded.value) {
        await loadConversation();
    }
}

async function loadConversation() {
    loading.value = true;

    try {
        const response = await csrfFetch(route('dashboard-chat.show'));

        if (!response.ok) {
            error.value = 'Could not load the conversation.';
            return;
        }

        const data = await response.json();
        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
    } finally {
        loading.value = false;
    }
}

async function send() {
    const content = draft.value.trim();

    if (!content || loading.value) {
        return;
    }

    draft.value = '';
    error.value = null;
    loading.value = true;

    try {
        const response = await csrfFetch(route('dashboard-chat.messages.store'), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        if (!response.ok && !data.error) {
            error.value = typeof data.message === 'string' ? data.message : 'Could not send that message.';
            draft.value = content;
            return;
        }

        messages.value.push(data.message);

        if (data.reply) {
            messages.value.push(data.reply);
        } else if (data.error) {
            error.value = data.error;
        }

        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
        draft.value = content;
    } finally {
        loading.value = false;
    }
}

interface RenderedSegment {
    text: string;
    boardId: number | null;
}

function renderSegments(content: string): RenderedSegment[] {
    const segments: RenderedSegment[] = [];
    const regex = /\[\[([^\]]+)\]\]/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(content)) !== null) {
        if (match.index > lastIndex) {
            segments.push({ text: content.slice(lastIndex, match.index), boardId: null });
        }

        const board = props.boards.find((b) => b.name === match![1]);
        segments.push({ text: board ? board.name : match[1], boardId: board ? board.id : null });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < content.length) {
        segments.push({ text: content.slice(lastIndex), boardId: null });
    }

    return segments;
}
</script>

<template>
    <div class="fixed bottom-4 right-4 z-40">
        <button
            type="button"
            class="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:opacity-90"
            aria-label="Dashboard assistant"
            @click="toggleOpen"
        >
            <X v-if="open" class="size-5" />
            <Bot v-else class="size-5" />
        </button>

        <div
            v-if="open"
            class="absolute bottom-16 right-0 flex h-[28rem] w-80 flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl"
        >
            <div class="border-b border-border px-4 py-3">
                <p class="text-sm font-semibold">Dashboard assistant</p>
                <p class="text-xs text-muted-foreground">Ask about your progress and stats.</p>
            </div>

            <div v-if="!aiEnabled" class="flex flex-1 items-center justify-center p-4 text-center text-sm text-muted-foreground">
                AI chat isn't configured yet.
            </div>

            <template v-else>
                <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                            :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                        >
                            <template v-for="(segment, index) in renderSegments(message.content)" :key="index">
                                <Link v-if="segment.boardId" :href="route('boards.show', segment.boardId)" class="font-medium underline">
                                    {{ segment.text }}
                                </Link>
                                <span v-else>{{ segment.text }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

                <form class="flex items-center gap-2 border-t border-border p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Ask about your progress..."
                        maxlength="2000"
                        class="flex-1 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        :disabled="loading"
                    />
                    <Button type="submit" size="icon" :disabled="loading || !draft.trim()">
                        <LoaderCircle v-if="loading" class="size-4 animate-spin" />
                        <Send v-else class="size-4" />
                    </Button>
                </form>
            </template>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Mount the widget on the Dashboard page**

In `resources/js/pages/Dashboard.vue`, add the import alongside the other component imports:

```typescript
import DashboardChatWidget from '@/components/DashboardChatWidget.vue';
```

Add `aiEnabled: boolean;` to the `defineProps<{...}>()` block (it currently ends with `filters: { workspace_id: number | null; board_id: number | null };`):

```typescript
const props = defineProps<{
    stats: DashboardStats;
    tasksByBoard: BoardTaskCount[];
    tasksByList: BoardTaskCount[] | null;
    workload: MemberWorkload[];
    recentActivity: CardActivity[];
    hasBoards: boolean;
    workspaces: { id: number; name: string }[];
    boards: { id: number; name: string; workspace_id: number }[];
    filters: { workspace_id: number | null; board_id: number | null };
    aiEnabled: boolean;
}>();
```

At the end of the template, add the widget directly before the closing `</AppLayout>` tag:

```html
        <DashboardChatWidget :ai-enabled="aiEnabled" :boards="boards" />
```

- [ ] **Step 4: Lint and build**

Run: `npx eslint resources/js/components/DashboardChatWidget.vue resources/js/pages/Dashboard.vue resources/js/types/index.ts --fix`
Expected: no errors.

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/DashboardChatWidget.vue resources/js/types/index.ts resources/js/pages/Dashboard.vue
git commit -m "feat: add floating AI dashboard assistant widget"
```

---

### Task 7: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test --compact`
Expected: all tests pass, including the new `DashboardStatsServiceTest`, the extended `GeminiClientTest`, `DashboardConversationTest`, `DashboardChatTest` files, and the appended `DashboardTest` case.

- [ ] **Step 2: Confirm Pint is clean, scoped to this plan's files**

Run: `vendor/bin/pint --test app/Services/DashboardStatsService.php app/Services/GeminiClient.php app/Models/DashboardConversation.php app/Models/DashboardMessage.php app/Http/Requests/DashboardChat/StoreDashboardMessageRequest.php app/Http/Controllers/DashboardChatController.php app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/DashboardStatsServiceTest.php tests/Unit/GeminiClientTest.php tests/Feature/DashboardConversationTest.php tests/Feature/DashboardChatTest.php tests/Feature/DashboardTest.php`
Expected: `{"tool":"pint","result":"passed"}`.

Do NOT run an unscoped `vendor/bin/pint --format agent` — that has previously reformatted unrelated pre-existing files elsewhere in this codebase outside the current task's scope.

- [ ] **Step 3: Confirm the frontend still builds**

Run: `npm run build`
Expected: build succeeds.
