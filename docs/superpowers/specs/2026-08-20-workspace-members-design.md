# Workspaces, Board Members & Card Members — Design Spec

Date: 2026-08-20

## Context

Trellow currently has a single-tenant board model: every board belongs to exactly one user (`boards.user_id`), and only that user can see or act on it (`BoardPolicy` checks `$user->id === $board->user_id`). This spec adds real multi-user collaboration, matching Trello's three-tier membership model shown in the reference screenshot: **Workspace members** → **Board members** (a subset of workspace members) → **Card members** (a subset of board members).

This is a foundational change: every board must now belong to a Workspace, and board access is authorized via board membership rather than a single owner field.

## Scope decisions (from clarifying questions)

- **Multiple workspaces per user**: a user can create and belong to any number of workspaces (not one fixed workspace), matching real Trello. Needs a workspace switcher/list in the UI.
- **Adding members**: no mail/queue infrastructure exists in this app. Members are added by searching **existing registered users** (by name/email) and adding them immediately — no email invite, no accept/reject step.
- **Existing data migration**: every existing user gets an auto-created "Personal Workspace" (name: `"{User's name}'s Workspace"`), and all of their existing boards are moved into it, with the user added as both workspace member and board member. New users get the same auto-created workspace on registration.

## Data model

**`workspaces`**
- `id`
- `owner_id` — FK to `users`, the creator; only the owner can manage workspace membership or delete the workspace
- `name` — string
- timestamps

**`workspace_user`** (pivot, no role column — all members are equal except the owner)
- `id`, `workspace_id`, `user_id`, timestamps
- Unique on (`workspace_id`, `user_id`)

**`boards`** — modified
- add `workspace_id` — FK to `workspaces`, NOT NULL, cascade delete (deleting a workspace deletes its boards)
- `user_id` stays as-is, now meaning "board creator" (used to gate permanent delete)

**`board_user`** (pivot — board membership, a subset of the board's workspace membership)
- `id`, `board_id`, `user_id`, timestamps
- Unique on (`board_id`, `user_id`)

**`card_user`** (pivot — card assignees, a subset of the card's board membership)
- `id`, `card_id`, `user_id`, timestamps
- Unique on (`card_id`, `user_id`)

## Authorization

**`WorkspacePolicy`** (new)
- `view`: user is a member of the workspace (`workspace_user` row exists)
- `update` (rename), `delete`, `manageMembers`: user is the workspace `owner_id`
- **Removing a workspace member**: the owner can remove any member except themselves. A non-owner member can remove themselves (leave). The owner cannot leave — they must delete the workspace instead.

**`BoardPolicy`** (rewritten)
- `view`, `update` (rename/color/archive/restore): user is a member of the board (`board_user` row exists) — replaces the old `user_id === $board->user_id` check everywhere it's currently used
- `delete` (permanent destroy): user is the board's creator (`board->user_id`)
- Creating a board requires the user to be a member of the target workspace
- **Managing board members**: any board member can add another workspace member to the board, or remove any other board member (including themselves — leaving). The board's creator can never be removed, by themselves or anyone else, so a board can never end up without its original owner.
- **Managing card members**: any board member can assign or unassign any board member on a card — no extra restriction beyond board membership.

**Card/list/checklist policies**: unchanged in shape — they already delegate to `BoardPolicy` via `$card->boardList->board` etc.; since `BoardPolicy::update` now means "is a board member" instead of "is the owner", this correctly extends membership-based access down through lists, cards, and checklists with no further changes needed to those policies.

**Card member assignment**: assigning a user to a card requires that user to already be a board member (validated server-side — reject assigning a non-board-member).

## Backend routes

All under `auth` middleware, alongside the existing `boards.*` group.

- `workspaces.index` — `GET /workspaces` — the user's workspaces
- `workspaces.store` — `POST /workspaces`
- `workspaces.show` — `GET /workspaces/{workspace}` — that workspace's active boards + its member list
- `workspaces.update` — `PATCH /workspaces/{workspace}` — rename
- `workspaces.destroy` — `DELETE /workspaces/{workspace}` — owner only, cascades to boards/lists/cards/checklists
- `workspace-members.store` — `POST /workspaces/{workspace}/members` — body `{ email }`, adds an existing user
- `workspace-members.destroy` — `DELETE /workspaces/{workspace}/members/{user}` — owner can remove anyone but themselves; a non-owner member can remove only themselves (leave)
- `workspaces.boards.store` — `POST /workspaces/{workspace}/boards` — create a board in the workspace (creator is auto-added as the sole initial board member)
- `boards.archived` — becomes `GET /workspaces/{workspace}/boards/archived` (was global `/boards/archived`)
- `board-members.store` — `POST /boards/{board}/members` — body `{ user_id }`, must already be a member of the board's workspace
- `board-members.destroy` — `DELETE /boards/{board}/members/{user}` — any board member can remove any other member, including themselves (leave); the board's creator can never be removed
- `card-members.store` — `POST /cards/{card}/members` — body `{ user_id }`, must already be a board member
- `card-members.destroy` — `DELETE /cards/{card}/members/{user}`

Existing `boards.show/update/archive/restore/destroy`, `board-lists.*`, `cards.*` (excluding the old `cards.store`'s validation, which is unaffected), `checklists.*`, `checklist-items.*` routes are unchanged in shape; only their underlying authorization now resolves through board membership.

## Frontend

**Navigation**: sidebar "Boards" link becomes **"Workspaces"**, pointing at `/workspaces`.

**Pages**:
- `Workspaces/Index.vue` — grid of the user's workspaces (name, member count), "New workspace" dialog
- `Workspaces/Show.vue` — replaces the old global `Boards/Index.vue`: this workspace's active boards (same tile grid as before) + "New board" + a "Members" panel (search existing users by name/email, add; list current members with remove `×`, owner marked, owner cannot be removed) + link to this workspace's archived boards + rename/delete (owner only, via a "..." menu matching the board-level pattern)
- `Workspaces/Archived.vue` — replaces the old global `Boards/Archived.vue`, scoped per workspace

**Board Show page** (`boards/Show.vue`): add a "Members" button next to "View archive", opening a panel (reusing the same search-existing-user-then-add pattern) restricted to the board's workspace members; each listed board member has a remove `×` except the board creator.

**Card detail modal**: add a "Members" section — click to toggle assignment from the board's member list (small avatar picker, checkmark on assigned members), reusing `useInitials.ts` / the existing `Avatar` UI component for circular initials.

**Board card face** (`BoardCard.vue`): show a small stack of circular initials avatars for assigned members, alongside the existing due-date/checklist badges.

## Testing

Pest feature tests covering:
- Workspace CRUD, membership add/remove, owner-only guards, and the "owner cannot be removed/cannot leave" rule
- Board creation requires workspace membership; board membership add/remove, "creator cannot be removed" rule, "user must already be a workspace member to be added to a board"
- Card member assignment requires board membership; assign/unassign
- Authorization regression: a user who is a workspace member but NOT a board member cannot view/act on that board (and vice versa — removing someone from a board doesn't remove them from the workspace)
- The existing-user migration: confirm every pre-existing user gets exactly one auto-created workspace containing exactly their pre-existing boards, and is a member of both

## Out of scope (this iteration)

- Roles/permission levels beyond "owner" vs "member" (no Trello-style Admin/Normal/Observer tiers)
- Email invites / accept-reject flow (adding a member is immediate, no notification)
- Board visibility settings (private vs. workspace-visible) — every board is invite-only via explicit board membership
- Transferring workspace ownership
