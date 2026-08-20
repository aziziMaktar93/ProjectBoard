# Workspaces, Board Members & Card Members Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add real multi-user collaboration to Trellow: Workspaces (with members), Board membership (a subset of workspace membership), and Card assignees (a subset of board membership) — matching the three-tier model in the reference spec.

**Architecture:** Three new pivot-backed relationships (`workspace_user`, `board_user`, `card_user`) plus a new `Workspace` model that every `Board` now belongs to. Board access, currently gated by `boards.user_id === auth id`, becomes gated by board membership (`board_user`). A data migration backfills a personal workspace for every pre-existing user/board so nothing breaks.

**Tech Stack:** Laravel 12 (PHP 8.2), Pest 3, Inertia v2, Vue 3.5, TypeScript, Tailwind CSS 3, shadcn-vue.

**Reference:** `docs/superpowers/specs/2026-08-20-workspace-members-design.md`

## Global Constraints

- No email/invite-link infrastructure exists. Members are added by searching **existing registered users** and adding them immediately — no invite/accept flow.
- A user can belong to/create multiple workspaces.
- Every board belongs to exactly one workspace (`boards.workspace_id`). Board access = board membership (`board_user`), not the old `boards.user_id` ownership check. `boards.user_id` remains as "creator", used only to gate permanent delete and to protect the creator from being removed as a member.
- Card assignment candidates must already be board members; board-membership candidates must already be workspace members — both enforced server-side via `Rule::exists()->where()`, the same pattern already used in `ReorderCardsRequest`.
- Workspace member removal: the owner can remove anyone but themselves; a non-owner member can remove only themselves (leave). Board member removal: any board member can remove any other member including themselves, except the board's creator can never be removed. Card member assignment: any board member can assign/unassign any board member, no extra restriction.
- `App\Http\Controllers\Controller` has no `AuthorizesRequests` trait — use `Illuminate\Support\Facades\Gate::authorize(...)` in controllers, `$this->user()->can(...)` inside `FormRequest::authorize()`.
- Inertia props are raw Eloquent models/collections — no API Resource classes.
- After any PHP file changes, run `vendor/bin/pint --dirty --format agent` before committing.
- Run `php artisan test --compact --filter=<Name>` after each backend task, and the **full** suite (`php artisan test --compact`) before each task's final commit — this plan restructures shared authorization, so full-suite regressions matter more than usual.
- For frontend tasks: verify in a real browser (per this repo's UI-testing convention) — this plan touches navigation structure, not just isolated components.

---

## Task 1: Workspace/board/card membership schema, models, factory, registration hook

**Files:**
- Create: `database/migrations/2026_08_20_090001_create_workspaces_table.php`
- Create: `database/migrations/2026_08_20_090002_create_workspace_user_table.php`
- Create: `database/migrations/2026_08_20_090003_create_board_user_table.php`
- Create: `database/migrations/2026_08_20_090004_create_card_user_table.php`
- Create: `database/migrations/2026_08_20_090005_add_workspace_id_to_boards_table.php`
- Create: `app/Models/Workspace.php`
- Modify: `app/Models/Board.php`
- Modify: `app/Models/Card.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/WorkspaceFactory.php`
- Modify: `database/factories/BoardFactory.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Test: `tests/Feature/WorkspaceModelTest.php`

**Interfaces:**
- Produces: `Workspace` (fields: `id`, `owner_id`, `name`, timestamps; relations `owner(): BelongsTo`, `members(): BelongsToMany` via `workspace_user`, `boards(): HasMany`). `Board` gains `workspace_id` (fillable, nullable at the DB level but always populated by application code), `workspace(): BelongsTo`, `members(): BelongsToMany` via `board_user`. `Card` gains `members(): BelongsToMany` via `card_user`. `User` gains `ownedWorkspaces(): HasMany`, `workspaces(): BelongsToMany`, `boardMemberships(): BelongsToMany`.
- **Critical for every later task and every existing test file**: `BoardFactory::configure()` auto-creates a `Workspace` owned by the board's `user_id` (if `workspace_id` wasn't explicitly set via `Board::factory()->for($workspace)`) and attaches that user as both a workspace member and a board member. This means every existing `Board::factory()->for($user)->create()` call across the whole test suite continues to work unchanged — that user remains able to view/update "their" board because they're now a board member, not because of the old ownership check. **Do not modify `BoardListTest.php`, `CardTest.php`, or `ChecklistTest.php` in this task** — they must keep passing unmodified because of this factory behavior.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WorkspaceModelTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a workspace has an owner and members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);

    expect($workspace->owner->is($owner))->toBeTrue();
    expect($workspace->members)->toHaveCount(2);
    expect($workspace->members->pluck('id'))->toContain($owner->id, $other->id);
});

test('a board belongs to a workspace and gets its creator as a member', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    expect($board->workspace)->not->toBeNull();
    expect($board->workspace->owner_id)->toBe($user->id);
    expect($board->members->pluck('id'))->toContain($user->id);
    expect($board->workspace->members->pluck('id'))->toContain($user->id);
});

test('a board created with an explicit workspace does not get a second one', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $board = Board::factory()->for($workspace)->for($owner)->create();

    expect($board->workspace_id)->toBe($workspace->id);
    expect(Workspace::count())->toBe(1);
});

test('registering a new user creates a personal workspace', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'jane@example.com')->first();

    expect($user->ownedWorkspaces)->toHaveCount(1);
    expect($user->ownedWorkspaces->first()->name)->toBe("Jane Doe's Workspace");
    expect($user->workspaces)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WorkspaceModelTest`
Expected: FAIL — `App\Models\Workspace` does not exist.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_08_20_090001_create_workspaces_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
```

`database/migrations/2026_08_20_090002_create_workspace_user_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
```

`database/migrations/2026_08_20_090003_create_board_user_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_user');
    }
};
```

`database/migrations/2026_08_20_090004_create_card_user_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['card_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_user');
    }
};
```

`database/migrations/2026_08_20_090005_add_workspace_id_to_boards_table.php` — adds the column (nullable at the DB level, to avoid a fragile `->change()` alter) and backfills a personal workspace for every user who already owns a board:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        $userIds = DB::table('boards')->whereNull('workspace_id')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = DB::table('users')->find($userId);

            if (! $user) {
                continue;
            }

            $workspaceId = DB::table('workspaces')->insertGetId([
                'owner_id' => $userId,
                'name' => "{$user->name}'s Workspace",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $boardIds = DB::table('boards')->where('user_id', $userId)->pluck('id');

            DB::table('boards')->where('user_id', $userId)->update(['workspace_id' => $workspaceId]);

            foreach ($boardIds as $boardId) {
                DB::table('board_user')->insert([
                    'board_id' => $boardId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
```

- [ ] **Step 4: Create the Workspace model**

`app/Models/Workspace.php`:

```php
<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withTimestamps();
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }
}
```

- [ ] **Step 5: Update Board, Card, and User models**

Modify `app/Models/Board.php` — add `'workspace_id'` to `$fillable`, and add these two methods (plus the `BelongsToMany` import):

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

```php
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user')->withTimestamps();
    }
```

Modify `app/Models/Card.php` — add the import and method:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

```php
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_user')->withTimestamps();
    }
```

Modify `app/Models/User.php` — add the import and three methods:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

```php
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withTimestamps();
    }

    public function boardMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_user')->withTimestamps();
    }
```

- [ ] **Step 6: Create the WorkspaceFactory**

`database/factories/WorkspaceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company().' Workspace',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace) {
            $workspace->members()->syncWithoutDetaching([$workspace->owner_id]);
        });
    }
}
```

- [ ] **Step 7: Update BoardFactory**

Modify `database/factories/BoardFactory.php` to the following full content:

```php
<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
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

    public function configure(): static
    {
        return $this->afterMaking(function (Board $board) {
            if (! $board->workspace_id) {
                $board->workspace_id = Workspace::factory()->create(['owner_id' => $board->user_id])->id;
            }
        })->afterCreating(function (Board $board) {
            $board->members()->syncWithoutDetaching([$board->user_id]);
            $board->workspace->members()->syncWithoutDetaching([$board->user_id]);
        });
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
```

- [ ] **Step 8: Auto-create a workspace on registration**

Modify `app/Http/Controllers/Auth/RegisteredUserController.php` — in `store()`, after `$user = User::create([...]);` and before `event(new Registered($user));`, insert:

```php
        $workspace = $user->ownedWorkspaces()->create(['name' => "{$user->name}'s Workspace"]);
        $workspace->members()->attach($user->id);
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --compact --filter=WorkspaceModelTest`
Expected: PASS (4 tests)

- [ ] **Step 10: Run the full suite to confirm zero regressions**

Run: `php artisan test --compact`
Expected: all previously-passing tests still pass (the `BoardFactory` change must be fully transparent to every existing test).

- [ ] **Step 11: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_20_090001_create_workspaces_table.php database/migrations/2026_08_20_090002_create_workspace_user_table.php database/migrations/2026_08_20_090003_create_board_user_table.php database/migrations/2026_08_20_090004_create_card_user_table.php database/migrations/2026_08_20_090005_add_workspace_id_to_boards_table.php app/Models/Workspace.php app/Models/Board.php app/Models/Card.php app/Models/User.php database/factories/WorkspaceFactory.php database/factories/BoardFactory.php app/Http/Controllers/Auth/RegisteredUserController.php tests/Feature/WorkspaceModelTest.php
git commit -m "feat: add workspace, board, and card membership schema"
```

---

## Task 2: Workspace CRUD + membership management (backend)

**Files:**
- Create: `app/Policies/WorkspacePolicy.php`
- Create: `app/Http/Requests/Workspaces/StoreWorkspaceRequest.php`
- Create: `app/Http/Requests/Workspaces/UpdateWorkspaceRequest.php`
- Create: `app/Http/Controllers/WorkspaceController.php`
- Create: `app/Http/Controllers/WorkspaceMemberController.php`
- Create: `routes/workspaces.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/WorkspaceTest.php`
- Test: `tests/Feature/WorkspaceMemberTest.php`

**Interfaces:**
- Consumes: `Workspace` model and `Workspace::factory()` from Task 1.
- Produces: routes `workspaces.index` (`GET /workspaces`), `workspaces.store` (`POST /workspaces`), `workspaces.show` (`GET /workspaces/{workspace}`), `workspaces.update` (`PATCH /workspaces/{workspace}`), `workspaces.destroy` (`DELETE /workspaces/{workspace}`), `workspace-members.search` (`GET /workspaces/{workspace}/members/search`), `workspace-members.store` (`POST /workspaces/{workspace}/members`), `workspace-members.destroy` (`DELETE /workspaces/{workspace}/members/{user}`). Inertia pages `workspaces/Index` (prop `workspaces: Workspace[]`), `workspaces/Show` (props `workspace: Workspace`, `boards: Board[]`, `members: User[]`). `WorkspacePolicy` (`view`, `update`, `delete`) — reused by Task 3 for board-creation authorization.
- **This task does not touch board creation or `boards.archived`** — those routes are added to `routes/workspaces.php` in Task 3, once `BoardController` is updated to accept a `Workspace` parameter. Do not add them here.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WorkspaceTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Workspace;

test('a user can create a workspace and becomes its owner and member', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/workspaces', ['name' => 'Acme Team']);

    $workspace = Workspace::first();
    $response->assertRedirect("/workspaces/{$workspace->id}");
    expect($workspace->owner_id)->toBe($user->id);
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('creating a workspace requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/workspaces', ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user can view a workspace they are a member of', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('workspaces/Show')->where('workspace.id', $workspace->id));
});

test('a user cannot view a workspace they are not a member of', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/workspaces/{$workspace->id}");

    $response->assertForbidden();
});

test('the workspace owner can rename it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old']);

    $response = $this->actingAs($owner)->patch("/workspaces/{$workspace->id}", ['name' => 'New']);

    $response->assertRedirect();
    expect($workspace->fresh()->name)->toBe('New');
});

test('a non-owner member cannot rename the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old']);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->patch("/workspaces/{$workspace->id}", ['name' => 'New']);

    $response->assertForbidden();
    expect($workspace->fresh()->name)->toBe('Old');
});

test('the workspace owner can delete it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

    $response->assertRedirect('/workspaces');
    expect(Workspace::find($workspace->id))->toBeNull();
});

test('a non-owner member cannot delete the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}");

    $response->assertForbidden();
    expect(Workspace::find($workspace->id))->not->toBeNull();
});

test('the workspaces index only lists workspaces the user belongs to', function () {
    $user = User::factory()->create();
    $myWorkspace = Workspace::factory()->for($user, 'owner')->create();
    Workspace::factory()->create();

    $response = $this->actingAs($user)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page->component('workspaces/Index')->has('workspaces', 1)->where('workspaces.0.id', $myWorkspace->id)
    );
});
```

Create `tests/Feature/WorkspaceMemberTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Workspace;

test('the workspace owner can search for a user to add', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $candidate = User::factory()->create(['name' => 'Jane Searchable']);

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/members/search?q=Searchable");

    $response->assertOk();
    $response->assertJsonFragment(['id' => $candidate->id]);
});

test('search excludes users who are already members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create(['name' => 'Already Member']);
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/members/search?q=Already");

    $response->assertJsonMissing(['id' => $member->id]);
});

test('a non-owner cannot search workspace members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->get("/workspaces/{$workspace->id}/members/search?q=x");

    $response->assertForbidden();
});

test('the workspace owner can add an existing user as a member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $candidate = User::factory()->create();

    $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => $candidate->id]);

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $candidate->id)->exists())->toBeTrue();
});

test('a non-owner cannot add a workspace member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $candidate = User::factory()->create();

    $response = $this->actingAs($member)->post("/workspaces/{$workspace->id}/members", ['user_id' => $candidate->id]);

    $response->assertForbidden();
    expect($workspace->members()->where('users.id', $candidate->id)->exists())->toBeFalse();
});

test('the workspace owner can remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a member can remove themselves from the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a member cannot remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $workspace->members()->attach([$memberA->id, $memberB->id]);

    $response = $this->actingAs($memberA)->delete("/workspaces/{$workspace->id}/members/{$memberB->id}");

    $response->assertForbidden();
    expect($workspace->members()->where('users.id', $memberB->id)->exists())->toBeTrue();
});

test('the owner cannot be removed from the workspace, even by themselves', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

    $response->assertStatus(422);
    expect($workspace->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=WorkspaceTest` and `php artisan test --compact --filter=WorkspaceMemberTest`
Expected: FAIL — routes/controllers don't exist yet (404s).

- [ ] **Step 3: Create the policy**

`app/Policies/WorkspacePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }
}
```

- [ ] **Step 4: Create the form requests**

`app/Http/Requests/Workspaces/StoreWorkspaceRequest.php`:

```php
<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
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
        ];
    }
}
```

`app/Http/Requests/Workspaces/UpdateWorkspaceRequest.php`:

```php
<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workspace'));
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

- [ ] **Step 5: Create the controllers**

`app/Http/Controllers/WorkspaceController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaces = $request->user()->workspaces()
            ->withCount('boards')
            ->orderBy('name')
            ->get();

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $request->user()->ownedWorkspaces()->create($request->validated());
        $workspace->members()->attach($request->user()->id);

        return to_route('workspaces.show', $workspace);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()->whereNull('archived_at')->latest()->get();
        $members = $workspace->members()->orderBy('name')->get();

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'boards' => $boards,
            'members' => $members,
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $workspace->update($request->validated());

        return back();
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return to_route('workspaces.index');
    }
}
```

`app/Http/Controllers/WorkspaceMemberController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    public function search(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('update', $workspace);

        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['users' => []]);
        }

        $existingMemberIds = $workspace->members()->pluck('users.id');

        $users = User::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->whereNotIn('id', $existingMemberIds)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $workspace->members()->syncWithoutDetaching([$validated['user_id']]);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser->id === $workspace->owner_id || $currentUser->id === $user->id,
            403
        );

        abort_if($user->id === $workspace->owner_id, 422, 'The workspace owner cannot be removed.');

        $workspace->members()->detach($user->id);

        return back();
    }
}
```

- [ ] **Step 6: Create the routes file and wire it up**

`routes/workspaces.php`:

```php
<?php

use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    Route::get('workspaces/{workspace}/members/search', [WorkspaceMemberController::class, 'search'])->name('workspace-members.search');
    Route::post('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('workspace-members.store');
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspace-members.destroy');
});
```

Modify `routes/web.php` — add before `require __DIR__.'/boards.php';`:

```php
require __DIR__.'/workspaces.php';
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact --filter=WorkspaceTest` (8 tests) and `php artisan test --compact --filter=WorkspaceMemberTest` (9 tests)
Expected: PASS

- [ ] **Step 8: Run the full suite, format, and commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add app/Policies/WorkspacePolicy.php app/Http/Requests/Workspaces app/Http/Controllers/WorkspaceController.php app/Http/Controllers/WorkspaceMemberController.php routes/workspaces.php routes/web.php tests/Feature/WorkspaceTest.php tests/Feature/WorkspaceMemberTest.php
git commit -m "feat: add workspace CRUD and membership management"
```

---

## Task 3: Board access rewritten to membership + board creation under workspace + board member management

**Files:**
- Modify: `app/Policies/BoardPolicy.php`
- Modify: `app/Http/Controllers/BoardController.php`
- Modify: `app/Http/Requests/Boards/StoreBoardRequest.php`
- Create: `app/Http/Requests/Boards/StoreBoardMemberRequest.php`
- Create: `app/Http/Controllers/BoardMemberController.php`
- Modify: `routes/workspaces.php`
- Modify: `routes/boards.php`
- Modify: `tests/Feature/BoardTest.php` (full rewrite — see Step 1)
- Test: `tests/Feature/BoardMemberTest.php`

**Interfaces:**
- Consumes: `Workspace`, `Board` (with `members()`, `workspace()`) from Tasks 1–2; `WorkspacePolicy` reused implicitly via the `view` ability check on board creation.
- Produces: `BoardPolicy::view`/`update` now mean "is a board member" (was "is `board.user_id`"); `BoardPolicy::delete` still means "is `board.user_id`" (the creator). Routes: `workspaces.boards.store` (`POST /workspaces/{workspace}/boards`), `boards.archived` now `GET /workspaces/{workspace}/boards/archived` (was the global `/boards/archived`). The global `boards.index`/`boards.store`/old `boards.archived` routes are **removed**. New: `board-members.store` (`POST /boards/{board}/members`), `board-members.destroy` (`DELETE /boards/{board}/members/{user}`).
- **Do not modify** `tests/Feature/BoardListTest.php`, `tests/Feature/CardTest.php`, or `tests/Feature/ChecklistTest.php` — per Task 1's factory design, they continue to pass unchanged because `BoardPolicy`'s new membership check still returns true for the board's creating user (auto-added as a member by the factory).

- [ ] **Step 1: Replace `tests/Feature/BoardTest.php` with the new version, and write `tests/Feature/BoardMemberTest.php`**

Replace the full contents of `tests/Feature/BoardTest.php` with:

```php
<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a workspace shows only its own active boards', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $activeBoard = Board::factory()->for($workspace)->for($user)->create(['name' => 'Active Board']);
    Board::factory()->for($workspace)->for($user)->archived()->create(['name' => 'Archived Board']);
    Board::factory()->for($user)->create(['name' => 'Different Workspace Board']);

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('workspaces/Show')
            ->has('boards', 1)
            ->where('boards.0.id', $activeBoard->id)
    );
});

test('archived lists only the workspace\'s archived boards', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    Board::factory()->for($workspace)->for($user)->create();
    $archivedBoard = Board::factory()->for($workspace)->for($user)->archived()->create();

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}/boards/archived");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Archived')
            ->has('boards', 1)
            ->where('boards.0.id', $archivedBoard->id)
    );
});

test('a workspace member can create a board in the workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $response = $this->actingAs($user)->post("/workspaces/{$workspace->id}/boards", [
        'name' => 'My New Board',
        'background_color' => '#0079BF',
    ]);

    $board = Board::where('name', 'My New Board')->first();

    $response->assertRedirect("/boards/{$board->id}");
    expect($board->workspace_id)->toBe($workspace->id);
    expect($board->user_id)->toBe($user->id);
    expect($board->members()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('creating a board requires a name', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $response = $this->actingAs($user)->post("/workspaces/{$workspace->id}/boards", ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user who is not a workspace member cannot create a board in it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/workspaces/{$workspace->id}/boards", ['name' => 'Sneaky Board']);

    $response->assertForbidden();
});

test('a board member can view the board', function () {
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

test('a user who is not a board member cannot view the board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('a workspace member who is not a board member cannot view the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $workspaceOnlyMember = User::factory()->create();
    $workspace->members()->attach($workspaceOnlyMember->id);

    $response = $this->actingAs($workspaceOnlyMember)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('an added board member can view and update the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create(['name' => 'Old name']);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($member)->get("/boards/{$board->id}")->assertOk();

    $response = $this->actingAs($member)->patch("/boards/{$board->id}", ['name' => 'New name']);
    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a user can rename their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Old name']);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}", ['name' => 'New name']);

    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a non-member cannot rename the board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create(['name' => 'Old name']);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}", ['name' => 'New name']);

    $response->assertForbidden();
    expect($board->fresh()->name)->toBe('Old name');
});

test('a user can archive and restore their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $this->actingAs($user)->patch("/boards/{$board->id}/archive")->assertRedirect("/workspaces/{$board->workspace_id}");
    expect($board->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/boards/{$board->id}/restore")->assertRedirect("/workspaces/{$board->workspace_id}/boards/archived");
    expect($board->fresh()->archived_at)->toBeNull();
});

test('a non archived board cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertStatus(422);
    expect(Board::find($board->id))->not->toBeNull();
});

test('an archived board can be permanently deleted by its creator', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertRedirect("/workspaces/{$board->workspace_id}/boards/archived");
    expect(Board::find($board->id))->toBeNull();
});

test('a board member who is not the creator cannot permanently delete the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->archived()->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/boards/{$board->id}");

    $response->assertForbidden();
    expect(Board::find($board->id))->not->toBeNull();
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

Create `tests/Feature/BoardMemberTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a board member can add another workspace member to the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id]);

    $response->assertRedirect();
    expect($board->members()->where('users.id', $candidate->id)->exists())->toBeTrue();
});

test('a user who is not yet a workspace member cannot be added to the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $outsider->id]);

    $response->assertSessionHasErrors('user_id');
    expect($board->members()->where('users.id', $outsider->id)->exists())->toBeFalse();
});

test('a non-board-member cannot add a board member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id]);

    $response->assertForbidden();
});

test('a board member can remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a board member can remove themselves (leave)', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('the board creator cannot be removed', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$owner->id}");

    $response->assertStatus(422);
    expect($board->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=BoardTest` and `php artisan test --compact --filter=BoardMemberTest`
Expected: FAIL — old routes/authorization don't match the new tests yet.

- [ ] **Step 3: Rewrite BoardPolicy**

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
        return $board->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Board $board): bool
    {
        return $board->members()->where('users.id', $user->id)->exists();
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->id === $board->user_id;
    }
}
```

- [ ] **Step 4: Update StoreBoardRequest**

Modify `app/Http/Requests/Boards/StoreBoardRequest.php` — change `authorize()` from `return true;` to:

```php
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('workspace'));
    }
```

- [ ] **Step 5: Rewrite BoardController**

Replace the full contents of `app/Http/Controllers/BoardController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Card;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function archived(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->get();

        return Inertia::render('boards/Archived', [
            'workspace' => $workspace,
            'boards' => $boards,
        ]);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace): RedirectResponse
    {
        $board = $workspace->boards()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $board->members()->attach($request->user()->id);

        return to_route('boards.show', $board);
    }

    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'workspace.members',
            'members' => fn ($query) => $query->orderBy('name'),
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards.checklists' => fn ($query) => $query->orderBy('position'),
            'lists.cards.checklists.items' => fn ($query) => $query->orderBy('position'),
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

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $board->update($request->validated());

        return back();
    }

    public function archive(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => now()]);

        return to_route('workspaces.show', $board->workspace_id);
    }

    public function restore(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board->update(['archived_at' => null]);

        return to_route('boards.archived', $board->workspace_id);
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        abort_if($board->archived_at === null, 422, 'Only archived boards can be permanently deleted.');

        $board->delete();

        return to_route('boards.archived', $board->workspace_id);
    }
}
```

Note: `Card` members eager-loading (`lists.cards.members`) is intentionally NOT added here — that's Task 6's job.

- [ ] **Step 6: Create the board-member form request and controller**

`app/Http/Requests/Boards/StoreBoardMemberRequest.php`:

```php
<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardMemberRequest extends FormRequest
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
        $board = $this->route('board');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('workspace_user', 'user_id')->where('workspace_id', $board->workspace_id),
            ],
        ];
    }
}
```

`app/Http/Controllers/BoardMemberController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardMemberRequest;
use App\Models\Board;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardMemberController extends Controller
{
    public function store(StoreBoardMemberRequest $request, Board $board): RedirectResponse
    {
        $board->members()->syncWithoutDetaching([$request->validated('user_id')]);

        return back();
    }

    public function destroy(Request $request, Board $board, User $user): RedirectResponse
    {
        Gate::authorize('update', $board);

        abort_if($user->id === $board->user_id, 422, 'The board creator cannot be removed.');

        $board->members()->detach($user->id);

        return back();
    }
}
```

- [ ] **Step 7: Update routes**

Modify `routes/workspaces.php` — add the import and two routes inside the existing `Route::middleware(['auth', 'verified'])->group(...)` closure, after the workspace-member routes:

```php
use App\Http\Controllers\BoardController;
```

```php
    Route::post('workspaces/{workspace}/boards', [BoardController::class, 'store'])->name('workspaces.boards.store');
    Route::get('workspaces/{workspace}/boards/archived', [BoardController::class, 'archived'])->name('boards.archived');
```

Modify `routes/boards.php` — remove these three lines entirely:

```php
    Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
    Route::get('boards/archived', [BoardController::class, 'archived'])->name('boards.archived');
    Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
```

Then add the import and two routes (placed right after the existing `boards.destroy` line):

```php
use App\Http\Controllers\BoardMemberController;
```

```php
    Route::post('boards/{board}/members', [BoardMemberController::class, 'store'])->name('board-members.store');
    Route::delete('boards/{board}/members/{user}', [BoardMemberController::class, 'destroy'])->name('board-members.destroy');
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact --filter=BoardTest` (16 tests) and `php artisan test --compact --filter=BoardMemberTest` (6 tests)
Expected: PASS

- [ ] **Step 9: Run the full suite to confirm zero regressions elsewhere**

Run: `php artisan test --compact`
Expected: `BoardListTest`, `CardTest`, `ChecklistTest`, `BoardArchivePanelTest`, `WorkspaceTest`, `WorkspaceMemberTest` all still pass unmodified.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/BoardPolicy.php app/Http/Controllers/BoardController.php app/Http/Requests/Boards/StoreBoardRequest.php app/Http/Requests/Boards/StoreBoardMemberRequest.php app/Http/Controllers/BoardMemberController.php routes/workspaces.php routes/boards.php tests/Feature/BoardTest.php tests/Feature/BoardMemberTest.php
git commit -m "feat: switch board access to membership and add board member management"
```

---

## Task 4: Frontend — Workspaces pages, sidebar nav, reusable member UI

**Files:**
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/components/MemberAvatar.vue`
- Create: `resources/js/components/boards/WorkspaceMemberPanel.vue`
- Create: `resources/js/pages/workspaces/Index.vue`
- Create: `resources/js/pages/workspaces/Show.vue`
- Create: `resources/js/pages/workspaces/Archived.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Delete: `resources/js/pages/boards/Index.vue`
- Delete: `resources/js/pages/boards/Archived.vue`

**Interfaces:**
- Consumes: routes `workspaces.index`, `workspaces.store`, `workspaces.show`, `workspaces.update`, `workspaces.destroy`, `workspace-members.search`, `workspace-members.store`, `workspace-members.destroy`, `workspaces.boards.store`, `boards.archived` (Tasks 2–3). Props from `workspaces.show`: `workspace: Workspace`, `boards: Board[]`, `members: User[]`.
- Produces: TypeScript `Workspace` interface, `MemberAvatar.vue` (props `{ user: User, size?: 'xs' | 'sm' }`) — reused directly by Tasks 5–6 without changes.

- [ ] **Step 1: Add the Workspace TypeScript type and extend Board**

Modify `resources/js/types/index.ts` — add after the `Card`/`BoardList` interfaces and before `Board`:

```ts
export interface Workspace {
    id: number;
    owner_id: number;
    name: string;
    created_at: string;
    updated_at: string;
    boards_count?: number;
    members?: User[];
}
```

Modify the existing `Board` interface — add `workspace_id`, `members?`, and `workspace?` fields:

```ts
export interface Board {
    id: number;
    user_id: number;
    workspace_id: number;
    name: string;
    background_color: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    lists?: BoardList[];
    members?: User[];
    workspace?: Workspace;
}
```

- [ ] **Step 2: Create the MemberAvatar component**

`resources/js/components/MemberAvatar.vue`:

```vue
<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

withDefaults(
    defineProps<{
        user: User;
        size?: 'xs' | 'sm';
    }>(),
    {
        size: 'sm',
    },
);

const { getInitials } = useInitials();
</script>

<template>
    <Avatar :class="size === 'xs' ? 'size-6 text-[10px]' : 'size-8 text-xs'" :title="user.name">
        <AvatarFallback class="bg-primary/10 text-primary">{{ getInitials(user.name) }}</AvatarFallback>
    </Avatar>
</template>
```

- [ ] **Step 3: Create the WorkspaceMemberPanel component**

`resources/js/components/boards/WorkspaceMemberPanel.vue`:

```vue
<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { SharedData, User, Workspace } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    workspace: Workspace;
    members: User[];
    isOwner: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const currentUserId = usePage<SharedData>().props.auth.user.id;

const query = ref('');
const results = ref<User[]>([]);
const searching = ref(false);
let searchToken = 0;

watch(query, async (value) => {
    const trimmed = value.trim();

    if (!trimmed) {
        results.value = [];
        return;
    }

    const token = ++searchToken;
    searching.value = true;

    try {
        const response = await fetch(`${route('workspace-members.search', props.workspace.id)}?q=${encodeURIComponent(trimmed)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (token === searchToken) {
            results.value = data.users;
        }
    } finally {
        if (token === searchToken) {
            searching.value = false;
        }
    }
});

function addMember(user: User) {
    router.post(
        route('workspace-members.store', props.workspace.id),
        { user_id: user.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                query.value = '';
                results.value = [];
            },
        },
    );
}

function removeMember(user: User) {
    const label = user.id === currentUserId ? 'leave this workspace' : `remove ${user.name} from this workspace`;

    if (!confirm(`Are you sure you want to ${label}?`)) {
        return;
    }

    router.delete(route('workspace-members.destroy', [props.workspace.id, user.id]), { preserveScroll: true });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Workspace members</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div v-if="isOwner" class="space-y-2">
                    <Input v-model="query" placeholder="Search by name or email" />
                    <ul v-if="results.length" class="space-y-1 rounded-md border p-1">
                        <li
                            v-for="user in results"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 rounded p-2 text-sm hover:bg-accent"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="user" size="xs" />
                                <div>
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                </div>
                            </div>
                            <Button size="sm" @click="addMember(user)">Add</Button>
                        </li>
                    </ul>
                    <p v-else-if="query.trim() && !searching" class="text-sm text-muted-foreground">No users found.</p>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Members ({{ members.length }})</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="member" size="xs" />
                                <p class="font-medium">
                                    {{ member.name }}
                                    <span v-if="member.id === workspace.owner_id" class="text-xs text-muted-foreground">(owner)</span>
                                </p>
                            </div>
                            <Button
                                v-if="member.id !== workspace.owner_id && (isOwner || member.id === currentUserId)"
                                variant="ghost"
                                size="sm"
                                @click="removeMember(member)"
                            >
                                {{ member.id === currentUserId ? 'Leave' : 'Remove' }}
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

- [ ] **Step 4: Create the Workspaces Index page**

`resources/js/pages/workspaces/Index.vue`:

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Workspace } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    workspaces: Workspace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Workspaces', href: '/workspaces' }];

const showCreate = ref(false);

const form = useForm({
    name: '',
});

function submit() {
    form.post(route('workspaces.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Workspaces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Your workspaces</h1>
                <Dialog v-model:open="showCreate">
                    <DialogTrigger as-child>
                        <Button size="sm">New workspace</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New workspace</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="workspace-name">Name</Label>
                                <Input id="workspace-name" v-model="form.name" required autofocus />
                                <InputError :message="form.errors.name" />
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <p v-if="workspaces.length === 0" class="text-sm text-muted-foreground">No workspaces yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="workspace in workspaces" :key="workspace.id" :href="route('workspaces.show', workspace.id)">
                    <div
                        class="flex h-24 flex-col justify-between rounded-lg border border-neutral-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <p class="line-clamp-2 font-semibold text-neutral-900 dark:text-neutral-100">{{ workspace.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ workspace.boards_count ?? 0 }} board(s)</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 5: Create the Workspaces Show page**

`resources/js/pages/workspaces/Show.vue`:

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkspaceMemberPanel from '@/components/boards/WorkspaceMemberPanel.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, SharedData, User, Workspace } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    workspace: Workspace;
    boards: Board[];
    members: User[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.workspace.name, href: route('workspaces.show', props.workspace.id) },
]);

const currentUserId = usePage<SharedData>().props.auth.user.id;
const isOwner = computed(() => props.workspace.owner_id === currentUserId);

const showCreateBoard = ref(false);

const boardForm = useForm({
    name: '',
    background_color: '#0079BF',
});

function submitBoard() {
    boardForm.post(route('workspaces.boards.store', props.workspace.id), {
        onSuccess: () => {
            showCreateBoard.value = false;
            boardForm.reset();
        },
    });
}

const showMembers = ref(false);

function deleteWorkspace() {
    if (!confirm(`Delete the workspace "${props.workspace.name}"? This permanently deletes all its boards too.`)) {
        return;
    }

    router.delete(route('workspaces.destroy', props.workspace.id));
}
</script>

<template>
    <Head :title="workspace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">{{ workspace.name }}</h1>
                <div class="flex items-center gap-2">
                    <Link :href="route('boards.archived', workspace.id)" class="text-sm text-muted-foreground underline">Archived boards</Link>
                    <Button variant="outline" size="sm" @click="showMembers = true">Members ({{ members.length }})</Button>
                    <Dialog v-model:open="showCreateBoard">
                        <DialogTrigger as-child>
                            <Button size="sm">New board</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New board</DialogTitle>
                            </DialogHeader>
                            <form class="space-y-4" @submit.prevent="submitBoard">
                                <div class="grid gap-2">
                                    <Label for="board-name">Name</Label>
                                    <Input id="board-name" v-model="boardForm.name" required autofocus />
                                    <InputError :message="boardForm.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="board-color">Color</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="board-color"
                                            v-model="boardForm.background_color"
                                            type="color"
                                            class="h-9 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                        />
                                        <span class="text-sm text-muted-foreground">{{ boardForm.background_color }}</span>
                                    </div>
                                    <InputError :message="boardForm.errors.background_color" />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" :disabled="boardForm.processing">Create</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                    <DropdownMenu v-if="isOwner">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                aria-label="Workspace actions"
                            >
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="deleteWorkspace">Delete workspace</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)" class="group block">
                    <div
                        class="flex h-24 flex-col justify-between rounded-lg p-4 shadow-sm transition group-hover:shadow-md group-hover:brightness-110"
                        :style="{ backgroundColor: board.background_color || '#44546f' }"
                    >
                        <p class="line-clamp-2 font-semibold text-white drop-shadow-sm">{{ board.name }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <WorkspaceMemberPanel v-model:open="showMembers" :workspace="workspace" :members="members" :is-owner="isOwner" />
    </AppLayout>
</template>
```

- [ ] **Step 6: Create the Workspaces Archived page**

`resources/js/pages/workspaces/Archived.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, Workspace } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    workspace: Workspace;
    boards: Board[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspaces', href: '/workspaces' },
    { title: props.workspace.name, href: route('workspaces.show', props.workspace.id) },
    { title: 'Archived', href: route('boards.archived', props.workspace.id) },
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
                <h1 class="text-lg font-semibold">Archived boards — {{ workspace.name }}</h1>
                <Link :href="route('workspaces.show', workspace.id)" class="text-sm text-muted-foreground underline">Back to workspace</Link>
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

- [ ] **Step 7: Update the sidebar nav and remove the old global boards pages**

Modify `resources/js/components/AppSidebar.vue` — change the "Boards" nav item's title and href:

```ts
    {
        title: 'Workspaces',
        href: '/workspaces',
        icon: Kanban,
    },
```

Delete `resources/js/pages/boards/Index.vue` and `resources/js/pages/boards/Archived.vue` — they are unreachable now that the old global `boards.index`/`boards.store`/`boards.archived` routes were removed in Task 3.

- [ ] **Step 8: Verify in the browser**

Run `npm run build`, start the app, log in.

1. Click "Workspaces" in the sidebar → lands on `/workspaces`, showing the auto-created personal workspace from registration.
2. Click into it → shows an empty board grid, "Members (1)" button, "New board" button.
3. Click "Members" → panel shows yourself as owner; try searching for another registered user (create a second test account first) and add them.
4. Create a board, confirm it appears in the grid.
5. Click "New workspace", create a second workspace, confirm it's independent (different boards, different members).

- [ ] **Step 9: Lint and commit**

```bash
npm run lint
git add resources/js/types/index.ts resources/js/components/MemberAvatar.vue resources/js/components/boards/WorkspaceMemberPanel.vue resources/js/pages/workspaces resources/js/components/AppSidebar.vue
git rm resources/js/pages/boards/Index.vue resources/js/pages/boards/Archived.vue
git commit -m "feat: add workspaces frontend, sidebar nav, and remove global boards pages"
```

---

## Task 5: Frontend — Board Show page members panel

**Files:**
- Create: `resources/js/components/boards/BoardMemberPanel.vue`
- Modify: `resources/js/pages/boards/Show.vue`

**Interfaces:**
- Consumes: `board.members`, `board.workspace.members` (Task 3's updated `BoardController::show` eager-load), routes `board-members.store`/`board-members.destroy` (Task 3), `MemberAvatar.vue` (Task 4).
- Produces: nothing new consumed by later tasks — Task 6 builds its own card-scoped member picker.

- [ ] **Step 1: Create the BoardMemberPanel component**

`resources/js/components/boards/BoardMemberPanel.vue`:

```vue
<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { Board, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    board: Board;
    workspaceMembers: User[];
}>();

const open = defineModel<boolean>('open', { default: false });

const availableMembers = computed(() => {
    const memberIds = new Set((props.board.members ?? []).map((member) => member.id));

    return props.workspaceMembers.filter((user) => !memberIds.has(user.id));
});

function addMember(user: User) {
    router.post(route('board-members.store', props.board.id), { user_id: user.id }, { preserveScroll: true });
}

function removeMember(user: User) {
    router.delete(route('board-members.destroy', [props.board.id, user.id]), { preserveScroll: true });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Board members</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Members ({{ (board.members ?? []).length }})</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="member in board.members"
                            :key="member.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="member" size="xs" />
                                <p class="font-medium">
                                    {{ member.name }}
                                    <span v-if="member.id === board.user_id" class="text-xs text-muted-foreground">(creator)</span>
                                </p>
                            </div>
                            <Button v-if="member.id !== board.user_id" variant="ghost" size="sm" @click="removeMember(member)">Remove</Button>
                        </li>
                    </ul>
                </div>

                <div v-if="availableMembers.length" class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Add from workspace</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="user in availableMembers"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="user" size="xs" />
                                <p class="font-medium">{{ user.name }}</p>
                            </div>
                            <Button size="sm" @click="addMember(user)">Add</Button>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

- [ ] **Step 2: Wire it into the Board Show page**

Modify `resources/js/pages/boards/Show.vue`:

```ts
import BoardMemberPanel from '@/components/boards/BoardMemberPanel.vue';
```

Update the breadcrumbs computed (currently references the removed `boards.index` route):

```ts
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.board.workspace?.name ?? '', href: route('workspaces.show', props.board.workspace_id) },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);
```

Add state and open the panel from a new "Members" button next to "View archive":

```ts
const showMembers = ref(false);
```

```html
<Button variant="outline" size="sm" @click="showMembers = true">Members ({{ (board.members ?? []).length }})</Button>
```

(place this button in the same `div` as the existing "View archive" button, before it or after it — either order is fine)

Add the panel near the other panels at the bottom of the template:

```html
<BoardMemberPanel v-model:open="showMembers" :board="board" :workspace-members="board.workspace?.members ?? []" />
```

- [ ] **Step 3: Verify in the browser**

1. Open a board that belongs to a workspace with 2+ members (add a second member via the Workspace members panel first if needed).
2. Click "Members" on the board — confirm the creator shows with "(creator)" and no remove button; the other workspace member shows under "Add from workspace".
3. Add them, confirm they move into the "Members" list with a "Remove" button.
4. Remove them, confirm they move back to "Add from workspace".
5. Confirm the breadcrumb now reads Workspaces → `<workspace name>` → `<board name>`, and both links work.

- [ ] **Step 4: Lint and commit**

```bash
npm run lint
git add resources/js/components/boards/BoardMemberPanel.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add board members panel"
```

---

## Task 6: Backend + Frontend — Card members (assignees)

**Files:**
- Create: `app/Http/Requests/Cards/StoreCardMemberRequest.php`
- Create: `app/Http/Controllers/CardMemberController.php`
- Modify: `routes/boards.php`
- Modify: `app/Http/Controllers/BoardController.php`
- Test: `tests/Feature/CardMemberTest.php`
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/components/boards/CardMemberPicker.vue`
- Modify: `resources/js/components/boards/CardDetailModal.vue`
- Modify: `resources/js/components/boards/BoardCard.vue`
- Modify: `resources/js/pages/boards/Show.vue`

**Interfaces:**
- Consumes: `board.members` (Task 3), `MemberAvatar.vue` (Task 4).
- Produces: routes `card-members.store` (`POST /cards/{card}/members`), `card-members.destroy` (`DELETE /cards/{card}/members/{user}`). `Card.members?: User[]` in the board-show payload.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CardMemberTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;

test('a board member can assign another board member to a card', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $member->id]);

    $response->assertRedirect();
    expect($card->members()->where('users.id', $member->id)->exists())->toBeTrue();
});

test('a user who is not a board member cannot be assigned to a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $outsider->id]);

    $response->assertSessionHasErrors('user_id');
    expect($card->members()->where('users.id', $outsider->id)->exists())->toBeFalse();
});

test('a non-board-member cannot assign a card member', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/cards/{$card->id}/members", ['user_id' => $owner->id]);

    $response->assertForbidden();
});

test('a board member can unassign a card member', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);

    $response = $this->actingAs($owner)->delete("/cards/{$card->id}/members/{$owner->id}");

    $response->assertRedirect();
    expect($card->members()->where('users.id', $owner->id)->exists())->toBeFalse();
});

test('the board show page includes card members', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.members.0.id', $owner->id)
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CardMemberTest`
Expected: FAIL — route/controller don't exist, and the eager-load isn't there yet.

- [ ] **Step 3: Create the form request and controller**

`app/Http/Requests/Cards/StoreCardMemberRequest.php`:

```php
<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardMemberRequest extends FormRequest
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
        $board = $this->route('card')->boardList->board;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('board_user', 'user_id')->where('board_id', $board->id),
            ],
        ];
    }
}
```

`app/Http/Controllers/CardMemberController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardMemberRequest;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CardMemberController extends Controller
{
    public function store(StoreCardMemberRequest $request, Card $card): RedirectResponse
    {
        $card->members()->syncWithoutDetaching([$request->validated('user_id')]);

        return back();
    }

    public function destroy(Request $request, Card $card, User $user): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->members()->detach($user->id);

        return back();
    }
}
```

- [ ] **Step 4: Add the routes and update the board-show eager load**

Modify `routes/boards.php` — add the import and, at the end of the group (after the checklist-item routes):

```php
use App\Http\Controllers\CardMemberController;
```

```php
    Route::post('cards/{card}/members', [CardMemberController::class, 'store'])->name('card-members.store');
    Route::delete('cards/{card}/members/{user}', [CardMemberController::class, 'destroy'])->name('card-members.destroy');
```

Modify `app/Http/Controllers/BoardController.php` — in `show()`, add `'lists.cards.members'` to the `$board->load([...])` array, after `'lists.cards.checklists.items' => ...`:

```php
            'lists.cards.members',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=CardMemberTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Run the full suite, format, and commit the backend half**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Cards/StoreCardMemberRequest.php app/Http/Controllers/CardMemberController.php routes/boards.php app/Http/Controllers/BoardController.php tests/Feature/CardMemberTest.php
git commit -m "feat: add card member assignment"
```

- [ ] **Step 7: Add the Card TS type field and the CardMemberPicker component**

Modify `resources/js/types/index.ts` — add `members?: User[];` to the `Card` interface, alongside its existing `checklists?: Checklist[];` field.

`resources/js/components/boards/CardMemberPicker.vue`:

```vue
<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import type { Card, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

const props = defineProps<{
    card: Card;
    boardMembers: User[];
}>();

function isAssigned(user: User): boolean {
    return (props.card.members ?? []).some((member) => member.id === user.id);
}

function toggle(user: User) {
    if (isAssigned(user)) {
        router.delete(route('card-members.destroy', [props.card.id, user.id]), { preserveScroll: true });
    } else {
        router.post(route('card-members.store', props.card.id), { user_id: user.id }, { preserveScroll: true });
    }
}
</script>

<template>
    <ul class="space-y-1">
        <li
            v-for="user in boardMembers"
            :key="user.id"
            class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-2 text-sm hover:bg-accent"
            @click="toggle(user)"
        >
            <div class="flex items-center gap-2">
                <MemberAvatar :user="user" size="xs" />
                <p class="font-medium">{{ user.name }}</p>
            </div>
            <Check v-if="isAssigned(user)" class="size-4 text-primary" />
        </li>
    </ul>
</template>
```

- [ ] **Step 8: Wire members into the card detail modal**

Modify `resources/js/components/boards/CardDetailModal.vue`:

```ts
import CardMemberPicker from '@/components/boards/CardMemberPicker.vue';
```

Add a new prop:

```ts
const props = defineProps<{
    card: Card | null;
    boardMembers: User[];
}>();
```

(add `User` to the existing `import type { Card } from '@/types';` line, making it `import type { Card, User } from '@/types';`)

Add a "Members" section to the template, between the Color field's `</div>` and the `<DialogFooter>` — this is a display-only assignment list, not part of the name/description/color save form, so place it as a sibling section like the existing Checklist block (after the closing `</form>`, inside the same `border-t` pattern):

```html
            <div class="space-y-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <Label>Members</Label>
                <CardMemberPicker v-if="card" :card="card" :board-members="boardMembers" />
            </div>
```

Place this new block right after the existing `</form>` closing tag and before the existing Checklist `<div class="space-y-3 border-t ...">` block, so the layout reads Name/Description/Color/Save → Members → Checklist.

- [ ] **Step 9: Show assigned members and pass board members down from Show.vue**

Modify `resources/js/components/boards/BoardCard.vue` — add an avatar row. Add the import:

```ts
import MemberAvatar from '@/components/MemberAvatar.vue';
```

Add this block right after the `checklistSummary`/`dueDateLabel` badge `<div>` (still inside the `<button>`, after its closing `</div>`):

```html
                <div v-if="card.members?.length" class="mt-2 flex -space-x-2">
                    <MemberAvatar
                        v-for="member in card.members"
                        :key="member.id"
                        :user="member"
                        size="xs"
                        class="ring-2 ring-white dark:ring-neutral-800"
                    />
                </div>
```

Modify `resources/js/pages/boards/Show.vue` — pass board members through to the modal:

```html
<CardDetailModal v-model:open="showCardModal" :card="activeCard" :board-members="board.members ?? []" />
```

- [ ] **Step 10: Verify in the browser**

1. Open a board with at least one other board member (add one via the board Members panel from Task 5 first).
2. Open a card, scroll to "Members", click a name — confirm the checkmark appears and the dialog stays open (no page reload flicker).
3. Close the modal, confirm the card face now shows that member's avatar initials.
4. Reopen the card, click the same member again to unassign — confirm the checkmark disappears and the avatar leaves the card face after closing.
5. Refresh the page — confirm the assignment persisted.

- [ ] **Step 11: Lint and commit**

```bash
npm run lint
git add resources/js/types/index.ts resources/js/components/boards/CardMemberPicker.vue resources/js/components/boards/CardDetailModal.vue resources/js/components/boards/BoardCard.vue resources/js/pages/boards/Show.vue
git commit -m "feat: add card member assignment UI"
```
