# Board Chat — Design Spec

## Purpose

Add a shared, board-scoped chat so members of a board can talk to each other without leaving the board page. This is phase 1 of a larger idea (direct messages between users is a separate, later project); this spec covers only board-level group chat.

## Scope

- One shared chat "room" per board — every board member (editor and viewer) can read and post.
- Polling-based (no WebSocket/Reverb) — consistent with the existing AI Board Assistant and AI Dashboard Assistant widgets, which already use plain `fetch` + `setInterval`/on-demand polling rather than real-time push.
- Plain text messages with `@Name` mentions that trigger the existing notification system.
- A user can delete their own messages. No editing.
- Floating widget UI on the board page, next to the existing AI Board Assistant widget.
- Unread badge on the widget icon while it's closed. No cross-board/sidebar-wide unread indicator — that's out of scope.

Out of scope: direct messages (1-to-1), real-time delivery, message editing, attachments/rich text, read receipts per member, message search.

## Data Model

**`BoardMessage`** (new model/table `board_messages`):
- `board_id` (FK, cascade delete with board)
- `user_id` (FK)
- `content` (text)
- timestamps

No separate "conversation" wrapper model — unlike `AiConversation` (which is keyed per board+user because each user gets their own AI thread) or `DashboardConversation` (keyed per user), this chat is a single shared thread per board, so `Board hasMany BoardMessage` is sufficient.

**`board_user` pivot** — add nullable `chat_last_read_at` timestamp column, alongside the existing `role` and `is_favourite` columns. Updated to `now()` whenever the member fetches the message list (i.e., opens the widget).

Messages are hard-deleted (no soft delete) when a user deletes their own message — a deleted chat message is meant to disappear, not be archived.

## API

Added to `routes/boards.php`, following the existing `ai-chat.*` route conventions:

- `GET boards/{board}/chat/messages` — `Gate::authorize('view', $board)`. Returns the full message list (oldest first) with each message's author (`id`, `name`). As a side effect, updates the requesting user's `chat_last_read_at` pivot value to `now()`.
- `POST boards/{board}/chat/messages` — `Gate::authorize('view', $board)` (viewers can post — chat is not a board-editing action), `throttle:30,1`. Validates `content` (`required|string|max:2000`). Creates the message, then scans for `@MemberName` mentions using the same substring-match approach as `CardActivityController::notifyMentionedMembers()`, creating a `Notification` of type `board_message_mention` for each mentioned member (excluding the author).
- `DELETE boards/{board}/chat/messages/{message}` — `abort_unless($message->board_id === $board->id, 404)`, `abort_unless($message->user_id === $request->user()->id, 403)`. Hard-deletes the message.

No separate unread-count endpoint — the unread badge is derived entirely from the message list response (see Frontend below), keeping this feature self-contained to the board page rather than a site-wide notification surface.

## Notifications

Add `board_message_mention` to the `AppNotification['type']` union in `resources/js/types/index.ts`. Data shape: `{ board_id: number, board_name: string, actor_name: string, message_preview: string }` — deliberately different from the existing `mention` type's `{ card_id, card_name, board_id, actor_name, item_name? }`, since a chat mention isn't tied to a card.

`Notifications.vue` and `NotificationBell.vue` get a new render case for `board_message_mention`: shows `{actor_name} mentioned you in {board_name}`, links to `route('boards.show', board_id)`.

## Frontend

**`resources/js/components/boards/BoardChatWidget.vue`** — mirrors the structure of `AiChatWidget.vue`:
- Rendered alongside `AiChatWidget.vue` on `boards/Show.vue`, positioned so both floating buttons are visible without overlapping (e.g. stacked with a gap, chat button above/beside the AI button).
- Props: `boardId: number`, `members: { id: number; name: string }[]` (from `board.members`, already loaded on the page).
- `open` (boolean), `messages` (ref array), `draft` (string), `loading`, `error` — same shape as `AiChatWidget.vue`'s local state.
- **Closed-state polling:** every 15s, fetch the message list in the background (same `GET` endpoint — simplest option since there's no separate unread-count endpoint) and compare the newest message's timestamp against a locally-cached "last seen" value to compute unread count for the badge. This poll is skipped entirely if `open` is true (the open-state poll below covers it).
- **Open-state polling:** every 5s while `open`, re-fetch the message list and merge in new messages (append any not already present, keyed by `id`), then scroll to bottom if the user was already at the bottom. Interval is cleared when `open` becomes false or the component unmounts.
- Opening the widget calls the `GET` endpoint immediately (marks `chat_last_read_at` server-side) and clears the unread badge.
- Sending: `POST` with `{ content: draft }`; append the returned message to the local list optimistically-confirmed (i.e., append from the response, not before).
- `@` mention helper: as the user types `@`, filter `members` by name prefix and show a small inline dropdown list below the textarea; selecting one inserts `@FullName ` into the draft. This is a simple filtered list, not a rich mention-picker component.
- Rendering a message: mentions (`@Name` where `Name` matches a board member) are highlighted via a regex-based split-and-wrap into a `<span>`, the same technique `DashboardChatWidget.vue` uses for `[[Board Name]]` links.
- Delete: a small button shown only on the current user's own messages; clicking asks for confirmation (native `confirm()`, consistent with other destructive one-off actions in this codebase) then calls `DELETE` and removes the message from the local list on success.

## Authorization

- Viewing and posting: `Gate::authorize('view', $board)` — matches the existing AI chat's permission level, since board viewers are allowed to participate in discussion even though they can't edit board content.
- Deleting: ownership check only (`user_id` match), independent of board role — a viewer can delete their own chat message even though they can't edit the board.

## Error Handling

- Network/API failures during polling fail silently (no error banner) since these are background refreshes — surfacing every polling failure would be noisy. Only the send and delete actions surface an inline error message on failure, matching `AiChatWidget.vue`'s pattern.
- Sending an empty/whitespace-only message is a no-op client-side (`content.trim()` check before firing the request), and also rejected server-side by the `required` validation rule.

## Testing

Feature tests (new `tests/Feature/BoardChatTest.php`):
- A board member (editor) can list and post messages.
- A board viewer can list and post messages (viewer role is not blocked).
- A non-member cannot list or post messages (403).
- Posting a message with `@MemberName` creates a `board_message_mention` notification for that member, not for the author, and not for members not mentioned.
- A user can delete their own message; a different member (even the board owner/editor) cannot delete someone else's message (403).
- Fetching the message list updates the requesting user's `chat_last_read_at` pivot value.

## Global Constraints (carried into the plan)

- Follow `routes/boards.php`'s existing route-naming and controller-per-resource conventions.
- `Gate::authorize('view', $board)` for read/post, ownership check for delete — no new policy method needed.
- No new PHP dependencies. No WebSocket/broadcasting infrastructure.
- PHP: curly braces always, constructor property promotion, explicit return types, `casts()` method not `$casts` property.
- Every task needs a Pest test; run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Vue: single root element per component, `<script setup lang="ts">`, follow `AiChatWidget.vue`'s structural conventions closely since this widget is deliberately its sibling.
