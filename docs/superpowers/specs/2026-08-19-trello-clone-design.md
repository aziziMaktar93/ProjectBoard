# Trello Clone — Design Spec

Date: 2026-08-19

## Context

`trellow` is a fresh Laravel 12 + Inertia v2 + Vue 3 + shadcn/Tailwind starter kit with auth scaffolding (login/register, Dashboard, Settings) already in place, but no Trello-like features yet.

A reference implementation exists at `C:\laragon\www\trello-clone-vue-laravel-master` (Laravel 5 + Vue 2 + Passport). It is intentionally minimal: a single global board shared by all users, with `Category` (list) and `Task` (card) models, drag-and-drop reordering via `vuedraggable`, no descriptions/labels/due dates/comments, and hard soft-deletes with no archive UI. It is useful only as a rough conceptual reference, not a pattern to copy directly — the new build uses the current stack's own conventions (Inertia pages, Eloquent policies, Pest tests).

## Scope decisions

- **Multi-board per user**: each user owns any number of boards. No cross-user sharing/invites in this iteration.
- **Card fields**: name + description only. No due dates, labels, or comments in this iteration.
- **Delete workflow**: Trello-style archive-then-permanently-delete, at all three levels (board, list, card). Nothing is hard-deleted without first being archived.

## Data model

Three tables, each scoped to the owning user via its board.

**`boards`**
- `id`
- `user_id` — FK to `users`, owner
- `name` — string
- `background_color` — string, nullable (hex or a preset key), sensible default
- `archived_at` — nullable timestamp
- timestamps

**`board_lists`** (named to avoid the `lists` reserved-word clash in some DBs)
- `id`
- `board_id` — FK to `boards`
- `name` — string
- `position` — integer
- `archived_at` — nullable timestamp
- timestamps

**`cards`**
- `id`
- `board_list_id` — FK to `board_lists`
- `name` — string
- `description` — text, nullable
- `position` — integer
- `archived_at` — nullable timestamp
- timestamps

### Ordering

`position` is a plain integer, re-indexed on every reorder rather than using fractional/gap positions. On drag-drop completion the frontend sends the full ordered list of IDs (and, for cards, the target `board_list_id` when moving across lists) to a reorder endpoint; the backend re-assigns sequential `position` values to every affected row in a single transaction. Moving a card between lists updates both the source and destination list's card positions in one request.

## Authorization

A `BoardPolicy` (plus implicit authorization for `board_lists`/`cards` via their parent board) restricts every action to the board's owning user. Lists and cards are only ever reachable through their parent board, so authorization is checked once at the board level for nested resources.

## Backend routes/controllers

All under `auth` middleware.

- `BoardController` — `index`, `store`, `show` (eager-loads non-archived lists → non-archived cards, ordered by `position`), `update`, `archive`, `restore`, `destroy` (permanent; only callable on an already-archived board)
- `BoardListController`, nested under `/boards/{board}/lists` — `store`, `update`, `reorder`, `archive`, `restore`, `destroy`
- `CardController`, nested under `/lists/{list}/cards` — `store`, `update`, `reorder` (handles same-list and cross-list moves), `archive`, `restore`, `destroy`

Archived items are excluded from the normal `show`/`index` queries and surfaced only through dedicated archive views/endpoints.

## Frontend

### Pages (`resources/js/pages/`)

- `Boards/Index.vue` — grid of the user's boards, a "New board" card, and a link to archived boards
- `Boards/Show.vue` — the board view: horizontally-scrolling lists, each a vertical stack of cards; "Add list" and "Add card" affordances
- `Boards/Archived.vue` — archived boards with restore / delete-permanently actions

### Components (`resources/js/components/boards/`)

- `BoardListColumn.vue` — one list/column; renders its cards and hosts the card-level `vue-draggable-plus` instance
- `BoardCard.vue` — a single card (name + truncated description preview)
- `CardDetailModal.vue` — opens on card click; edit name/description, archive, or delete
- `ArchivePanel.vue` — slide-over within a board showing that board's archived lists and cards, with restore/delete-permanently actions

### Drag-and-drop

Add `vue-draggable-plus` (SortableJS wrapper, Vue 3 native) as a new dependency, used for both list reordering (within a board) and card reordering (within and across lists). On drop, the UI updates optimistically; a lightweight request (not a full Inertia page visit) is sent to the relevant reorder endpoint. On failure, the UI rolls back to the last known-good order and shows an error toast.

## Testing

Pest feature tests, one suite per controller area:
- CRUD happy paths for boards, lists, and cards
- Archive → restore → permanent-delete lifecycle for each level
- Authorization: a second user cannot view, modify, archive, or delete another user's boards/lists/cards
- Reorder endpoints: same-list reorder, and cross-list card moves, verifying resulting `position` values

## Out of scope (this iteration)

- Sharing boards / inviting members
- Labels, due dates, comments, attachments
- Real-time sync across tabs/devices (no websockets/Echo) — not needed since boards aren't shared
