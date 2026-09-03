# Checklist Item Completed Date — Design Spec

## Purpose

Show when a checklist item was actually completed, distinct from its due date. Currently `CardChecklist.vue` shows a due-date badge on every item that has one, regardless of checked state — there's no record of when the item was actually finished, so a completed item's badge still just shows its original deadline.

## Scope

- Add a `completed_at` (nullable timestamp) column to `checklist_items`.
- Server-managed only: `ChecklistItemController::update()` sets `completed_at = now()` whenever `is_checked` transitions to `true`, and clears it to `null` whenever it transitions to `false`. The client never sends `completed_at` directly.
- UI: a checked item shows both badges side by side — the existing due-date badge (unchanged styling/logic) and a new completed-date badge (green, label "Done {date}", e.g. "Done Aug 31"), only rendered when `completed_at` is set.
- An unchecked item's rendering is unchanged (due-date badge only, red if overdue).

Out of scope: editing/backdating the completed date manually, showing time-of-day (date only, same format as the due-date badge), any change to `isItemOverdue()`'s logic.

## Data Model

`ChecklistItem` gains `completed_at` (nullable `timestamp`), fillable, cast to `datetime`.

## Backend

In `ChecklistItemController::update()`, alongside the existing `CardActivity` logging block that checks `is_checked` transitions, set `$validated['completed_at']` before the `$checklistItem->update($validated)` call:

```php
if (array_key_exists('is_checked', $validated) && $validated['is_checked'] !== $checklistItem->is_checked) {
    $validated['completed_at'] = $validated['is_checked'] ? now() : null;
    // ...existing CardActivity::create(...) block unchanged
}
```

## Frontend

- `resources/js/types/index.ts`: `ChecklistItem` interface gains `completed_at: string | null`.
- `resources/js/components/boards/CardChecklist.vue`: add an `itemCompletedDateLabel(item)` helper mirroring `itemDueDateLabel(item)`'s date formatting, and render a second badge (green: `bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400`) right after the existing due-date badge, `v-if="item.completed_at"`, showing `Done {formatted date}`.

## Testing

Feature test additions to the existing checklist item test file: checking an item sets `completed_at` to a non-null recent timestamp; unchecking a previously-checked item clears it back to `null`; re-checking sets a fresh timestamp; toggling an already-checked item to checked again (no actual transition) doesn't touch `completed_at`.

## Global Constraints

- PHP: curly braces always, explicit return types, `casts()` method.
- Pest tests required.
- `vendor/bin/pint --dirty --format agent` after PHP changes — but this repo currently has unrelated uncommitted changes to `app/Http/Controllers/DashboardChatController.php`, `app/Services/DashboardStatsService.php`, `app/Services/GeminiClient.php`, `tests/Feature/DashboardChatTest.php`, `tests/Feature/DashboardStatsServiceTest.php`... **(note: as of this spec being written, those five files were already committed in an earlier step of this session — verify current `git status` before implementing; if clean, a normal `--dirty` run is fine).**
- Vue: single root element, `<script setup lang="ts">`.
