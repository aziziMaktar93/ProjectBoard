# Board Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a shared, board-scoped group chat (polling-based, `@mention` notifications, delete-own-message) as a floating widget on the board page, alongside the existing AI Board Assistant widget.

**Architecture:** A new `BoardMessage` model (`board_id`, `user_id`, `content`) gives every board member a single shared thread per board — no per-user "conversation" wrapper is needed since this chat isn't scoped per-user like the AI features. A nullable `chat_last_read_at` column added to the existing `board_user` pivot tracks each member's read position for the unread badge. Three new REST endpoints (list/create/delete) follow the existing `ai-chat.*` route conventions in `routes/boards.php`. The frontend is a new `BoardChatWidget.vue` that mirrors `AiChatWidget.vue`'s structure (floating button, slide-up panel) but polls on an interval instead of being purely request/response, and highlights `@mentions` the same way `DashboardChatWidget.vue` highlights `[[Board Name]]` links. Mentions reuse the exact substring-match technique already in `CardActivityController::notifyMentionedMembers()`, feeding into a new `board_message_mention` notification type.

**Tech Stack:** Laravel 12 / Pest 3 (backend), Vue 3 `<script setup lang="ts">` + Inertia v2 (frontend). No new dependencies.

## Global Constraints

- Follow `routes/boards.php`'s existing route-naming and controller-per-resource conventions.
- `Gate::authorize('view', $board)` for read/post (viewers may chat); ownership check only (`user_id` match) for delete — no new policy method needed.
- No new PHP dependencies. No WebSocket/broadcasting infrastructure — polling only.
- PHP: curly braces always, constructor property promotion, explicit return types, `casts()` method not `$casts` property.
- Every task needs a Pest test; run `vendor/bin/pint --dirty --format agent` after PHP changes (never an unscoped `pint` run).
- Vue: single root element per component, `<script setup lang="ts">`.
- `content` validation: `required|string|max:2000` (matches `StoreAiMessageRequest`).
- Send endpoint throttled `throttle:30,1` (matches the app's existing throttle convention on message-send routes, slightly higher than AI's `20,1` since human typing/sending is faster than waiting on an AI reply).

---

### Task 1: `BoardMessage` model, migrations, and factory

**Files:**
- Create: `database/migrations/2026_09_03_000001_create_board_messages_table.php`
- Create: `database/migrations/2026_09_03_000002_add_chat_last_read_at_to_board_user_table.php`
- Create: `app/Models/BoardMessage.php`
- Create: `database/factories/BoardMessageFactory.php`
- Modify: `app/Models/Board.php` (add `messages()` relation)
- Test: `tests/Feature/BoardMessageModelTest.php`

**Interfaces:**
- Produces: `BoardMessage` model with fillable `['board_id', 'user_id', 'content']`, `belongsTo` relations `board()` and `user()`. `Board::messages(): HasMany` returning `BoardMessage` ordered by nothing special (controllers will order explicitly). `board_user` pivot gains `chat_last_read_at` (nullable timestamp), accessible via `$board->members()->withPivot([...,'chat_last_read_at'])` — this task updates `Board::members()` to include it in `withPivot`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\User;
use App\Models\Workspace;

test('a board message belongs to a board and a user', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $message = BoardMessage::factory()->for($board)->for($user)->create(['content' => 'Hello team']);

    expect($message->board->id)->toBe($board->id);
    expect($message->user->id)->toBe($user->id);
    expect($board->messages)->toHaveCount(1);
});

test('deleting a board deletes its messages', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    BoardMessage::factory()->for($board)->for($user)->create();

    $board->delete();

    expect(BoardMessage::count())->toBe(0);
});

test('board_user pivot has a nullable chat_last_read_at column', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $pivot = $board->members()->where('users.id', $user->id)->first()->pivot;

    expect($pivot->chat_last_read_at)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardMessageModelTest`
Expected: FAIL — `BoardMessage` class/table does not exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_09_03_000001_create_board_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_messages');
    }
};
```

`database/migrations/2026_09_03_000002_add_chat_last_read_at_to_board_user_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_user', function (Blueprint $table) {
            $table->timestamp('chat_last_read_at')->nullable()->after('is_favourite');
        });
    }

    public function down(): void
    {
        Schema::table('board_user', function (Blueprint $table) {
            $table->dropColumn('chat_last_read_at');
        });
    }
};
```

- [ ] **Step 4: Write the model**

`app/Models/BoardMessage.php`:

```php
<?php

namespace App\Models;

use Database\Factories\BoardMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardMessage extends Model
{
    /** @use HasFactory<BoardMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_id',
        'user_id',
        'content',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`database/factories/BoardMessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMessage>
 */
class BoardMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Add the `messages()` relation to `Board`**

In `app/Models/Board.php`, add alongside the other `HasMany` relations (e.g. after `events()`):

```php
    public function messages(): HasMany
    {
        return $this->hasMany(BoardMessage::class);
    }
```

And update the `withPivot` call in `members()` to include the new column:

```php
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user')->withPivot(['role', 'is_favourite', 'chat_last_read_at'])->withTimestamps();
    }
```

- [ ] **Step 6: Run migrations and the test**

Run: `php artisan migrate` then `php artisan test --compact --filter=BoardMessageModelTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_03_000001_create_board_messages_table.php database/migrations/2026_09_03_000002_add_chat_last_read_at_to_board_user_table.php app/Models/BoardMessage.php app/Models/Board.php database/factories/BoardMessageFactory.php tests/Feature/BoardMessageModelTest.php
git commit -m "feat: add BoardMessage model and chat_last_read_at pivot column"
```

---

### Task 2: List, send, and delete message endpoints

**Files:**
- Create: `app/Http/Controllers/BoardChatController.php`
- Create: `app/Http/Requests/BoardChat/StoreBoardMessageRequest.php`
- Modify: `routes/boards.php` (add three routes)
- Test: `tests/Feature/BoardChatTest.php`

**Interfaces:**
- Consumes: `BoardMessage` model and `Board::messages()` from Task 1.
- Produces: JSON routes `board-chat.index` (GET), `board-chat.store` (POST), `board-chat.destroy` (DELETE), all under `boards/{board}/chat/messages[/{message}]`. Response shape for the index/store actions: `{ "messages": [{ id, board_id, user_id, content, created_at, updated_at, user: { id, name } }, ...] }` for index, and `{ "message": {...} }` for store.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/BoardChatTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

test('a board member can list and post chat messages', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->getJson("/boards/{$board->id}/chat/messages")
        ->assertOk()
        ->assertJson(['messages' => []]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Hello team']);

    $response->assertOk();
    $response->assertJsonPath('message.content', 'Hello team');
    $response->assertJsonPath('message.user.id', $user->id);
    expect(BoardMessage::where('board_id', $board->id)->count())->toBe(1);
});

test('a board viewer can list and post chat messages', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->getJson("/boards/{$board->id}/chat/messages")->assertOk();
    $this->actingAs($viewer)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Just a viewer, saying hi'])
        ->assertOk();

    expect(BoardMessage::where('board_id', $board->id)->count())->toBe(1);
});

test('a non-member cannot list or post chat messages', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson("/boards/{$board->id}/chat/messages")->assertForbidden();
    $this->actingAs($outsider)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'hi'])->assertForbidden();
});

test('posting an empty message fails validation', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->postJson("/boards/{$board->id}/chat/messages", ['content' => ''])
        ->assertStatus(422);
});

test('mentioning a member by name creates a board_message_mention notification for them only', function () {
    $author = User::factory()->create();
    $workspace = Workspace::factory()->for($author, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($author)->create(['name' => 'Launch Plan']);
    $mentioned = User::factory()->create(['name' => 'Jamie Lee']);
    $notMentioned = User::factory()->create(['name' => 'Alex Kim']);
    $workspace->members()->attach([$mentioned->id, $notMentioned->id]);
    $board->members()->attach([$mentioned->id, $notMentioned->id]);

    $this->actingAs($author)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Hey @Jamie Lee can you check this?'])
        ->assertOk();

    expect(Notification::where('user_id', $mentioned->id)->where('type', 'board_message_mention')->count())->toBe(1);
    expect(Notification::where('user_id', $notMentioned->id)->count())->toBe(0);
    expect(Notification::where('user_id', $author->id)->count())->toBe(0);

    $notification = Notification::where('user_id', $mentioned->id)->first();
    expect($notification->data['board_id'])->toBe($board->id);
    expect($notification->data['board_name'])->toBe('Launch Plan');
    expect($notification->data['actor_name'])->toBe($author->name);
});

test('a user can delete their own message but not someone else\'s', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);
    $board->members()->attach($other->id);

    $ownMessage = BoardMessage::factory()->for($board)->for($owner)->create();
    $othersMessage = BoardMessage::factory()->for($board)->for($other)->create();

    $this->actingAs($owner)->deleteJson("/boards/{$board->id}/chat/messages/{$othersMessage->id}")
        ->assertForbidden();
    expect(BoardMessage::find($othersMessage->id))->not->toBeNull();

    $this->actingAs($owner)->deleteJson("/boards/{$board->id}/chat/messages/{$ownMessage->id}")
        ->assertOk();
    expect(BoardMessage::find($ownMessage->id))->toBeNull();
});

test('listing messages updates the requesting users chat_last_read_at pivot value', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->getJson("/boards/{$board->id}/chat/messages")->assertOk();

    $pivot = $board->members()->where('users.id', $user->id)->first()->pivot;
    expect($pivot->chat_last_read_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=BoardChatTest`
Expected: FAIL — route `board-chat.index` etc. not defined (404s).

- [ ] **Step 3: Write the form request**

`app/Http/Requests/BoardChat/StoreBoardMessageRequest.php`:

```php
<?php

namespace App\Http\Requests\BoardChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardMessageRequest extends FormRequest
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

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/BoardChatController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardChat\StoreBoardMessageRequest;
use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardChatController extends Controller
{
    public function index(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('view', $board);

        $messages = $board->messages()->with('user:id,name')->oldest()->get();

        $board->members()->updateExistingPivot($request->user()->id, ['chat_last_read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function store(StoreBoardMessageRequest $request, Board $board): JsonResponse
    {
        $message = $board->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $this->notifyMentionedMembers($board, $message, $request->user());

        return response()->json(['message' => $message->load('user:id,name')]);
    }

    public function destroy(Request $request, Board $board, BoardMessage $message): JsonResponse
    {
        Gate::authorize('view', $board);

        abort_unless($message->board_id === $board->id, 404);
        abort_unless($message->user_id === $request->user()->id, 403);

        $message->delete();

        return response()->json(['success' => true]);
    }

    private function notifyMentionedMembers(Board $board, BoardMessage $message, User $author): void
    {
        foreach ($board->members as $member) {
            if ($member->id === $author->id || ! str_contains($message->content, '@'.$member->name)) {
                continue;
            }

            Notification::create([
                'user_id' => $member->id,
                'type' => 'board_message_mention',
                'data' => [
                    'board_id' => $board->id,
                    'board_name' => $board->name,
                    'actor_name' => $author->name,
                    'message_preview' => str($message->content)->limit(80)->toString(),
                ],
            ]);
        }
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/boards.php`, add the `BoardChatController` import alongside the others, and add these three lines near the existing `ai-chat.*` routes:

```php
    Route::get('boards/{board}/chat/messages', [BoardChatController::class, 'index'])->name('board-chat.index');
    Route::post('boards/{board}/chat/messages', [BoardChatController::class, 'store'])->name('board-chat.store')->middleware('throttle:30,1');
    Route::delete('boards/{board}/chat/messages/{message}', [BoardChatController::class, 'destroy'])->name('board-chat.destroy');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=BoardChatTest`
Expected: PASS (7 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BoardChatController.php app/Http/Requests/BoardChat/StoreBoardMessageRequest.php routes/boards.php tests/Feature/BoardChatTest.php
git commit -m "feat: add board chat list/send/delete endpoints with @mention notifications"
```

---

### Task 3: `board_message_mention` notification rendering

**Files:**
- Modify: `resources/js/types/index.ts` (extend `AppNotification`)
- Modify: `app/Http/Controllers/NotificationController.php` (`open()` method)
- Modify: `resources/js/components/NotificationBell.vue`
- Modify: `resources/js/pages/Notifications.vue`
- Test: `tests/Feature/BoardChatNotificationTest.php`

**Interfaces:**
- Consumes: `Notification` records of type `board_message_mention` created in Task 2, with `data: { board_id, board_name, actor_name, message_preview }`.
- Produces: `AppNotification['type']` union includes `'board_message_mention'`; `AppNotification['data']` gains the optional fields needed to render it without breaking the existing `card_id`/`card_name`-shaped notifications.

- [ ] **Step 1: Write the failing test**

`tests/Feature/BoardChatNotificationTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

test('opening a board_message_mention notification redirects to the board without a card param', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'board_message_mention',
        'data' => [
            'board_id' => $board->id,
            'board_name' => $board->name,
            'actor_name' => 'Jamie Lee',
            'message_preview' => 'hey there',
        ],
    ]);

    $response = $this->actingAs($user)->get("/notifications/{$notification->id}/open");

    $response->assertRedirect(route('boards.show', $board->id));
    expect($notification->fresh()->read_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardChatNotificationTest`
Expected: FAIL — current `open()` always appends a `card` query param sourced from `$notification->data['card_id']`, which doesn't exist for this type, so the redirect URL won't match `route('boards.show', $board->id)` (it will include an empty/undefined `card` param).

- [ ] **Step 3: Update `NotificationController::open()`**

In `app/Http/Controllers/NotificationController.php`, replace the `open()` method body:

```php
    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        if (! array_key_exists('card_id', $notification->data)) {
            return redirect()->route('boards.show', ['board' => $notification->data['board_id']]);
        }

        return redirect()->route('boards.show', [
            'board' => $notification->data['board_id'],
            'card' => $notification->data['card_id'],
        ]);
    }
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=BoardChatNotificationTest`
Expected: PASS

- [ ] **Step 5: Update the TypeScript type**

In `resources/js/types/index.ts`, change the `AppNotification` interface's `type` union and `data` shape:

```typescript
export interface AppNotification {
    id: number;
    user_id: number;
    type: 'card_assigned' | 'mention' | 'checklist_item_assigned' | 'board_message_mention';
    data: {
        card_id?: number;
        card_name?: string;
        board_id: number;
        board_name?: string;
        actor_name: string;
        item_name?: string;
        message_preview?: string;
    };
    read_at: string | null;
    created_at: string;
    updated_at: string;
}
```

(`card_id` and `card_name` become optional since `board_message_mention` notifications don't have them; every other existing usage already only reads these fields when the relevant `type` matches, so this is not a breaking change.)

- [ ] **Step 6: Update `NotificationBell.vue`'s `sentenceFor()`**

In `resources/js/components/NotificationBell.vue`, add a case before the final fallback:

```typescript
function sentenceFor(notification: AppNotification): string {
    if (notification.type === 'mention') {
        return `${notification.data.actor_name} mentioned you on "${notification.data.card_name}"`;
    }

    if (notification.type === 'board_message_mention') {
        return `${notification.data.actor_name} mentioned you in "${notification.data.board_name}" chat`;
    }

    if (notification.type === 'checklist_item_assigned') {
        return `${notification.data.actor_name} assigned you to "${notification.data.item_name}" on "${notification.data.card_name}"`;
    }

    return `${notification.data.actor_name} assigned you to "${notification.data.card_name}"`;
}
```

- [ ] **Step 7: Update `Notifications.vue`**

In `resources/js/pages/Notifications.vue`:

1. Add the same `board_message_mention` branch to its `sentenceFor()` (identical to Step 6's addition).
2. Import `MessageSquare` from `lucide-vue-next` alongside the existing icon imports.
3. In the icon `<span>`'s `:class` object, add: `'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400': notification.type === 'board_message_mention',`
4. Add `<MessageSquare v-else-if="notification.type === 'board_message_mention'" class="size-4" />` before the final `<UserPlus v-else ... />` fallback icon.

- [ ] **Step 8: Format, build, and commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/NotificationController.php resources/js/types/index.ts resources/js/components/NotificationBell.vue resources/js/pages/Notifications.vue tests/Feature/BoardChatNotificationTest.php
git commit -m "feat: render board chat mention notifications"
```

---

### Task 4: `BoardChatWidget.vue` — display, send, delete (no polling yet)

**Files:**
- Create: `resources/js/components/boards/BoardChatWidget.vue`
- Modify: `resources/js/pages/boards/Show.vue` (mount the widget)
- Test: manual verification only for this task (no Pest coverage for a pure Vue component in this codebase's conventions — `AiChatWidget.vue` has none either); Task 2's backend tests already cover the endpoints this component calls.

**Interfaces:**
- Consumes: `board-chat.index` (GET, returns `{ messages: BoardMessage[] }`), `board-chat.store` (POST `{ content }`, returns `{ message: BoardMessage }`), `board-chat.destroy` (DELETE), where `BoardMessage = { id: number; board_id: number; user_id: number; content: string; created_at: string; updated_at: string; user: { id: number; name: string } }`. Add this type to `resources/js/types/index.ts` in this task.
- Produces: `BoardChatWidget.vue` accepting props `{ boardId: number; currentUserId: number; members: { id: number; name: string }[] }`. No polling logic yet — that's Task 5.

- [ ] **Step 1: Add the `BoardMessage` type**

In `resources/js/types/index.ts`, add near `AiMessage`:

```typescript
export interface BoardMessage {
    id: number;
    board_id: number;
    user_id: number;
    content: string;
    created_at: string;
    updated_at: string;
    user: { id: number; name: string };
}
```

- [ ] **Step 2: Write the component**

`resources/js/components/boards/BoardChatWidget.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { BoardMessage } from '@/types';
import { MessageCircle, Send, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    boardId: number;
    currentUserId: number;
    members: { id: number; name: string }[];
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<BoardMessage[]>([]);
const scrollRef = ref<HTMLElement | null>(null);
const mentionQuery = ref<string | null>(null);

const mentionMatches = computed(() => {
    if (mentionQuery.value === null) {
        return [];
    }

    const query = mentionQuery.value.toLowerCase();

    return props.members.filter((member) => member.name.toLowerCase().startsWith(query)).slice(0, 5);
});

async function scrollToBottom() {
    await nextTick();
    scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight });
}

async function toggleOpen() {
    open.value = !open.value;

    if (open.value && !loaded.value) {
        await loadMessages();
    }
}

async function loadMessages() {
    loading.value = true;

    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));
        const data = await response.json();

        if (!response.ok) {
            error.value = 'Could not load the chat.';
            return;
        }

        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
    } finally {
        loading.value = false;
    }
}

function onDraftInput() {
    const cursor = draft.value.length;
    const upToCursor = draft.value.slice(0, cursor);
    const match = /@([\w ]*)$/.exec(upToCursor);
    mentionQuery.value = match ? match[1] : null;
}

function selectMention(name: string) {
    draft.value = draft.value.replace(/@([\w ]*)$/, `@${name} `);
    mentionQuery.value = null;
}

async function send() {
    const content = draft.value.trim();

    if (!content || loading.value) {
        return;
    }

    draft.value = '';
    mentionQuery.value = null;
    error.value = null;
    loading.value = true;

    try {
        const response = await csrfFetch(route('board-chat.store', props.boardId), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        if (!response.ok) {
            error.value = typeof data.message === 'string' ? data.message : 'Could not send that message.';
            draft.value = content;
            return;
        }

        messages.value.push(data.message);
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
        draft.value = content;
    } finally {
        loading.value = false;
    }
}

async function deleteMessage(message: BoardMessage) {
    if (!confirm('Delete this message?')) {
        return;
    }

    try {
        const response = await csrfFetch(route('board-chat.destroy', [props.boardId, message.id]), {
            method: 'DELETE',
        });

        if (response.ok) {
            messages.value = messages.value.filter((m) => m.id !== message.id);
        } else {
            error.value = 'Could not delete this message.';
        }
    } catch {
        error.value = "Couldn't reach the server, try again.";
    }
}

function renderSegments(content: string): { text: string; mention: boolean }[] {
    const names = props.members.map((member) => member.name).sort((a, b) => b.length - a.length);

    if (names.length === 0) {
        return [{ text: content, mention: false }];
    }

    const pattern = new RegExp(`@(${names.map((name) => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'g');
    const segments: { text: string; mention: boolean }[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(content)) !== null) {
        if (match.index > lastIndex) {
            segments.push({ text: content.slice(lastIndex, match.index), mention: false });
        }

        segments.push({ text: match[0], mention: true });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < content.length) {
        segments.push({ text: content.slice(lastIndex), mention: false });
    }

    return segments;
}
</script>

<template>
    <div class="fixed bottom-4 right-20 z-40">
        <button
            type="button"
            class="flex size-12 items-center justify-center rounded-full bg-secondary text-secondary-foreground shadow-lg transition hover:opacity-90"
            aria-label="Board chat"
            @click="toggleOpen"
        >
            <X v-if="open" class="size-5" />
            <MessageCircle v-else class="size-5" />
        </button>

        <div
            v-if="open"
            class="absolute bottom-16 right-0 flex h-[28rem] w-80 flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl"
        >
            <div class="border-b border-border px-4 py-3">
                <p class="text-sm font-semibold">Board chat</p>
                <p class="text-xs text-muted-foreground">Talk with everyone on this board.</p>
            </div>

            <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                <div v-for="message in messages" :key="message.id" class="group flex flex-col" :class="message.user_id === currentUserId ? 'items-end' : 'items-start'">
                    <div
                        class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                        :class="message.user_id === currentUserId ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                    >
                        <p class="text-xs font-medium opacity-70">{{ message.user.name }}</p>
                        <p>
                            <template v-for="(segment, index) in renderSegments(message.content)" :key="index">
                                <span v-if="segment.mention" class="font-semibold underline">{{ segment.text }}</span>
                                <template v-else>{{ segment.text }}</template>
                            </template>
                        </p>
                    </div>
                    <button
                        v-if="message.user_id === currentUserId"
                        type="button"
                        class="mt-0.5 hidden text-xs text-muted-foreground hover:text-destructive group-hover:flex"
                        @click="deleteMessage(message)"
                    >
                        <Trash2 class="mr-1 size-3" /> Delete
                    </button>
                </div>
            </div>

            <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

            <div class="relative border-t border-border">
                <ul v-if="mentionMatches.length" class="absolute bottom-full left-0 w-full border-b border-border bg-background shadow-sm">
                    <li v-for="member in mentionMatches" :key="member.id">
                        <button
                            type="button"
                            class="block w-full px-3 py-1.5 text-left text-sm hover:bg-accent"
                            @click="selectMention(member.name)"
                        >
                            {{ member.name }}
                        </button>
                    </li>
                </ul>

                <form class="flex items-center gap-2 p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Message the board..."
                        class="flex-1 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        :disabled="loading"
                        @input="onDraftInput"
                    />
                    <Button type="submit" size="icon" :disabled="loading || !draft.trim()">
                        <Send class="size-4" />
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Mount the widget on the board page**

In `resources/js/pages/boards/Show.vue`, add the imports near the other widget import:

```typescript
import BoardChatWidget from '@/components/boards/BoardChatWidget.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
```

Note: if `usePage` or `router` is already imported from `@inertiajs/vue3` in this file, add `usePage` to that existing import statement instead of creating a second one; same for a pre-existing `import type { ... } from '@/types'` line — add `SharedData` to it rather than duplicating the import.

Add this near the other `computed`/setup code (top of `<script setup>`, after props/existing refs):

```typescript
const currentUserId = computed(() => usePage<SharedData>().props.auth.user.id);
```

(If `computed` isn't already imported from `'vue'` in this file, add it to the existing Vue import statement.)

And add the component right after the existing `<AiChatWidget ...>` line (around line 564):

```vue
        <AiChatWidget :board-id="board.id" :ai-enabled="aiEnabled" :can-edit="canEdit" />
        <BoardChatWidget :board-id="board.id" :current-user-id="currentUserId" :members="board.members ?? []" />
```

- [ ] **Step 4: Build and manually sanity-check**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/index.ts resources/js/components/boards/BoardChatWidget.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add board chat widget UI (send, receive, delete, mentions)"
```

---

### Task 5: Polling and unread badge

**Files:**
- Modify: `resources/js/components/boards/BoardChatWidget.vue`

**Interfaces:**
- Consumes: everything from Task 4.
- Produces: no new external interface — this task only adds internal polling behavior and an unread-count badge on the floating button.

- [ ] **Step 1: Add polling state and lifecycle hooks**

In `resources/js/components/boards/BoardChatWidget.vue`, update the `<script setup>` imports and add polling logic:

```typescript
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
```

Add new refs near the top, after `mentionQuery`:

```typescript
const unreadCount = ref(0);
const lastSeenId = ref(0);
let openPollTimer: ReturnType<typeof setInterval> | null = null;
let closedPollTimer: ReturnType<typeof setInterval> | null = null;
```

- [ ] **Step 2: Add the closed-state background poll**

Add these functions after `loadMessages()`:

```typescript
async function checkForUnread() {
    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const fetched: BoardMessage[] = data.messages;
        const newest = fetched.length ? fetched[fetched.length - 1] : null;

        if (newest && newest.id > lastSeenId.value) {
            unreadCount.value = fetched.filter((m) => m.id > lastSeenId.value && m.user_id !== props.currentUserId).length;
        }
    } catch {
        // Silent — this is a background refresh, not a user-initiated action.
    }
}

function startClosedPolling() {
    stopClosedPolling();
    closedPollTimer = setInterval(checkForUnread, 15000);
}

function stopClosedPolling() {
    if (closedPollTimer) {
        clearInterval(closedPollTimer);
        closedPollTimer = null;
    }
}
```

- [ ] **Step 3: Add the open-state live poll**

```typescript
async function pollForNewMessages() {
    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const fetched: BoardMessage[] = data.messages;
        const existingIds = new Set(messages.value.map((m) => m.id));
        const hasNew = fetched.some((m) => !existingIds.has(m.id));

        if (hasNew || fetched.length !== messages.value.length) {
            const wasAtBottom = scrollRef.value ? scrollRef.value.scrollHeight - scrollRef.value.scrollTop - scrollRef.value.clientHeight < 40 : true;
            messages.value = fetched;

            if (wasAtBottom) {
                await scrollToBottom();
            }
        }
    } catch {
        // Silent — background refresh.
    }
}

function startOpenPolling() {
    stopOpenPolling();
    openPollTimer = setInterval(pollForNewMessages, 5000);
}

function stopOpenPolling() {
    if (openPollTimer) {
        clearInterval(openPollTimer);
        openPollTimer = null;
    }
}
```

- [ ] **Step 4: Wire polling into `toggleOpen()` and lifecycle**

Replace `toggleOpen()`:

```typescript
async function toggleOpen() {
    open.value = !open.value;

    if (open.value) {
        stopClosedPolling();
        unreadCount.value = 0;

        if (!loaded.value) {
            await loadMessages();
        }

        if (messages.value.length) {
            lastSeenId.value = messages.value[messages.value.length - 1].id;
        }

        startOpenPolling();
    } else {
        stopOpenPolling();

        if (messages.value.length) {
            lastSeenId.value = messages.value[messages.value.length - 1].id;
        }

        startClosedPolling();
    }
}
```

Add lifecycle hooks after the function declarations, before the `</script>` close:

```typescript
onMounted(() => {
    startClosedPolling();
});

onBeforeUnmount(() => {
    stopClosedPolling();
    stopOpenPolling();
});
```

Also update `send()` — after `messages.value.push(data.message)`, add `lastSeenId.value = data.message.id;` so a message the current user just sent doesn't count toward their own unread badge.

- [ ] **Step 5: Add the badge to the template**

In the floating button in the `<template>`, add a badge span:

```vue
        <button
            type="button"
            class="relative flex size-12 items-center justify-center rounded-full bg-secondary text-secondary-foreground shadow-lg transition hover:opacity-90"
            aria-label="Board chat"
            @click="toggleOpen"
        >
            <X v-if="open" class="size-5" />
            <MessageCircle v-else class="size-5" />
            <span
                v-if="!open && unreadCount > 0"
                class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>
```

- [ ] **Step 6: Build**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/boards/BoardChatWidget.vue
git commit -m "feat: add polling and unread badge to board chat widget"
```

---

### Task 6: Final verification pass

**Files:** None created — this task only runs the full verification chain and fixes anything it surfaces.

**Interfaces:** N/A.

- [ ] **Step 1: Full backend test suite**

Run: `php artisan test --compact`
Expected: all tests pass, including every `BoardChatTest`, `BoardChatNotificationTest`, and `BoardMessageModelTest` case from earlier tasks.

- [ ] **Step 2: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes with no changes, or auto-fixes any remaining style issues in files touched by this plan.

- [ ] **Step 3: Frontend build**

Run: `npm run build`
Expected: succeeds with no TypeScript or Vue compiler errors.

- [ ] **Step 4: Commit any fixes**

If Steps 1-3 required changes, stage exactly the files touched and commit:

```bash
git add -A -- . ':!debug.log'
git commit -m "fix: address issues found in board chat final verification"
```

If no changes were needed, skip this step.
