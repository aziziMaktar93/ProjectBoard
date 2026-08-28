# AI Board Assistant (Chatbox) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a board-scoped AI chat widget (Google Gemini, free tier) that helps a user brainstorm board structure and can propose lists/cards the user applies with one click.

**Architecture:** A thin `GeminiClient` service wraps the Gemini REST API (text + function-calling). `AiChatController` exposes three JSON endpoints (show/sendMessage/applyAction) backed by two new tables (`ai_conversations`, `ai_messages`, one conversation per board+user). The AI never writes to the board directly — `sendMessage` only stores a proposed `tool_action`; `applyAction` is a separate, editor-only endpoint that executes it using the same create-list/create-card logic as the existing manual endpoints. The frontend is a floating chat bubble (`AiChatWidget.vue`) mounted only on `boards/Show.vue`, using plain `fetch` (not Inertia visits) since these are JSON calls that shouldn't swap the page.

**Tech Stack:** Laravel 12 (PHP 8.2), Pest, Vue 3 + TypeScript, Inertia v2, Google Gemini REST API via Laravel's `Http` facade.

## Global Constraints

- Provider is Google Gemini only (free tier, no billing). Default model `gemini-2.0-flash`, overridable via `GEMINI_MODEL` env var. No other provider or user-facing switcher.
- Exactly two tools in v1: `create_lists` and `create_cards`. No `create_checklist` tool, no other AI actions.
- Any board member (viewer or editor) can chat — gated by `BoardPolicy::view`. Only editors can apply a proposed action — gated by `BoardPolicy::update`. A non-member of the board gets 403 on every AI endpoint.
- One ongoing conversation per (board, user) pair, persisted in the database. No multi-thread UI, no renaming/deleting a conversation.
- The chat widget is a floating bottom-right bubble mounted only in `boards/Show.vue`. It is not part of the board page's initial Inertia payload — the conversation is fetched lazily via a separate JSON request the first time the widget is opened.
- If `GEMINI_API_KEY` is not configured, the board page passes `aiEnabled: false` and the widget shows a "not configured" state instead of a composer. No request is ever sent to Gemini in that case.
- No streaming — each turn is a single request/response.
- No new npm dependencies. CSRF for the widget's `fetch` calls is handled by manually reading the `XSRF-TOKEN` cookie into an `X-XSRF-TOKEN` header (Laravel's default cookie-based CSRF check), not by adding axios.
- Every PHP change must pass `vendor/bin/pint --dirty --format agent` before being considered done. Every backend behavior change needs a Pest test.

---

### Task 1: `ai_conversations` / `ai_messages` schema and models

**Files:**
- Create: `database/migrations/2026_08_28_020000_create_ai_conversations_table.php`
- Create: `database/migrations/2026_08_28_020001_create_ai_messages_table.php`
- Create: `app/Models/AiConversation.php`
- Create: `app/Models/AiMessage.php`
- Test: `tests/Feature/AiConversationTest.php`

**Interfaces:**
- Produces: `AiConversation` (fillable `board_id`, `user_id`; relations `board(): BelongsTo`, `user(): BelongsTo`, `messages(): HasMany` ordered by `id` ascending) and `AiMessage` (fillable `ai_conversation_id`, `role`, `content`, `tool_action`, `applied_at`; casts `tool_action` → `array`, `applied_at` → `datetime`; relation `conversation(): BelongsTo`). Later tasks create/query these directly (`AiConversation::firstOrCreate([...])`, `$conversation->messages()->create([...])`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AiConversationTest.php`:

```php
<?php

use App\Models\AiConversation;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('an ai conversation belongs to a board and a user, and lists its messages oldest first', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'first']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'second']);

    expect($conversation->board->id)->toBe($board->id);
    expect($conversation->user->id)->toBe($user->id);
    expect($conversation->fresh()->messages->pluck('content')->all())->toBe(['first', 'second']);
});

test('an ai message casts tool_action to an array and applied_at to a datetime', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);

    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will create these lists: Research',
        'tool_action' => ['type' => 'create_lists', 'names' => ['Research']],
        'applied_at' => now(),
    ]);

    expect($message->fresh()->tool_action)->toBe(['type' => 'create_lists', 'names' => ['Research']]);
    expect($message->fresh()->applied_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AiConversationTest`
Expected: FAIL — `AiConversation` class not found (migrations/models don't exist yet).

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_28_020000_create_ai_conversations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
```

`database/migrations/2026_08_28_020001_create_ai_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('tool_action')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/AiConversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_id',
        'user_id',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }
}
```

`app/Models/AiMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'tool_action',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_action' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
```

- [ ] **Step 5: Run the migration and re-run the test**

Run: `php artisan migrate --no-interaction`
Run: `php artisan test --compact --filter=AiConversationTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add database/migrations/2026_08_28_020000_create_ai_conversations_table.php database/migrations/2026_08_28_020001_create_ai_messages_table.php app/Models/AiConversation.php app/Models/AiMessage.php tests/Feature/AiConversationTest.php
git commit -m "feat: add ai_conversations/ai_messages schema and models"
```

---

### Task 2: `GeminiClient` service

**Files:**
- Create: `app/Exceptions/GeminiApiException.php`
- Create: `app/Services/GeminiClient.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/GeminiClientTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `GeminiClient::reply(string $boardName, array $lists, array $messages): array{content: string, tool_action: array<string, mixed>|null}` where `$lists` is `array<int, array{name: string, card_count: int}>` and `$messages` is `array<int, array{role: string, content: string}>` (oldest first, last entry is the new user turn). Throws `App\Exceptions\GeminiApiException` on any HTTP failure or unexpected response shape. Resolvable via `app(GeminiClient::class)` (bound as a singleton reading `config('services.gemini.*')`). Later tasks call `app(GeminiClient::class)->reply(...)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/GeminiClientTest.php`:

```php
<?php

use App\Exceptions\GeminiApiException;
use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;

test('reply returns plain text when Gemini responds with text only', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => 'Here are some ideas.']]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'help me plan']]);

    expect($result)->toBe(['content' => 'Here are some ideas.', 'tool_action' => null]);
});

test('reply returns a create_lists tool action when Gemini calls that function', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => ['name' => 'create_lists', 'args' => ['names' => ['Research', 'Design']]]],
                ]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'suggest lists']]);

    expect($result['tool_action'])->toBe(['type' => 'create_lists', 'names' => ['Research', 'Design']]);
    expect($result['content'])->toContain('Research');
});

test('reply returns a create_cards tool action when Gemini calls that function', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => [
                        'name' => 'create_cards',
                        'args' => ['list_name' => 'Research', 'card_names' => ['Competitor audit']],
                    ]],
                ]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'add a card']]);

    expect($result['tool_action'])->toBe([
        'type' => 'create_cards',
        'list_name' => 'Research',
        'card_names' => ['Competitor audit'],
    ]);
});

test('reply throws GeminiApiException when the HTTP request fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');

    expect(fn () => $client->reply('My Board', [], [['role' => 'user', 'content' => 'hi']]))
        ->toThrow(GeminiApiException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GeminiClientTest`
Expected: FAIL — `App\Services\GeminiClient` not found.

- [ ] **Step 3: Write `GeminiApiException`**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class GeminiApiException extends RuntimeException
{
}
```

- [ ] **Step 4: Write `GeminiClient`**

```php
<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * @param  array<int, array{name: string, card_count: int}>  $lists
     * @param  array<int, array{role: string, content: string}>  $messages  Oldest first; the last entry is the new user message.
     * @return array{content: string, tool_action: array<string, mixed>|null}
     */
    public function reply(string $boardName, array $lists, array $messages): array
    {
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemInstruction($boardName, $lists)]],
                ],
                'contents' => $this->toContents($messages),
                'tools' => [['function_declarations' => $this->toolDeclarations()]],
            ]
        );

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

    /**
     * @param  array<int, array{name: string, card_count: int}>  $lists
     */
    private function systemInstruction(string $boardName, array $lists): string
    {
        $listSummary = collect($lists)
            ->map(fn (array $list) => "- {$list['name']} ({$list['card_count']} card(s))")
            ->implode("\n");

        $listSummary = $listSummary === '' ? '(no lists yet)' : $listSummary;

        return <<<TEXT
            You are a project management assistant embedded in a Trello-style board called "{$boardName}".
            Current lists on this board:
            {$listSummary}

            Help the user brainstorm and plan the board's structure. When they ask you to add lists or cards,
            call the appropriate tool instead of just describing what to do. Keep replies short and practical.
            TEXT;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function toContents(array $messages): array
    {
        return array_map(
            fn (array $message) => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            $messages
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDeclarations(): array
    {
        return [
            [
                'name' => 'create_lists',
                'description' => 'Create one or more new lists (columns) on the board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of the lists to create, in order.',
                        ],
                    ],
                    'required' => ['names'],
                ],
            ],
            [
                'name' => 'create_cards',
                'description' => 'Create one or more cards under a named list on the board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'The name of the list to add cards to.',
                        ],
                        'card_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of the cards to create, in order.',
                        ],
                    ],
                    'required' => ['list_name', 'card_names'],
                ],
            ],
        ];
    }

    /**
     * @param  array{name: string, args: array<string, mixed>}  $functionCall
     * @return array{content: string, tool_action: array<string, mixed>}
     */
    private function toolActionFromFunctionCall(array $functionCall): array
    {
        if ($functionCall['name'] === 'create_lists') {
            $names = $functionCall['args']['names'] ?? [];

            return [
                'content' => 'I\'ll create these lists: '.implode(', ', $names),
                'tool_action' => ['type' => 'create_lists', 'names' => $names],
            ];
        }

        if ($functionCall['name'] === 'create_cards') {
            $listName = $functionCall['args']['list_name'] ?? '';
            $cardNames = $functionCall['args']['card_names'] ?? [];

            return [
                'content' => "I'll add these cards to \"{$listName}\": ".implode(', ', $cardNames),
                'tool_action' => ['type' => 'create_cards', 'list_name' => $listName, 'card_names' => $cardNames],
            ];
        }

        throw new GeminiApiException("Gemini called an unknown tool: {$functionCall['name']}");
    }
}
```

- [ ] **Step 5: Wire config, `.env.example`, and the service binding**

In `config/services.php`, add inside the returned array:

```php
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
```

In `.env.example`, after the `AWS_*` block, add:

```
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.0-flash
```

In `app/Providers/AppServiceProvider.php`, replace the empty `register()` method body:

```php
    public function register(): void
    {
        $this->app->singleton(\App\Services\GeminiClient::class, fn () => new \App\Services\GeminiClient(
            (string) config('services.gemini.key'),
            (string) config('services.gemini.model'),
        ));
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=GeminiClientTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Exceptions/GeminiApiException.php app/Services/GeminiClient.php config/services.php .env.example app/Providers/AppServiceProvider.php tests/Unit/GeminiClientTest.php
git commit -m "feat: add GeminiClient service for text and function-call replies"
```

---

### Task 3: `AiChatController` — `show` and `sendMessage`

**Files:**
- Create: `app/Http/Requests/AiChat/StoreAiMessageRequest.php`
- Create: `app/Http/Controllers/AiChatController.php`
- Modify: `routes/boards.php`
- Test: `tests/Feature/AiChatTest.php`

**Interfaces:**
- Consumes: `AiConversation`, `AiMessage` (Task 1); `app(GeminiClient::class)->reply(...)` (Task 2).
- Produces: routes `ai-chat.show` (`GET boards/{board}/ai/conversation`) and `ai-chat.messages.store` (`POST boards/{board}/ai/messages`, body `{content: string}`). `show` returns JSON `{messages: AiMessage[]}`. `sendMessage` returns JSON `{message: AiMessage, reply: AiMessage}` on success or `{message: AiMessage, error: string}` (HTTP 502) on a Gemini failure. Task 4 adds `applyAction` to this same controller and file.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AiChatTest.php`:

```php
<?php

use App\Models\AiConversation;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function fakeGeminiTextReply(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => $text]]]],
            ],
        ], 200),
    ]);
}

function fakeGeminiFunctionCall(string $name, array $args): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => ['name' => $name, 'args' => $args]],
                ]]],
            ],
        ], 200),
    ]);
}

test('a board member can open the ai conversation for a board, created lazily', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    expect(AiConversation::count())->toBe(0);

    $response = $this->actingAs($user)->getJson("/boards/{$board->id}/ai/conversation");

    $response->assertOk()->assertJson(['messages' => []]);
    expect(AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('two board members get separate ai conversations', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $board->members()->attach($member->id);

    $this->actingAs($owner)->getJson("/boards/{$board->id}/ai/conversation")->assertOk();
    $this->actingAs($member)->getJson("/boards/{$board->id}/ai/conversation")->assertOk();

    expect(AiConversation::where('board_id', $board->id)->count())->toBe(2);
});

test('a non-member cannot open or send messages to a board ai conversation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson("/boards/{$board->id}/ai/conversation")->assertForbidden();
    $this->actingAs($outsider)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'hi'])->assertForbidden();
});

test('sending a message stores it and stores a plain-text assistant reply', function () {
    fakeGeminiTextReply('Here are some ideas for your board.');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'help me plan']);

    $response->assertOk();
    $response->assertJsonPath('reply.content', 'Here are some ideas for your board.');
    $response->assertJsonPath('reply.tool_action', null);

    $conversation = AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages->first()->role)->toBe('user');
    expect($conversation->messages->last()->role)->toBe('assistant');
});

test('sending a message that triggers create_lists stores the tool action', function () {
    fakeGeminiFunctionCall('create_lists', ['names' => ['Research', 'Design']]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'suggest lists']);

    $response->assertOk();
    $response->assertJsonPath('reply.tool_action', ['type' => 'create_lists', 'names' => ['Research', 'Design']]);
});

test('a failed gemini call keeps the user message but does not create a bogus assistant reply', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'help me plan']);

    $response->assertStatus(502);
    $response->assertJsonPath('error', "AI couldn't respond, try again.");

    $conversation = AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->role)->toBe('user');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AiChatTest`
Expected: FAIL — route `ai-chat.show` / controller not found.

- [ ] **Step 3: Write `StoreAiMessageRequest`**

```php
<?php

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('board'));
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

- [ ] **Step 4: Write `AiChatController`**

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Http\Requests\AiChat\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Board;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiChatController extends Controller
{
    public function show(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('view', $board);

        $conversation = AiConversation::firstOrCreate([
            'board_id' => $board->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'messages' => $conversation->messages,
        ]);
    }

    public function sendMessage(StoreAiMessageRequest $request, Board $board): JsonResponse
    {
        $conversation = AiConversation::firstOrCreate([
            'board_id' => $board->id,
            'user_id' => $request->user()->id,
        ]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
        ]);

        $lists = $board->lists()
            ->whereNull('archived_at')
            ->withCount(['cards' => fn ($query) => $query->whereNull('archived_at')])
            ->orderBy('position')
            ->get()
            ->map(fn ($list) => ['name' => $list->name, 'card_count' => $list->cards_count])
            ->all();

        $history = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        try {
            $result = app(GeminiClient::class)->reply($board->name, $lists, $history);
        } catch (GeminiApiException $exception) {
            report($exception);

            return response()->json([
                'message' => $userMessage,
                'error' => "AI couldn't respond, try again.",
            ], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'tool_action' => $result['tool_action'],
        ]);

        return response()->json([
            'message' => $userMessage,
            'reply' => $assistantMessage,
        ]);
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/boards.php`, add the import alongside the other controller imports:

```php
use App\Http\Controllers\AiChatController;
```

And add these two routes inside the existing `auth`+`verified` middleware group (anywhere near the other `boards/{board}/...` routes):

```php
    Route::get('boards/{board}/ai/conversation', [AiChatController::class, 'show'])->name('ai-chat.show');
    Route::post('boards/{board}/ai/messages', [AiChatController::class, 'sendMessage'])->name('ai-chat.messages.store');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=AiChatTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Requests/AiChat/StoreAiMessageRequest.php app/Http/Controllers/AiChatController.php routes/boards.php tests/Feature/AiChatTest.php
git commit -m "feat: add ai chat show/sendMessage endpoints"
```

---

### Task 4: `AiChatController::applyAction`

**Files:**
- Modify: `app/Http/Controllers/AiChatController.php`
- Modify: `routes/boards.php`
- Modify: `tests/Feature/AiChatTest.php`

**Interfaces:**
- Consumes: `AiConversation`, `AiMessage` (Task 1); `BoardPolicy::update` (existing).
- Produces: route `ai-chat.messages.apply` (`POST boards/{board}/ai/messages/{message}/apply`). Returns JSON `{success: true}` on success, or `{error: string}` with HTTP 422 for an unknown/missing/already-applied action or an unresolvable `list_name`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AiChatTest.php` (add `use App\Models\BoardList;` to the existing `use` block at the top of the file):

```php
test('applying a create_lists action creates the lists and marks the message applied', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will create these lists: Research, Design',
        'tool_action' => ['type' => 'create_lists', 'names' => ['Research', 'Design']],
    ]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages/{$message->id}/apply");

    $response->assertOk()->assertJson(['success' => true]);
    expect($board->lists()->pluck('name')->all())->toBe(['Research', 'Design']);
    expect($message->fresh()->applied_at)->not->toBeNull();
});

test('a viewer cannot apply an ai action', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $board->members()->attach($viewer->id, ['role' => 'viewer']);
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $viewer->id]);
    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will create these lists: Research',
        'tool_action' => ['type' => 'create_lists', 'names' => ['Research']],
    ]);

    $response = $this->actingAs($viewer)->postJson("/boards/{$board->id}/ai/messages/{$message->id}/apply");

    $response->assertForbidden();
    expect($board->lists()->count())->toBe(0);
});

test('applying a create_cards action with an unknown list name fails and creates nothing', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    BoardList::factory()->for($board)->create(['name' => 'Research']);
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will add cards to "Nope"',
        'tool_action' => ['type' => 'create_cards', 'list_name' => 'Nope', 'card_names' => ['Task 1']],
    ]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages/{$message->id}/apply");

    $response->assertStatus(422);
    expect(BoardList::where('name', 'Research')->first()->cards()->count())->toBe(0);
});

test('applying a create_cards action creates cards under the matching list', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['name' => 'Research']);
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will add cards to "Research"',
        'tool_action' => ['type' => 'create_cards', 'list_name' => 'research', 'card_names' => ['Competitor audit']],
    ]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages/{$message->id}/apply");

    $response->assertOk();
    expect($list->fresh()->cards()->pluck('name')->all())->toBe(['Competitor audit']);
});

test('applying an already-applied message is rejected', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will create these lists: Research',
        'tool_action' => ['type' => 'create_lists', 'names' => ['Research']],
        'applied_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages/{$message->id}/apply");

    $response->assertStatus(422);
    expect($board->lists()->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=AiChatTest`
Expected: the 5 new tests FAIL — route `ai-chat.messages.apply` doesn't exist yet.

- [ ] **Step 3: Add `applyAction` to `AiChatController`**

Add `use App\Models\AiMessage;` is already imported from Task 3; add this method to the class (and `use App\Models\BoardList;` is not needed in the controller itself — list lookup goes through `$board->lists()`):

```php
    public function applyAction(Request $request, Board $board, AiMessage $message): JsonResponse
    {
        Gate::authorize('update', $board);

        abort_unless($message->conversation->board_id === $board->id, 404);
        abort_unless($message->conversation->user_id === $request->user()->id, 404);

        if ($message->applied_at !== null) {
            return response()->json(['error' => 'This suggestion was already applied.'], 422);
        }

        $action = $message->tool_action;

        if (! is_array($action) || ! isset($action['type'])) {
            return response()->json(['error' => 'This message has no action to apply.'], 422);
        }

        if ($action['type'] === 'create_lists') {
            $names = array_values(array_filter($action['names'] ?? [], fn ($name) => trim((string) $name) !== ''));

            if ($names === []) {
                return response()->json(['error' => 'No list names to create.'], 422);
            }

            $position = ($board->lists()->max('position') ?? -1) + 1;

            foreach ($names as $name) {
                $board->lists()->create(['name' => $name, 'position' => $position++]);
            }
        } elseif ($action['type'] === 'create_cards') {
            $list = $board->lists()
                ->whereNull('archived_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) ($action['list_name'] ?? ''))])
                ->first();

            if (! $list) {
                return response()->json(['error' => "No list named \"{$action['list_name']}\" found on this board."], 422);
            }

            $cardNames = array_values(array_filter($action['card_names'] ?? [], fn ($name) => trim((string) $name) !== ''));

            if ($cardNames === []) {
                return response()->json(['error' => 'No card names to create.'], 422);
            }

            $position = ($list->cards()->max('position') ?? -1) + 1;

            foreach ($cardNames as $name) {
                $list->cards()->create(['name' => $name, 'position' => $position++]);
            }
        } else {
            return response()->json(['error' => "Unknown action type \"{$action['type']}\"."], 422);
        }

        $message->update(['applied_at' => now()]);

        return response()->json(['success' => true]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/boards.php`, add directly after the `ai-chat.messages.store` route:

```php
    Route::post('boards/{board}/ai/messages/{message}/apply', [AiChatController::class, 'applyAction'])->name('ai-chat.messages.apply');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=AiChatTest`
Expected: PASS (11 tests).

- [ ] **Step 6: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/AiChatController.php routes/boards.php tests/Feature/AiChatTest.php
git commit -m "feat: add ai chat applyAction endpoint for create_lists/create_cards"
```

---

### Task 5: `aiEnabled` board prop

**Files:**
- Modify: `app/Http/Controllers/BoardController.php`
- Modify: `tests/Feature/BoardTest.php`

**Interfaces:**
- Produces: the `boards/Show` Inertia page gains a boolean prop `aiEnabled`, `true` iff `config('services.gemini.key')` is non-empty. Task 6's `boards/Show.vue` consumes this prop directly.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/BoardTest.php` (imports `User`, `Board`, `Workspace` already present in this file):

```php
test('aiEnabled is true only when a gemini api key is configured', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    config(['services.gemini.key' => null]);
    $this->actingAs($user)->get("/boards/{$board->id}")
        ->assertInertia(fn ($page) => $page->where('aiEnabled', false));

    config(['services.gemini.key' => 'fake-key']);
    $this->actingAs($user)->get("/boards/{$board->id}")
        ->assertInertia(fn ($page) => $page->where('aiEnabled', true));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardTest`
Expected: FAIL — `aiEnabled` prop missing from the Inertia response.

- [ ] **Step 3: Add the prop**

In `app/Http/Controllers/BoardController.php`, in `show()`, add a line right after `'canEdit' => $request->user()->can('update', $board),`:

```php
            'aiEnabled' => filled(config('services.gemini.key')),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BoardTest`
Expected: PASS.

- [ ] **Step 5: Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/BoardController.php tests/Feature/BoardTest.php
git commit -m "feat: expose aiEnabled flag on the board page"
```

---

### Task 6: `AiChatWidget.vue` and mounting it on the board page

**Files:**
- Create: `resources/js/lib/csrfFetch.ts`
- Create: `resources/js/components/boards/AiChatWidget.vue`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/pages/boards/Show.vue`

**Interfaces:**
- Consumes: routes `ai-chat.show`, `ai-chat.messages.store`, `ai-chat.messages.apply` (Tasks 3-4); `aiEnabled` prop (Task 5).
- Produces: `<AiChatWidget :board-id="number" :ai-enabled="boolean" :can-edit="boolean" />`, a self-contained floating widget with no emitted events and no parent-managed state.

This task is frontend-only. There is no backend behavior to test with Pest; verify with `npx eslint --fix` and `npm run build` (no browser-automation tool is available in this environment, so this cannot be visually confirmed — say so when reporting the task done).

- [ ] **Step 1: Write the CSRF-aware fetch helper**

Create `resources/js/lib/csrfFetch.ts`:

```typescript
function readCookie(name: string): string {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : '';
}

export async function csrfFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');

    if (options.body) {
        headers.set('Content-Type', 'application/json');
    }

    const method = (options.method ?? 'GET').toUpperCase();

    if (method !== 'GET') {
        headers.set('X-XSRF-TOKEN', readCookie('XSRF-TOKEN'));
    }

    return fetch(url, { ...options, headers, credentials: 'same-origin' });
}
```

This exists because the widget talks to plain JSON endpoints (not Inertia page visits), so Inertia's automatic CSRF handling doesn't apply — Laravel's default cookie-based CSRF check just needs the `XSRF-TOKEN` cookie value echoed back as an `X-XSRF-TOKEN` header on non-GET requests.

- [ ] **Step 2: Add the `AiMessage` type**

In `resources/js/types/index.ts`, add near the other feature interfaces (e.g. after `CardActivity`):

```typescript
export type AiToolAction =
    | { type: 'create_lists'; names: string[] }
    | { type: 'create_cards'; list_name: string; card_names: string[] };

export interface AiMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    tool_action: AiToolAction | null;
    applied_at: string | null;
    created_at: string;
    updated_at: string;
}
```

- [ ] **Step 3: Write `AiChatWidget.vue`**

Create `resources/js/components/boards/AiChatWidget.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { AiMessage } from '@/types';
import { router } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Send, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    boardId: number;
    aiEnabled: boolean;
    canEdit: boolean;
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<AiMessage[]>([]);
const appliedIds = ref<Set<number>>(new Set());
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
        const response = await csrfFetch(route('ai-chat.show', props.boardId));
        const data = await response.json();
        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
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
        const response = await csrfFetch(route('ai-chat.messages.store', props.boardId), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        messages.value.push(data.message);

        if (data.reply) {
            messages.value.push(data.reply);
        } else if (data.error) {
            error.value = data.error;
        }

        await scrollToBottom();
    } finally {
        loading.value = false;
    }
}

async function applyMessage(message: AiMessage) {
    const response = await csrfFetch(route('ai-chat.messages.apply', [props.boardId, message.id]), {
        method: 'POST',
    });
    const data = await response.json();

    if (response.ok) {
        appliedIds.value.add(message.id);
        router.reload({ only: ['board'] });
    } else {
        error.value = data.error ?? 'Could not apply this suggestion.';
    }
}

function isApplied(message: AiMessage): boolean {
    return message.applied_at !== null || appliedIds.value.has(message.id);
}

function actionSummary(message: AiMessage): string {
    if (!message.tool_action) {
        return '';
    }

    if (message.tool_action.type === 'create_lists') {
        return `Create lists: ${message.tool_action.names.join(', ')}`;
    }

    return `Add cards to "${message.tool_action.list_name}": ${message.tool_action.card_names.join(', ')}`;
}
</script>

<template>
    <div class="fixed bottom-4 right-4 z-40">
        <button
            type="button"
            class="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:opacity-90"
            aria-label="AI board assistant"
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
                <p class="text-sm font-semibold">Board assistant</p>
                <p class="text-xs text-muted-foreground">Brainstorm lists and cards for this board.</p>
            </div>

            <div v-if="!aiEnabled" class="flex flex-1 items-center justify-center p-4 text-center text-sm text-muted-foreground">
                AI chat isn't configured yet.
            </div>

            <template v-else>
                <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <div v-for="message in messages" :key="message.id" :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                            :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                        >
                            <p>{{ message.content }}</p>
                            <div v-if="message.tool_action" class="mt-2 rounded border border-border/50 bg-background/50 p-2">
                                <p class="text-xs">{{ actionSummary(message) }}</p>
                                <Button v-if="canEdit && !isApplied(message)" size="sm" class="mt-1.5 w-full" @click="applyMessage(message)">
                                    Add to board
                                </Button>
                                <p v-else-if="isApplied(message)" class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    Added
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

                <form class="flex items-center gap-2 border-t border-border p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Ask for list or card ideas..."
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

- [ ] **Step 4: Mount the widget on the board page**

In `resources/js/pages/boards/Show.vue`, add the import alongside the other component imports:

```typescript
import AiChatWidget from '@/components/boards/AiChatWidget.vue';
```

Add `aiEnabled: boolean;` to the `defineProps<{...}>()` block (it currently ends with `canEdit: boolean;`):

```typescript
const props = defineProps<{
    board: Board;
    archivedLists: BoardList[];
    archivedCards: Card[];
    initialCardId: number | null;
    canEdit: boolean;
    aiEnabled: boolean;
}>();
```

At the end of the template, add the widget alongside the other panels (after the existing `<BoardMemberPanel ... />` line):

```html
        <AiChatWidget :board-id="board.id" :ai-enabled="aiEnabled" :can-edit="canEdit" />
```

- [ ] **Step 5: Lint and build**

Run: `npx eslint resources/js/lib/csrfFetch.ts resources/js/components/boards/AiChatWidget.vue resources/js/pages/boards/Show.vue --fix`
Expected: no errors.

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 6: Commit**

```bash
git add resources/js/lib/csrfFetch.ts resources/js/components/boards/AiChatWidget.vue resources/js/types/index.ts resources/js/pages/boards/Show.vue
git commit -m "feat: add floating AI board assistant widget to the board page"
```

---

### Task 7: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test --compact`
Expected: all tests pass, including the new `AiConversationTest`, `GeminiClientTest`, and `AiChatTest` files and the appended `BoardTest` case.

- [ ] **Step 2: Confirm Pint is clean**

Run: `vendor/bin/pint --format agent`
Expected: `{"tool":"pint","result":"passed"}` with no remaining diffs.

- [ ] **Step 3: Report the manual step the user still owns**

The feature is inert until a real API key is set. Tell the user: get a free key from Google AI Studio (https://aistudio.google.com/apikey), add `GEMINI_API_KEY=<key>` to their local `.env`, then restart `php artisan serve`/`composer run dev` — the widget will show a working composer instead of "AI chat isn't configured yet." No code changes are needed for this step.
