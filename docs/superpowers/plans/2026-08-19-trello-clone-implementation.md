# Trello Clone Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a multi-board Trello clone (boards → lists → cards, drag-and-drop reordering, archive-then-delete workflow) on top of the existing Laravel 12 + Inertia v2 + Vue 3 starter kit in `trellow`.

**Architecture:** Three new Eloquent models (`Board`, `BoardList`, `Card`) each scoped to their owning user via `Board.user_id`, exposed through three Inertia-driven controllers (`BoardController`, `BoardListController`, `CardController`) authorized by a single `BoardPolicy`. The frontend adds a `boards/` page group and a `components/boards/` component set, using `vue-draggable-plus` for drag-and-drop reordering wired to two dedicated reorder endpoints.

**Tech Stack:** Laravel 12 (PHP 8.2), Pest 3, Inertia v2, Vue 3.5, TypeScript, Tailwind CSS 3, shadcn-vue (radix-vue) components already installed, `vue-draggable-plus` (new dependency).

**Reference:** `docs/superpowers/specs/2026-08-19-trello-clone-design.md`

## Global Constraints

- Card fields are limited to `name` and `description` — no due dates, labels, or comments (out of scope this iteration).
- No board sharing/invites — every board, list, and card belongs to exactly one user (the board's `user_id`).
- Delete workflow is archive-then-permanently-delete at all three levels (board, list, card). Permanent delete (`destroy`) must be rejected with a `422` unless the record is already archived.
- `position` is a plain integer, fully re-indexed (0..n-1) within every list touched by a reorder request — no fractional/gap positions.
- The `board_lists` table name is used instead of `lists` to avoid the reserved-word clash some databases have with `lists`.
- `App\Http\Controllers\Controller` in this codebase does **not** include the `AuthorizesRequests` trait (confirmed by reading the file). Use `Illuminate\Support\Facades\Gate::authorize(...)` in controller methods, and `$this->user()->can(...)` inside `FormRequest::authorize()` methods. Do not add the trait back to the base controller.
- Inertia props are raw Eloquent models/collections (matches the existing `HandleInertiaRequests::share()` convention of sharing `$request->user()` directly) — do not introduce API Resource classes for these pages.
- Reorder requests from the frontend use `router.patch(url, payload, { preserveScroll: true, preserveState: true })` from `@inertiajs/vue3`, not a hand-rolled `fetch`/`axios` call — this avoids scroll/state loss without adding new HTTP-client/CSRF-handling code.
- Only one new npm dependency is introduced: `vue-draggable-plus`. No new Composer dependencies.
- After any PHP file changes in a task, run `vendor/bin/pint --dirty --format agent` before committing.
- Run `php artisan test --compact --filter=<Name>` (not the whole suite) after each backend task, per this repo's test-enforcement convention.

---

## Task 1: Database schema — boards, board_lists, cards

**Files:**
- Create: `database/migrations/2026_08_19_100001_create_boards_table.php`
- Create: `database/migrations/2026_08_19_100002_create_board_lists_table.php`
- Create: `database/migrations/2026_08_19_100003_create_cards_table.php`
- Create: `app/Models/Board.php`
- Create: `app/Models/BoardList.php`
- Create: `app/Models/Card.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/BoardFactory.php`
- Create: `database/factories/BoardListFactory.php`
- Create: `database/factories/CardFactory.php`
- Test: `tests/Feature/BoardModelTest.php`

**Interfaces:**
- Produces: `Board` (fields: `id`, `user_id`, `name`, `background_color` nullable, `archived_at` nullable, timestamps; relations `user(): BelongsTo`, `lists(): HasMany`), `BoardList` (fields: `id`, `board_id`, `name`, `position`, `archived_at` nullable, timestamps; relations `board(): BelongsTo`, `cards(): HasMany`), `Card` (fields: `id`, `board_list_id`, `name`, `description` nullable, `position`, `archived_at` nullable, timestamps; relation `boardList(): BelongsTo`). All three factories support `->archived()` state. `User::boards(): HasMany`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BoardModelTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('a board has many lists and a list has many cards', function () {
    $board = Board::factory()->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    expect($board->lists)->toHaveCount(1);
    expect($board->lists->first()->is($list))->toBeTrue();
    expect($list->cards)->toHaveCount(1);
    expect($list->cards->first()->is($card))->toBeTrue();
    expect($card->boardList->is($list))->toBeTrue();
    expect($list->board->is($board))->toBeTrue();
});

test('a user has many boards', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->count(2)->create();

    expect($user->boards)->toHaveCount(2);
});

test('the archived factory state sets archived_at', function () {
    $board = Board::factory()->archived()->create();
    $list = BoardList::factory()->archived()->create();
    $card = Card::factory()->archived()->create();

    expect($board->archived_at)->not->toBeNull();
    expect($list->archived_at)->not->toBeNull();
    expect($card->archived_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardModelTest`
Expected: FAIL — classes `App\Models\Board`, `App\Models\BoardList`, `App\Models\Card` do not exist yet.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_08_19_100001_create_boards_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('background_color')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
```

`database/migrations/2026_08_19_100002_create_board_lists_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_lists');
    }
};
```

`database/migrations/2026_08_19_100003_create_cards_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
```

- [ ] **Step 4: Create the models**

`app/Models/Board.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    /** @use HasFactory<\Database\Factories\BoardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'background_color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class);
    }
}
```

`app/Models/BoardList.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardList extends Model
{
    /** @use HasFactory<\Database\Factories\BoardListFactory> */
    use HasFactory;

    protected $table = 'board_lists';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_id',
        'name',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
```

`app/Models/Card.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    /** @use HasFactory<\Database\Factories\CardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'board_list_id',
        'name',
        'description',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function boardList(): BelongsTo
    {
        return $this->belongsTo(BoardList::class);
    }
}
```

Modify `app/Models/User.php` — add the import and relation method (insert alongside the existing class body):

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

```php
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }
```

- [ ] **Step 5: Create the factories**

`database/factories/BoardFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Board>
 */
class BoardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'background_color' => '#0079BF',
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
```

`database/factories/BoardListFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardList>
 */
class BoardListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->words(2, true),
            'position' => 0,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
```

`database/factories/CardFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\BoardList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Card>
 */
class CardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_list_id' => BoardList::factory(),
            'name' => fake()->sentence(3),
            'description' => null,
            'position' => 0,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=BoardModelTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_19_100001_create_boards_table.php database/migrations/2026_08_19_100002_create_board_lists_table.php database/migrations/2026_08_19_100003_create_cards_table.php app/Models/Board.php app/Models/BoardList.php app/Models/Card.php app/Models/User.php database/factories/BoardFactory.php database/factories/BoardListFactory.php database/factories/CardFactory.php tests/Feature/BoardModelTest.php
git commit -m "feat: add board, board_list, and card schema"
```

---

## Task 2: Board backend — policy, requests, controller, routes

**Files:**
- Create: `app/Policies/BoardPolicy.php`
- Create: `app/Http/Requests/Boards/StoreBoardRequest.php`
- Create: `app/Http/Requests/Boards/UpdateBoardRequest.php`
- Create: `app/Http/Controllers/BoardController.php`
- Create: `routes/boards.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BoardTest.php`

**Interfaces:**
- Consumes: `Board` model and `Board::factory()` from Task 1 (`app/Models/Board.php`, `database/factories/BoardFactory.php`).
- Produces: routes `boards.index` (`GET /boards`), `boards.archived` (`GET /boards/archived`), `boards.store` (`POST /boards`), `boards.show` (`GET /boards/{board}`), `boards.update` (`PATCH /boards/{board}`), `boards.archive` (`PATCH /boards/{board}/archive`), `boards.restore` (`PATCH /boards/{board}/restore`), `boards.destroy` (`DELETE /boards/{board}`). Inertia pages `boards/Index` (prop `boards: Board[]`), `boards/Archived` (prop `boards: Board[]`), `boards/Show` (prop `board: Board` with `lists` eager-loaded). `BoardPolicy` methods `view`, `update`, `delete` — reused directly by later tasks for list/card authorization via `$boardList->board` / `$card->boardList->board`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BoardTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\User;

test('index lists only the authenticated user\'s active boards', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $activeBoard = Board::factory()->for($user)->create(['name' => 'Active Board']);
    Board::factory()->for($user)->archived()->create(['name' => 'Archived Board']);
    Board::factory()->for($otherUser)->create(['name' => 'Other User Board']);

    $response = $this->actingAs($user)->get('/boards');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Index')
            ->has('boards', 1)
            ->where('boards.0.id', $activeBoard->id)
    );
});

test('archived lists only the authenticated user\'s archived boards', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->create();
    $archivedBoard = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->get('/boards/archived');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Archived')
            ->has('boards', 1)
            ->where('boards.0.id', $archivedBoard->id)
    );
});

test('a user can create a board', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/boards', [
        'name' => 'My New Board',
        'background_color' => '#0079BF',
    ]);

    $board = Board::first();

    $response->assertRedirect("/boards/{$board->id}");
    expect($board->user_id)->toBe($user->id);
    expect($board->name)->toBe('My New Board');
});

test('creating a board requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/boards', ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user can view their own board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.id', $board->id)
    );
});

test('a user cannot view another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('a user can rename their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Old name']);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}", [
        'name' => 'New name',
    ]);

    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a user cannot rename another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create(['name' => 'Old name']);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}", [
        'name' => 'New name',
    ]);

    $response->assertForbidden();
    expect($board->fresh()->name)->toBe('Old name');
});

test('a user can archive and restore their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $this->actingAs($user)->patch("/boards/{$board->id}/archive")->assertRedirect('/boards');
    expect($board->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/boards/{$board->id}/restore")->assertRedirect('/boards/archived');
    expect($board->fresh()->archived_at)->toBeNull();
});

test('a non archived board cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertStatus(422);
    expect(Board::find($board->id))->not->toBeNull();
});

test('an archived board can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertRedirect('/boards/archived');
    expect(Board::find($board->id))->toBeNull();
});

test('a user cannot delete another user\'s archived board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/boards/{$board->id}");

    $response->assertForbidden();
    expect(Board::find($board->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardTest`
Expected: FAIL — route `boards.index` / class `BoardController` do not exist (404s).

- [ ] **Step 3: Create the policy**

`app/Policies/BoardPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function update(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }
}
```

Laravel auto-discovers this policy for the `Board` model via the `App\Models\{Model}` → `App\Policies\{Model}Policy` naming convention — no manual registration needed.

- [ ] **Step 4: Create the form requests**

`app/Http/Requests/Boards/StoreBoardRequest.php`:

```php
<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'background_color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
```

`app/Http/Requests/Boards/UpdateBoardRequest.php`:

```php
<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'background_color' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/BoardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Models\Board;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $boards = $request->user()->boards()
            ->whereNull('archived_at')
            ->latest()
            ->get();

        return Inertia::render('boards/Index', [
            'boards' => $boards,
        ]);
    }

    public function archived(Request $request): Response
    {
        $boards = $request->user()->boards()
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->get();

        return Inertia::render('boards/Archived', [
            'boards' => $boards,
        ]);
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $board = $request->user()->boards()->create($request->validated());

        return to_route('boards.show', $board);
    }

    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
        ]);

        return Inertia::render('boards/Show', [
            'board' => $board,
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $board->update($request->validated());

        return back();
    }

    public function archive(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => now()]);

        return to_route('boards.index');
    }

    public function restore(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => null]);

        return to_route('boards.archived');
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        abort_if($board->archived_at === null, 422, 'Only archived boards can be permanently deleted.');

        $board->delete();

        return to_route('boards.archived');
    }
}
```

- [ ] **Step 6: Create the routes file and wire it up**

`routes/boards.php`:

```php
<?php

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
    Route::get('boards/archived', [BoardController::class, 'archived'])->name('boards.archived');
    Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::patch('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
    Route::patch('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
});
```

Modify `routes/web.php` — add after `require __DIR__.'/settings.php';`:

```php
require __DIR__.'/boards.php';
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=BoardTest`
Expected: PASS (12 tests)

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/BoardPolicy.php app/Http/Requests/Boards app/Http/Controllers/BoardController.php routes/boards.php routes/web.php tests/Feature/BoardTest.php
git commit -m "feat: add board CRUD, archive workflow, and authorization"
```

---

## Task 3: BoardList backend — requests, controller, reorder, routes

**Files:**
- Create: `app/Http/Requests/BoardLists/StoreBoardListRequest.php`
- Create: `app/Http/Requests/BoardLists/UpdateBoardListRequest.php`
- Create: `app/Http/Requests/BoardLists/ReorderBoardListsRequest.php`
- Create: `app/Http/Controllers/BoardListController.php`
- Modify: `routes/boards.php`
- Test: `tests/Feature/BoardListTest.php`

**Interfaces:**
- Consumes: `Board`, `BoardList` models and factories (Task 1); `BoardPolicy` (Task 2, reused via `$boardList->board`).
- Produces: routes `board-lists.store` (`POST /boards/{board}/lists`), `board-lists.reorder` (`PATCH /boards/{board}/lists/reorder`), `board-lists.update` (`PATCH /lists/{boardList}`), `board-lists.archive` (`PATCH /lists/{boardList}/archive`), `board-lists.restore` (`PATCH /lists/{boardList}/restore`), `board-lists.destroy` (`DELETE /lists/{boardList}`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BoardListTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;

test('a user can add a list to their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->post("/boards/{$board->id}/lists", [
        'name' => 'To Do',
    ]);

    $response->assertRedirect();
    $list = $board->lists()->first();
    expect($list->name)->toBe('To Do');
    expect($list->position)->toBe(0);
});

test('a new list is appended after existing lists', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    BoardList::factory()->for($board)->create(['position' => 0]);
    BoardList::factory()->for($board)->create(['position' => 1]);

    $this->actingAs($user)->post("/boards/{$board->id}/lists", ['name' => 'Third']);

    expect($board->lists()->where('name', 'Third')->first()->position)->toBe(2);
});

test('a user cannot add a list to another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/boards/{$board->id}/lists", ['name' => 'To Do']);

    $response->assertForbidden();
});

test('a user can rename a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['name' => 'Old']);

    $response = $this->actingAs($user)->patch("/lists/{$list->id}", ['name' => 'New']);

    $response->assertRedirect();
    expect($list->fresh()->name)->toBe('New');
});

test('a user can reorder lists on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $first = BoardList::factory()->for($board)->create(['position' => 0]);
    $second = BoardList::factory()->for($board)->create(['position' => 1]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$second->id, $first->id],
    ]);

    $response->assertRedirect();
    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

test('reorder rejects a list id that does not belong to the board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $foreignList = BoardList::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$list->id, $foreignList->id],
    ]);

    $response->assertSessionHasErrors('ordered_ids.1');
});

test('a user can archive and restore a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $this->actingAs($user)->patch("/lists/{$list->id}/archive")->assertRedirect();
    expect($list->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/lists/{$list->id}/restore")->assertRedirect();
    expect($list->fresh()->archived_at)->toBeNull();
});

test('archived lists are excluded from the board show payload', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    BoardList::factory()->for($board)->create(['name' => 'Visible']);
    BoardList::factory()->for($board)->archived()->create(['name' => 'Hidden']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('board.lists', 1)
            ->where('board.lists.0.name', 'Visible')
    );
});

test('a non archived list cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertStatus(422);
    expect(BoardList::find($list->id))->not->toBeNull();
});

test('an archived list can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->archived()->create();

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertRedirect();
    expect(BoardList::find($list->id))->toBeNull();
});

test('a user cannot modify a list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->patch("/lists/{$list->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($other)->patch("/lists/{$list->id}/archive")->assertForbidden();
    expect($list->fresh()->name)->not->toBe('Hacked');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardListTest`
Expected: FAIL — route `board-lists.store` / class `BoardListController` do not exist.

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/BoardLists/StoreBoardListRequest.php`:

```php
<?php

namespace App\Http\Requests\BoardLists;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/BoardLists/UpdateBoardListRequest.php`:

```php
<?php

namespace App\Http\Requests\BoardLists;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boardList')->board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/BoardLists/ReorderBoardListsRequest.php`:

```php
<?php

namespace App\Http\Requests\BoardLists;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderBoardListsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Board $board */
        $board = $this->route('board');

        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', Rule::exists('board_lists', 'id')->where('board_id', $board->id)],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/BoardListController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardLists\ReorderBoardListsRequest;
use App\Http\Requests\BoardLists\StoreBoardListRequest;
use App\Http\Requests\BoardLists\UpdateBoardListRequest;
use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoardListController extends Controller
{
    public function store(StoreBoardListRequest $request, Board $board): RedirectResponse
    {
        $position = ($board->lists()->max('position') ?? -1) + 1;

        $board->lists()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateBoardListRequest $request, BoardList $boardList): RedirectResponse
    {
        $boardList->update($request->validated());

        return back();
    }

    public function reorder(ReorderBoardListsRequest $request, Board $board): RedirectResponse
    {
        DB::transaction(function () use ($request, $board) {
            foreach ($request->validated('ordered_ids') as $position => $id) {
                BoardList::where('id', $id)->where('board_id', $board->id)->update(['position' => $position]);
            }
        });

        return back();
    }

    public function archive(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('update', $boardList->board);

        $boardList->update(['archived_at' => now()]);

        return back();
    }

    public function restore(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('update', $boardList->board);

        $boardList->update(['archived_at' => null]);

        return back();
    }

    public function destroy(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('delete', $boardList->board);

        abort_if($boardList->archived_at === null, 422, 'Only archived lists can be permanently deleted.');

        $boardList->delete();

        return back();
    }
}
```

- [ ] **Step 5: Add the routes**

Modify `routes/boards.php` — add the import and route group:

```php
use App\Http\Controllers\BoardListController;
```

```php
    Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('board-lists.store');
    Route::patch('boards/{board}/lists/reorder', [BoardListController::class, 'reorder'])->name('board-lists.reorder');
    Route::patch('lists/{boardList}', [BoardListController::class, 'update'])->name('board-lists.update');
    Route::patch('lists/{boardList}/archive', [BoardListController::class, 'archive'])->name('board-lists.archive');
    Route::patch('lists/{boardList}/restore', [BoardListController::class, 'restore'])->name('board-lists.restore');
    Route::delete('lists/{boardList}', [BoardListController::class, 'destroy'])->name('board-lists.destroy');
```

(Insert this group inside the existing `Route::middleware(['auth', 'verified'])->group(...)` closure, after the board routes.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=BoardListTest`
Expected: PASS (11 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/BoardLists app/Http/Controllers/BoardListController.php routes/boards.php tests/Feature/BoardListTest.php
git commit -m "feat: add board list CRUD, reorder, and archive workflow"
```

---

## Task 4: Card backend — requests, controller, reorder/move, routes

**Files:**
- Create: `app/Http/Requests/Cards/StoreCardRequest.php`
- Create: `app/Http/Requests/Cards/UpdateCardRequest.php`
- Create: `app/Http/Requests/Cards/ReorderCardsRequest.php`
- Create: `app/Http/Controllers/CardController.php`
- Modify: `routes/boards.php`
- Test: `tests/Feature/CardTest.php`

**Interfaces:**
- Consumes: `Board`, `BoardList`, `Card` models and factories (Task 1); `BoardPolicy` (Task 2, reused via `$card->boardList->board`).
- Produces: routes `cards.store` (`POST /lists/{boardList}/cards`), `cards.reorder` (`PATCH /boards/{board}/cards/reorder`), `cards.update` (`PATCH /cards/{card}`), `cards.archive` (`PATCH /cards/{card}/archive`), `cards.restore` (`PATCH /cards/{card}/restore`), `cards.destroy` (`DELETE /cards/{card}`). The `cards.reorder` payload shape (consumed by the frontend in Task 8): `{ target_list_id: number, target_ordered_ids: number[], source_list_id?: number, source_ordered_ids?: number[] }`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CardTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('a user can add a card to a list on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $response = $this->actingAs($user)->post("/lists/{$list->id}/cards", ['name' => 'Write tests']);

    $response->assertRedirect();
    $card = $list->cards()->first();
    expect($card->name)->toBe('Write tests');
    expect($card->position)->toBe(0);
});

test('a user cannot add a card to a list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/lists/{$list->id}/cards", ['name' => 'Write tests']);

    $response->assertForbidden();
});

test('a user can update a card\'s name and description', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/cards/{$card->id}", [
        'name' => 'Updated name',
        'description' => 'Updated description',
    ]);

    $response->assertRedirect();
    expect($card->fresh()->name)->toBe('Updated name');
    expect($card->fresh()->description)->toBe('Updated description');
});

test('a user can reorder cards within a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create(['position' => 0]);
    $second = Card::factory()->for($list)->create(['position' => 1]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$second->id, $first->id],
    ]);

    $response->assertRedirect();
    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

test('a user can move a card to a different list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $sourceList = BoardList::factory()->for($board)->create();
    $targetList = BoardList::factory()->for($board)->create();
    $movedCard = Card::factory()->for($sourceList)->create(['position' => 0]);
    $remainingCard = Card::factory()->for($sourceList)->create(['position' => 1]);
    $existingTargetCard = Card::factory()->for($targetList)->create(['position' => 0]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'source_list_id' => $sourceList->id,
        'target_list_id' => $targetList->id,
        'target_ordered_ids' => [$movedCard->id, $existingTargetCard->id],
        'source_ordered_ids' => [$remainingCard->id],
    ]);

    $response->assertRedirect();
    expect($movedCard->fresh()->board_list_id)->toBe($targetList->id);
    expect($movedCard->fresh()->position)->toBe(0);
    expect($existingTargetCard->fresh()->position)->toBe(1);
    expect($remainingCard->fresh()->position)->toBe(0);
});

test('reorder rejects a target list id that does not belong to the board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $foreignList = BoardList::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $foreignList->id,
        'target_ordered_ids' => [$card->id],
    ]);

    $response->assertSessionHasErrors('target_list_id');
});

test('reorder rejects a card id that does not belong to the source or target list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $ownCard = Card::factory()->for($list)->create();
    $foreignCard = Card::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$ownCard->id, $foreignCard->id],
    ]);

    $response->assertSessionHasErrors('target_ordered_ids.1');
    expect($foreignCard->fresh()->board_list_id)->not->toBe($list->id);
});

test('a user can archive and restore a card', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($user)->patch("/cards/{$card->id}/archive")->assertRedirect();
    expect($card->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/cards/{$card->id}/restore")->assertRedirect();
    expect($card->fresh()->archived_at)->toBeNull();
});

test('archived cards are excluded from the board show payload', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['name' => 'Visible']);
    Card::factory()->for($list)->archived()->create(['name' => 'Hidden']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('board.lists.0.cards', 1)
            ->where('board.lists.0.cards.0.name', 'Visible')
    );
});

test('a non archived card cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->delete("/cards/{$card->id}");

    $response->assertStatus(422);
    expect(Card::find($card->id))->not->toBeNull();
});

test('an archived card can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->archived()->create();

    $response = $this->actingAs($user)->delete("/cards/{$card->id}");

    $response->assertRedirect();
    expect(Card::find($card->id))->toBeNull();
});

test('a user cannot modify a card on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->patch("/cards/{$card->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($other)->patch("/cards/{$card->id}/archive")->assertForbidden();
    expect($card->fresh()->name)->not->toBe('Hacked');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CardTest`
Expected: FAIL — route `cards.store` / class `CardController` do not exist.

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/Cards/StoreCardRequest.php`:

```php
<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boardList')->board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/Cards/UpdateCardRequest.php`:

```php
<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('card')->boardList->board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

`app/Http/Requests/Cards/ReorderCardsRequest.php`:

```php
<?php

namespace App\Http\Requests\Cards;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Board $board */
        $board = $this->route('board');

        // Every referenced card must currently belong to the source or target list —
        // both of which are already confirmed to belong to this board above. Without
        // this, a caller could smuggle a card id belonging to a different user's board
        // into the payload and have it silently reassigned into this board's list.
        $listIds = array_filter([$this->input('target_list_id'), $this->input('source_list_id')]);

        return [
            'source_list_id' => ['nullable', 'integer', Rule::exists('board_lists', 'id')->where('board_id', $board->id)],
            'target_list_id' => ['required', 'integer', Rule::exists('board_lists', 'id')->where('board_id', $board->id)],
            'target_ordered_ids' => ['required', 'array'],
            'target_ordered_ids.*' => ['integer', Rule::exists('cards', 'id')->whereIn('board_list_id', $listIds)],
            'source_ordered_ids' => ['array'],
            'source_ordered_ids.*' => ['integer', Rule::exists('cards', 'id')->whereIn('board_list_id', $listIds)],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/CardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\ReorderCardsRequest;
use App\Http\Requests\Cards\StoreCardRequest;
use App\Http\Requests\Cards\UpdateCardRequest;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CardController extends Controller
{
    public function store(StoreCardRequest $request, BoardList $boardList): RedirectResponse
    {
        $position = ($boardList->cards()->max('position') ?? -1) + 1;

        $boardList->cards()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateCardRequest $request, Card $card): RedirectResponse
    {
        $card->update($request->validated());

        return back();
    }

    public function reorder(ReorderCardsRequest $request, Board $board): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach ($data['target_ordered_ids'] as $position => $id) {
                Card::where('id', $id)->update([
                    'board_list_id' => $data['target_list_id'],
                    'position' => $position,
                ]);
            }

            foreach ($data['source_ordered_ids'] ?? [] as $position => $id) {
                Card::where('id', $id)->update([
                    'board_list_id' => $data['source_list_id'],
                    'position' => $position,
                ]);
            }
        });

        return back();
    }

    public function archive(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => now()]);

        return back();
    }

    public function restore(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => null]);

        return back();
    }

    public function destroy(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card->boardList->board);

        abort_if($card->archived_at === null, 422, 'Only archived cards can be permanently deleted.');

        $card->delete();

        return back();
    }
}
```

- [ ] **Step 5: Add the routes**

Modify `routes/boards.php` — add the import and route group:

```php
use App\Http\Controllers\CardController;
```

```php
    Route::post('lists/{boardList}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::patch('boards/{board}/cards/reorder', [CardController::class, 'reorder'])->name('cards.reorder');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::patch('cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive');
    Route::patch('cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=CardTest`
Expected: PASS (11 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Cards app/Http/Controllers/CardController.php routes/boards.php tests/Feature/CardTest.php
git commit -m "feat: add card CRUD, reorder/move, and archive workflow"
```

---

## Task 5: Frontend — TypeScript types, Boards Index/Archived pages, sidebar nav

**Files:**
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/pages/boards/Index.vue`
- Create: `resources/js/pages/boards/Archived.vue`
- Modify: `resources/js/components/AppSidebar.vue`

**Interfaces:**
- Consumes: props from `boards.index` / `boards.archived` (Task 2) — `boards: Board[]`. Routes `boards.index`, `boards.archived`, `boards.store`, `boards.restore`, `boards.destroy` (Task 2).
- Produces: TypeScript types `Card`, `BoardList`, `Board` in `resources/js/types/index.ts`, consumed by every later frontend task.

- [ ] **Step 1: Add the TypeScript types**

Modify `resources/js/types/index.ts` — append at the end of the file:

```ts
export interface Card {
    id: number;
    board_list_id: number;
    name: string;
    description: string | null;
    position: number;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface BoardList {
    id: number;
    board_id: number;
    name: string;
    position: number;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    cards: Card[];
}

export interface Board {
    id: number;
    user_id: number;
    name: string;
    background_color: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    lists?: BoardList[];
}
```

- [ ] **Step 2: Create the Boards Index page**

Create `resources/js/pages/boards/Index.vue`:

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Board, type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    boards: Board[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boards', href: '/boards' }];

const showCreate = ref(false);

const form = useForm({
    name: '',
    background_color: '#0079BF',
});

function submit() {
    form.post(route('boards.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Boards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Your boards</h1>
                <div class="flex items-center gap-4">
                    <Link :href="route('boards.archived')" class="text-sm text-muted-foreground underline">Archived boards</Link>
                    <Dialog v-model:open="showCreate">
                        <DialogTrigger as-child>
                            <Button size="sm">New board</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New board</DialogTitle>
                            </DialogHeader>
                            <form class="space-y-4" @submit.prevent="submit">
                                <div class="grid gap-2">
                                    <Label for="board-name">Name</Label>
                                    <Input id="board-name" v-model="form.name" required autofocus />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" :disabled="form.processing">Create</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)">
                    <Card
                        class="border-t-4 transition hover:border-sidebar-border"
                        :style="{ borderTopColor: board.background_color ?? undefined }"
                    >
                        <CardHeader>
                            <CardTitle>{{ board.name }}</CardTitle>
                        </CardHeader>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 3: Create the Boards Archived page**

Create `resources/js/pages/boards/Archived.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Board, type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    boards: Board[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Boards', href: '/boards' },
    { title: 'Archived', href: '/boards/archived' },
];

function restore(board: Board) {
    router.patch(route('boards.restore', board.id));
}

function destroy(board: Board) {
    if (!confirm(`Permanently delete the board "${board.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('boards.destroy', board.id));
}
</script>

<template>
    <Head title="Archived boards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Archived boards</h1>
                <Link :href="route('boards.index')" class="text-sm text-muted-foreground underline">Back to boards</Link>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No archived boards.</p>

            <ul class="space-y-2">
                <li v-for="board in boards" :key="board.id" class="flex items-center justify-between gap-2 rounded-md border p-3 text-sm">
                    <span>{{ board.name }}</span>
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" @click="restore(board)">Restore</Button>
                        <Button variant="ghost" size="sm" @click="destroy(board)">Delete permanently</Button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 4: Add the sidebar nav link**

Modify `resources/js/components/AppSidebar.vue`:

```ts
import { BookOpen, Folder, Kanban, LayoutGrid } from 'lucide-vue-next';
```

```ts
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Boards',
        href: '/boards',
        icon: Kanban,
    },
];
```

- [ ] **Step 5: Install dependencies and verify in the browser**

Run: `composer run dev` (starts the PHP server, queue listener, and Vite dev server together)

In the browser:
1. Log in, click "Boards" in the sidebar → lands on `/boards` showing "No boards yet".
2. Click "New board", type a name, submit → dialog closes, redirected to the new board's (still-blank) show page.
3. Go back to `/boards` → the new board appears in the grid.
4. Visit `/boards/archived` → shows "No archived boards" (the board isn't archived yet, this is just confirming the page renders).

- [ ] **Step 6: Lint and commit**

```bash
npm run lint
git add resources/js/types/index.ts resources/js/pages/boards/Index.vue resources/js/pages/boards/Archived.vue resources/js/components/AppSidebar.vue
git commit -m "feat: add boards index/archived pages and sidebar nav"
```

---

## Task 6: Frontend — Board Show page, list/card columns, add-list/add-card

**Files:**
- Create: `resources/js/pages/boards/Show.vue`
- Create: `resources/js/components/boards/BoardListColumn.vue`
- Create: `resources/js/components/boards/BoardCard.vue`

**Interfaces:**
- Consumes: `Board`, `BoardList`, `Card` types (Task 5); prop `board: Board` from `boards.show` (Task 2); routes `board-lists.store`, `cards.store` (Tasks 3–4).
- Produces: `BoardListColumn.vue` props `{ list: BoardList }`, emits `open-card: [card: Card]`. `BoardCard.vue` props `{ card: Card }`, emits `open: [card: Card]`. Both consumed directly by Task 7 (archive/delete menus) and Task 8 (drag-and-drop), which will extend these same files.

- [ ] **Step 1: Create the BoardCard component**

Create `resources/js/components/boards/BoardCard.vue`:

```vue
<script setup lang="ts">
import type { Card } from '@/types';

defineProps<{
    card: Card;
}>();

const emit = defineEmits<{
    open: [card: Card];
}>();
</script>

<template>
    <button
        type="button"
        class="w-full rounded-md border border-sidebar-border/70 bg-background p-3 text-left text-sm shadow-sm hover:border-sidebar-border dark:border-sidebar-border"
        @click="emit('open', card)"
    >
        <p>{{ card.name }}</p>
        <p v-if="card.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ card.description }}</p>
    </button>
</template>
```

- [ ] **Step 2: Create the BoardListColumn component**

Create `resources/js/components/boards/BoardListColumn.vue`:

```vue
<script setup lang="ts">
import BoardCard from '@/components/boards/BoardCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BoardList, Card } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    list: BoardList;
}>();

const emit = defineEmits<{
    'open-card': [card: Card];
}>();

const showAddCard = ref(false);

const addCardForm = useForm({
    name: '',
});

function submitAddCard() {
    addCardForm.post(route('cards.store', props.list.id), {
        preserveScroll: true,
        onSuccess: () => {
            addCardForm.reset();
            showAddCard.value = false;
        },
    });
}
</script>

<template>
    <div class="flex w-72 shrink-0 flex-col rounded-xl bg-muted/50 p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="truncate px-1 text-sm font-medium">{{ list.name }}</p>
        </div>

        <div class="flex flex-col gap-2">
            <BoardCard v-for="card in list.cards" :key="card.id" :card="card" @open="emit('open-card', $event)" />
        </div>

        <Button v-if="!showAddCard" variant="ghost" size="sm" class="mt-2 justify-start" @click="showAddCard = true">
            <Plus class="size-4" /> Add card
        </Button>

        <form v-else class="mt-2 space-y-2" @submit.prevent="submitAddCard">
            <Input v-model="addCardForm.name" placeholder="Card name" autofocus />
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="addCardForm.processing">Add</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAddCard = false">Cancel</Button>
            </div>
        </form>
    </div>
</template>
```

- [ ] **Step 3: Create the Board Show page**

Create `resources/js/pages/boards/Show.vue`:

```vue
<script setup lang="ts">
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, Card } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    board: Board;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Boards', href: route('boards.index') },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

const showAddList = ref(false);

const addListForm = useForm({
    name: '',
});

function submitAddList() {
    addListForm.post(route('board-lists.store', props.board.id), {
        preserveScroll: true,
        onSuccess: () => {
            addListForm.reset();
            showAddList.value = false;
        },
    });
}

function openCard(card: Card) {
    // Wired up to CardDetailModal in the next task.
    console.log('open card', card);
}
</script>

<template>
    <Head :title="board.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between p-4">
            <h1 class="text-lg font-semibold">{{ board.name }}</h1>
        </div>

        <div class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0">
            <BoardListColumn v-for="list in board.lists" :key="list.id" :list="list" @open-card="openCard" />
        </div>

        <div class="p-4 pt-0">
            <Button v-if="!showAddList" variant="secondary" size="sm" @click="showAddList = true">Add list</Button>
            <form v-else class="flex max-w-xs gap-2" @submit.prevent="submitAddList">
                <Input v-model="addListForm.name" placeholder="List name" autofocus />
                <Button type="submit" size="sm" :disabled="addListForm.processing">Add</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAddList = false">Cancel</Button>
            </form>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 4: Verify in the browser**

Run: `composer run dev` (if not already running)

In the browser:
1. Open a board from `/boards`.
2. Click "Add list", type a name, submit → the list column appears without a full page flash.
3. In the new list, click "Add card", type a name, submit → the card appears in the column.
4. Add a second list and a couple more cards; refresh the page → everything persists in the same order.

- [ ] **Step 5: Lint and commit**

```bash
npm run lint
git add resources/js/pages/boards/Show.vue resources/js/components/boards/BoardListColumn.vue resources/js/components/boards/BoardCard.vue
git commit -m "feat: add board show page with lists and cards"
```

---

## Task 7: Frontend — card detail modal, archive/delete actions, board-level archive

**Files:**
- Create: `resources/js/components/boards/CardDetailModal.vue`
- Modify: `resources/js/components/boards/BoardCard.vue`
- Modify: `resources/js/components/boards/BoardListColumn.vue`
- Modify: `resources/js/pages/boards/Show.vue`

**Interfaces:**
- Consumes: routes `cards.update`, `cards.archive`, `board-lists.archive`, `boards.archive` (Tasks 2–4); `BoardCard`/`BoardListColumn`/`Show.vue` from Task 6.
- Produces: `CardDetailModal.vue` — props `{ card: Card | null }`, `v-model:open` (boolean). Consumed as-is by Task 9 (no further changes needed there).

- [ ] **Step 1: Create the CardDetailModal component**

Create `resources/js/components/boards/CardDetailModal.vue`:

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Card } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    card: Card | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    description: '' as string | null,
});

watch(
    () => props.card,
    (card) => {
        if (card) {
            form.name = card.name;
            form.description = card.description;
        }
    },
    { immediate: true },
);

function submit() {
    if (!props.card) {
        return;
    }

    form.patch(route('cards.update', props.card.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="card">
            <DialogHeader>
                <DialogTitle>Edit card</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="card-name">Name</Label>
                    <Input id="card-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="card-description">Description</Label>
                    <textarea
                        id="card-description"
                        v-model="form.description"
                        rows="4"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Add an archive action to BoardCard**

Modify `resources/js/components/boards/BoardCard.vue` — replace its whole contents with:

```vue
<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Card } from '@/types';
import { router } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';

const props = defineProps<{
    card: Card;
}>();

const emit = defineEmits<{
    open: [card: Card];
}>();

function archive() {
    router.patch(route('cards.archive', props.card.id), {}, { preserveScroll: true });
}
</script>

<template>
    <div
        class="group flex items-start justify-between gap-2 rounded-md border border-sidebar-border/70 bg-background p-3 text-sm shadow-sm hover:border-sidebar-border dark:border-sidebar-border"
    >
        <button type="button" class="flex-1 text-left" @click="emit('open', card)">
            <p>{{ card.name }}</p>
            <p v-if="card.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ card.description }}</p>
        </button>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button type="button" class="opacity-0 group-hover:opacity-100" aria-label="Card actions">
                    <MoreHorizontal class="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem @click="archive">Archive</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
```

- [ ] **Step 3: Add an archive action to BoardListColumn**

Modify `resources/js/components/boards/BoardListColumn.vue` — add the dropdown menu import and archive handler, and update the header markup:

```ts
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from 'lucide-vue-next';
```

```ts
function archiveList() {
    router.patch(route('board-lists.archive', props.list.id), {}, { preserveScroll: true });
}
```

Replace the header `<div>` with:

```html
<div class="mb-2 flex items-center justify-between gap-2">
    <p class="truncate px-1 text-sm font-medium">{{ list.name }}</p>

    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button type="button" aria-label="List actions">
                <MoreHorizontal class="size-4" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="archiveList">Archive list</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</div>
```

- [ ] **Step 4: Wire the modal and board-level archive into Show.vue**

Modify `resources/js/pages/boards/Show.vue`:

```ts
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
```

Replace the `openCard` placeholder and add card-modal/archive state:

```ts
const activeCard = ref<Card | null>(null);
const showCardModal = ref(false);

function openCard(card: Card) {
    activeCard.value = card;
    showCardModal.value = true;
}

function archiveBoard() {
    if (!confirm(`Archive the board "${props.board.name}"?`)) {
        return;
    }

    router.patch(route('boards.archive', props.board.id));
}
```

Update the header to include the actions menu, and add the modal at the bottom of the template:

```html
<div class="flex items-center justify-between p-4">
    <h1 class="text-lg font-semibold">{{ board.name }}</h1>

    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button type="button" aria-label="Board actions">
                <MoreHorizontal class="size-4" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="archiveBoard">Archive board</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</div>
```

```html
<CardDetailModal v-model:open="showCardModal" :card="activeCard" />
```

- [ ] **Step 5: Verify in the browser**

In the browser:
1. Open a board, click a card → the edit dialog opens with its current name/description.
2. Change the name and description, save → dialog closes, the card reflects the new name/description without a full page reload.
3. Hover a card, click the "…" menu, choose Archive → the card disappears from the list.
4. Click a list's "…" menu, choose "Archive list" → the whole list disappears from the board.
5. Click the board's "…" menu, choose "Archive board" → redirected to `/boards` and the board is gone from the active list (confirm it now appears at `/boards/archived`).

- [ ] **Step 6: Lint and commit**

```bash
npm run lint
git add resources/js/components/boards/CardDetailModal.vue resources/js/components/boards/BoardCard.vue resources/js/components/boards/BoardListColumn.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add card detail modal and archive actions for cards, lists, and boards"
```

---

## Task 8: Frontend — drag-and-drop reordering

**Files:**
- Modify: `package.json` (new dependency)
- Modify: `resources/js/components/boards/BoardListColumn.vue`
- Modify: `resources/js/pages/boards/Show.vue`

**Interfaces:**
- Consumes: routes `board-lists.reorder`, `cards.reorder` (Tasks 3–4, payload shapes documented there); `BoardListColumn`/`Show.vue` from Tasks 6–7.
- Produces: `BoardListColumn.vue` new props `{ group: string }` and new emit `card-drag-end: [event: { from: HTMLElement; to: HTMLElement }]`.

- [ ] **Step 1: Install the drag-and-drop library**

Run: `npm install vue-draggable-plus`

- [ ] **Step 2: Wire card dragging into BoardListColumn**

Modify `resources/js/components/boards/BoardListColumn.vue`:

```ts
import { VueDraggable } from 'vue-draggable-plus';
```

```ts
const props = defineProps<{
    list: BoardList;
    group: string;
}>();

const emit = defineEmits<{
    'open-card': [card: Card];
    'card-drag-end': [event: { from: HTMLElement; to: HTMLElement }];
}>();
```

Replace the cards container:

```html
<!-- eslint-disable-next-line vue/no-mutating-props -->
<VueDraggable
    v-model="list.cards"
    :group="group"
    item-key="id"
    :animation="150"
    :data-list-id="list.id"
    class="flex flex-col gap-2"
    @end="(event: { from: HTMLElement; to: HTMLElement }) => emit('card-drag-end', event)"
>
    <BoardCard v-for="card in list.cards" :key="card.id" :card="card" @open="emit('open-card', $event)" />
</VueDraggable>
```

(`list` is a shared reactive object reference passed down from `Boards/Show.vue`'s `lists` ref, not a copied value — mutating `list.cards` here is intentional and is how the drag library keeps both the DOM and the parent's source of truth in sync. The inline eslint-disable is a standard, narrowly-scoped exception for this exact drag-and-drop pattern.)

- [ ] **Step 3: Wire list dragging and both reorder handlers into Show.vue**

Modify `resources/js/pages/boards/Show.vue`:

```ts
import { VueDraggable } from 'vue-draggable-plus';
```

Replace `const props = defineProps<{ board: Board }>();`'s usage of `board.lists` with a local reactive copy, and add the drag group and handlers:

```ts
const lists = ref<BoardList[]>(props.board.lists ?? []);
const cardGroup = `cards-board-${props.board.id}`;

function onListDragEnd() {
    router.patch(
        route('board-lists.reorder', props.board.id),
        { ordered_ids: lists.value.map((list) => list.id) },
        { preserveScroll: true, preserveState: true },
    );
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    const fromListId = Number(event.from.dataset.listId);
    const toListId = Number(event.to.dataset.listId);
    const targetList = lists.value.find((list) => list.id === toListId);

    if (!targetList) {
        return;
    }

    const payload: Record<string, unknown> = {
        target_list_id: toListId,
        target_ordered_ids: targetList.cards.map((card) => card.id),
    };

    if (fromListId !== toListId) {
        const sourceList = lists.value.find((list) => list.id === fromListId);

        if (sourceList) {
            payload.source_list_id = fromListId;
            payload.source_ordered_ids = sourceList.cards.map((card) => card.id);
        }
    }

    router.patch(route('cards.reorder', props.board.id), payload, {
        preserveScroll: true,
        preserveState: true,
    });
}
```

Add the `BoardList` type import: `import type { Board, BoardList, BreadcrumbItem, Card } from '@/types';`

Replace the lists container:

```html
<VueDraggable
    v-model="lists"
    item-key="id"
    :animation="150"
    handle=".list-drag-handle"
    class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0"
    @end="onListDragEnd"
>
    <BoardListColumn
        v-for="list in lists"
        :key="list.id"
        :list="list"
        :group="cardGroup"
        @open-card="openCard"
        @card-drag-end="onCardDragEnd"
    />
</VueDraggable>
```

Add a `list-drag-handle` class to BoardListColumn's header so the whole column isn't a drag surface — modify `resources/js/components/boards/BoardListColumn.vue`'s header `<p>`:

```html
<p class="list-drag-handle truncate px-1 text-sm font-medium">{{ list.name }}</p>
```

- [ ] **Step 4: Verify in the browser**

In the browser:
1. Open a board with 2+ lists, each with 2+ cards.
2. Drag a card up/down within the same list, release → order updates immediately; refresh the page → order persisted.
3. Drag a card from one list into another list → the card moves visually; refresh the page → the card is now under the target list, in the dropped position, and the source list's remaining cards kept their relative order.
4. Drag a list (via its name/handle) to reorder the columns → refresh the page → column order persisted.
5. Open the browser console throughout — no errors during any drag operation.

If cross-list drags don't persist correctly, check that `event.from`/`event.to` carry the `data-list-id` attribute set on the `VueDraggable` root element (inspect the rendered DOM) — `vue-draggable-plus` forwards unrecognized attributes to its root element, but confirm this against the installed version.

- [ ] **Step 5: Lint and commit**

```bash
npm run lint
git add package.json package-lock.json resources/js/components/boards/BoardListColumn.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add drag-and-drop reordering for lists and cards"
```

---

## Task 9: Backend + frontend — board archive panel (archived lists/cards)

**Files:**
- Modify: `app/Http/Controllers/BoardController.php`
- Create: `resources/js/components/boards/ArchivePanel.vue`
- Modify: `resources/js/pages/boards/Show.vue`
- Test: `tests/Feature/BoardArchivePanelTest.php`

**Interfaces:**
- Consumes: routes `board-lists.restore`, `board-lists.destroy`, `cards.restore`, `cards.destroy` (Tasks 3–4); `Card`/`BoardList` types (Task 5).
- Produces: `boards.show` gains two new props, `archivedLists: BoardList[]` and `archivedCards: Card[]`. `ArchivePanel.vue` — props `{ lists: BoardList[], cards: Card[] }`, `v-model:open` (boolean).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BoardArchivePanelTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('the board show page includes the board\'s archived lists and cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $activeList = BoardList::factory()->for($board)->create();
    $archivedList = BoardList::factory()->for($board)->archived()->create(['name' => 'Archived List']);
    $archivedCard = Card::factory()->for($activeList)->archived()->create(['name' => 'Archived Card']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('archivedLists', 1)
            ->where('archivedLists.0.id', $archivedList->id)
            ->has('archivedCards', 1)
            ->where('archivedCards.0.id', $archivedCard->id)
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BoardArchivePanelTest`
Expected: FAIL — `archivedLists`/`archivedCards` props are missing from the response.

- [ ] **Step 3: Add the archived props to BoardController::show**

Modify `app/Http/Controllers/BoardController.php`:

```php
use App\Models\Card;
```

Replace the `show` method body with:

```php
    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
        ]);

        $archivedLists = $board->lists()->whereNotNull('archived_at')->orderByDesc('archived_at')->get();
        $archivedCards = Card::whereIn('board_list_id', $board->lists()->pluck('id'))
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get();

        return Inertia::render('boards/Show', [
            'board' => $board,
            'archivedLists' => $archivedLists,
            'archivedCards' => $archivedCards,
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BoardArchivePanelTest`
Expected: PASS (1 test)

- [ ] **Step 5: Format and commit the backend change**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BoardController.php tests/Feature/BoardArchivePanelTest.php
git commit -m "feat: include archived lists and cards on the board show page"
```

- [ ] **Step 6: Create the ArchivePanel component**

Create `resources/js/components/boards/ArchivePanel.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { BoardList, Card } from '@/types';
import { router } from '@inertiajs/vue3';

defineProps<{
    lists: BoardList[];
    cards: Card[];
}>();

const open = defineModel<boolean>('open', { default: false });

function restoreList(list: BoardList) {
    router.patch(route('board-lists.restore', list.id), {}, { preserveScroll: true });
}

function deleteList(list: BoardList) {
    if (!confirm(`Permanently delete the list "${list.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('board-lists.destroy', list.id), { preserveScroll: true });
}

function restoreCard(card: Card) {
    router.patch(route('cards.restore', card.id), {}, { preserveScroll: true });
}

function deleteCard(card: Card) {
    if (!confirm(`Permanently delete the card "${card.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('cards.destroy', card.id), { preserveScroll: true });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Archive</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div>
                    <h3 class="mb-2 text-sm font-medium text-muted-foreground">Lists</h3>
                    <p v-if="lists.length === 0" class="text-sm text-muted-foreground">No archived lists.</p>
                    <ul class="space-y-2">
                        <li v-for="list in lists" :key="list.id" class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                            <span class="truncate">{{ list.name }}</span>
                            <div class="flex shrink-0 gap-1">
                                <Button variant="ghost" size="sm" @click="restoreList(list)">Restore</Button>
                                <Button variant="ghost" size="sm" @click="deleteList(list)">Delete</Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-muted-foreground">Cards</h3>
                    <p v-if="cards.length === 0" class="text-sm text-muted-foreground">No archived cards.</p>
                    <ul class="space-y-2">
                        <li v-for="card in cards" :key="card.id" class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                            <span class="truncate">{{ card.name }}</span>
                            <div class="flex shrink-0 gap-1">
                                <Button variant="ghost" size="sm" @click="restoreCard(card)">Restore</Button>
                                <Button variant="ghost" size="sm" @click="deleteCard(card)">Delete</Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

- [ ] **Step 7: Wire the panel into Show.vue**

Modify `resources/js/pages/boards/Show.vue`:

```ts
import ArchivePanel from '@/components/boards/ArchivePanel.vue';
```

```ts
const props = defineProps<{
    board: Board;
    archivedLists: BoardList[];
    archivedCards: Card[];
}>();
```

```ts
const showArchive = ref(false);
```

Add a "View archive" button next to the board actions menu in the template, and the panel at the bottom:

```html
<Button variant="outline" size="sm" @click="showArchive = true">View archive</Button>
```

```html
<ArchivePanel v-model:open="showArchive" :lists="archivedLists" :cards="archivedCards" />
```

- [ ] **Step 8: Verify in the browser**

In the browser:
1. On a board, archive a list and a card (from Task 7's menus).
2. Click "View archive" → the slide-over shows the archived list and card.
3. Click "Restore" on the card → it disappears from the archive panel and reappears on the board.
4. Click "Restore" on the list → it disappears from the archive panel and reappears on the board with its cards.
5. Archive the same list again, open the archive panel, click "Delete" → confirm dialog appears; confirm it → the list disappears from the archive panel permanently (refresh the page and check it's gone for good).

- [ ] **Step 9: Lint and commit**

```bash
npm run lint
git add resources/js/components/boards/ArchivePanel.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add archive panel for board lists and cards"
```
