# HOD Role — Design Spec

## Purpose

Restrict who can change due dates (card and checklist item) to a new "HOD" board role, instead of any editor.

## Scope

- Board roles become three tiers: `viewer`, `editor`, `hod`. No schema change — `board_user.role` is already a plain string column with no DB-level enum constraint.
- `BoardPolicy::manageDueDates(User $user, Board $board): bool` — true if `$user->id === $board->user_id` (owner) or the member's pivot `role === 'hod'`.
- `UpdateCardRequest` and `UpdateChecklistItemRequest`: `authorize()` still requires the general `update` (editor-tier) permission for the request as a whole, and additionally requires `manageDueDates` when the request payload includes a `due_date` key. Validation rules for `due_date` are unchanged.
- Assigning the `hod` role to a member is owner-only. `UpdateBoardMemberRequest::authorize()`: if the incoming `role` is `hod`, require `$this->user()->id === $this->route('board')->user_id`; otherwise keep the existing `can('update', $board)` check (unchanged for `editor`/`viewer`). `StoreBoardMemberRequest` (adding a new member) keeps its current default (`editor`) and existing rule; `hod` is not settable at add-time, only via a follow-up role update (matches how the UI already works — add first, then use the role dropdown to change it).
- `BoardController::show()` gains a `canManageDueDates` boolean prop (via `$request->user()->can('manageDueDates', $board)`), passed down to `CardDetailModal` and `CardChecklist`.
- `CardDetailModal.vue`'s "Dates" popover and `CardChecklist.vue`'s per-item due-date popover are gated by the new `canManageDueDates` prop instead of `canEdit`. Everything else in both components keeps using `canEdit` unchanged.
- `BoardMemberPanel.vue`'s role `<Select>` gets a third `SelectItem` for "HOD"; that specific option is only rendered when the viewing user is the board owner (`currentUserId === board.user_id`) — a non-owner editor can still change roles between `editor`/`viewer` as today, just can't see or pick HOD.
- `User['pivot']['role']` type widens from `'editor' | 'viewer'` to `'editor' | 'viewer' | 'hod'`.

## Explicit behavior change

Editors who are not HOD lose the ability to set/clear due dates on cards and checklist items — previously any editor could. This is the intended effect of the feature, not a regression to guard against.

## Testing

- `BoardPolicy`/feature tests: an HOD member can update a card's/checklist item's due date; a plain editor gets 403 attempting the same; the board owner can always do it regardless of role; a viewer still can't (already blocked by the general `update` check).
- A non-owner editor attempting to set a member's role to `hod` gets 403; the owner can.
- Existing editor/viewer role-management tests must keep passing unchanged.

## Global Constraints

- PHP: curly braces always, explicit return types.
- Pest tests required for the new policy method and the two authorize() changes.
- `vendor/bin/pint --dirty --format agent` after PHP changes.
- Vue: single root element, `<script setup lang="ts">`, follow existing `canEdit`-gating patterns in the touched files.
