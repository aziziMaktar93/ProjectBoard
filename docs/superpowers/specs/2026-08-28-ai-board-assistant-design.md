# AI Board Assistant (Chatbox) — Design Spec

Date: 2026-08-28

## Context

Trellow has no AI features today. This spec adds a chat assistant scoped to a single board that helps a user brainstorm and plan board structure (lists, cards) directly from a conversation, and can propose concrete changes the user applies with one click — it never mutates the board on its own.

## Scope decisions (from clarifying questions)

- **Purpose**: board/card assistant, not a generic chatbot. It knows the current board's lists and card counts and is meant for planning ("suggest lists for a redesign project", "break this down into tasks"), not open-ended conversation unrelated to the board.
- **Provider**: Google Gemini (free tier via Google AI Studio — no card required). Model is configurable via env, default `gemini-2.0-flash`.
- **Action model**: propose-then-apply. The AI can respond with a structured proposed action (e.g., "create these 3 lists"), rendered as a summary card with an **"Add to board"** button. Clicking it is what actually creates the lists/cards, via the same authorization and validation paths as the existing manual create endpoints. The AI itself never writes to the board.
- **Tool scope (v1)**: exactly two tools — `create_lists` and `create_cards`. Checklist-item generation is a natural fast-follow but out of scope now, to keep the tool surface small and fully testable.
- **Permissions**: any board member (viewer or editor) can chat/brainstorm — it's read-only until an action is applied. Applying a proposed action (`create_lists`/`create_cards`) requires edit rights, matching the existing `BoardPolicy::update` gate used everywhere else on the board. A non-member of the board cannot use the chat at all.
- **Persistence**: chat history is stored in the database, one ongoing conversation per (board, user) pair — no multi-thread UI. Reopening the board later resumes the same conversation.
- **UI placement**: a floating chat bubble in the bottom-right corner of the board page (`boards/Show.vue`) only — not a global/app-wide widget. Opening it lazy-loads the conversation; it isn't part of the board page's initial Inertia payload.
- **Graceful degradation**: if `GEMINI_API_KEY` isn't configured, the widget renders a "AI chat isn't configured yet" state instead of a broken button, and the rest of the app is unaffected.

## Data model

**`ai_conversations`**
- `id`
- `board_id` — FK to `boards`, cascade delete
- `user_id` — FK to `users`, cascade delete
- timestamps
- Unique on (`board_id`, `user_id`)

**`ai_messages`**
- `id`
- `ai_conversation_id` — FK to `ai_conversations`, cascade delete
- `role` — string, `user` or `assistant`
- `content` — text; for assistant messages this is the human-readable reply (including a short natural-language summary of any proposed action)
- `tool_action` — nullable JSON; shape depends on `type`:
  - `{"type": "create_lists", "names": ["Research", "Design", "Development"]}`
  - `{"type": "create_cards", "list_name": "Research", "card_names": ["Competitor audit", "User interviews"]}`
- `applied_at` — nullable timestamp; set when the user clicks "Add to board" for that message
- timestamps

A message can carry at most one `tool_action`. If Gemini's reply contains only text (no function call), `tool_action` is null and there is nothing to apply.

## Backend

**`GeminiClient`** (`app/Services/GeminiClient.php`)
- Wraps HTTP calls to the Gemini `generateContent` endpoint via Laravel's `Http` facade, using `config('services.gemini.key')` and `config('services.gemini.model')`.
- Builds the request from: a fixed system instruction describing the assistant's purpose and the two available tools (function declarations), the conversation's prior messages (mapped to Gemini's `contents` format), the new user message, and a compact board-context string (board name + each active list's name and card count — not full card bodies, to keep the prompt small).
- Parses the response into either `{'role' => 'assistant', 'content' => string, 'tool_action' => null}` or, when Gemini returns a function call, `{'role' => 'assistant', 'content' => <short generated summary>, 'tool_action' => [...]}`.
- Throws a dedicated `GeminiApiException` on HTTP failure/timeout/rate-limit, caught by the controller.

**`AiChatController`**
- `show(Board $board)` — `GET`. `Gate::authorize('view', $board)`. Finds-or-creates the (board, user) conversation, returns its messages.
- `sendMessage(StoreAiMessageRequest $request, Board $board)` — `POST`, body `{ content: string }`. `Gate::authorize('view', $board)` (any member, viewer included). Stores the user message, calls `GeminiClient`, stores the assistant reply (catching `GeminiApiException` and instead returning a friendly inline error without persisting a bogus assistant row), returns the new message(s).
- `applyAction(Board $board, AiMessage $message)` — `POST`. `Gate::authorize('update', $board)` (editors only). Validates the message belongs to the current user's conversation on this board and `applied_at` is still null. Executes the stored `tool_action` by calling the same model-layer logic the existing `BoardListController::store` / `CardController::store` use (position-at-end, etc.), then sets `applied_at`. Returns the updated board lists. For `create_cards`, `list_name` is matched against the board's active lists case-insensitively; if no match is found (e.g. the AI referenced a list name that doesn't exist), the whole action fails with a 422 and a clear error rather than partially applying or guessing a list.

**Routes** (`routes/boards.php`, under the existing `auth` + `verified` group):
- `GET boards/{board}/ai/conversation` → `AiChatController::show`
- `POST boards/{board}/ai/messages` → `AiChatController::sendMessage`
- `POST boards/{board}/ai/messages/{message}/apply` → `AiChatController::applyAction`

**Config**: `config/services.php` gains a `gemini` entry (`key`, `model`); `.env.example` documents `GEMINI_API_KEY` and `GEMINI_MODEL` (default `gemini-2.0-flash`).

## Frontend

**`AiChatWidget.vue`** (new, mounted only in `boards/Show.vue`)
- A fixed floating button, bottom-right, visible to any board member. Clicking it opens a panel and (first time) fetches `GET boards/{board}/ai/conversation`.
- Message list: user messages right-aligned, assistant left-aligned. An assistant message with a `tool_action` renders as a small card summarizing the proposal (e.g., "Create lists: Research, Design, Development") plus an **"Add to board"** button.
- The "Add to board" button is hidden (or shown disabled with a tooltip) when `!canEdit`, and hidden once `applied_at` is already set (shows "Added" instead).
- Sending a message: append it optimistically, `POST` to `boards/{board}/ai/messages`, append the returned assistant message (or inline error) on response; a loading indicator while waiting.
- Applying an action: `POST` to the apply endpoint, then re-fetch/patch the board's `lists` prop (via `router.reload({ only: ['board'] })`) so the newly created lists/cards appear without a full page reload.
- If the board's `aiEnabled` flag (passed from `BoardController::show`, `true` only when `GEMINI_API_KEY` is configured) is false, the button still shows but the panel displays "AI chat isn't configured yet" instead of a composer.

## Error handling

- Missing API key → `aiEnabled: false` prop, widget shows the "not configured" state, no requests are ever sent.
- Gemini API failure (network/timeout/rate-limit) → `AiChatController::sendMessage` catches `GeminiApiException`, still persists the user's message, returns an error payload; the widget shows an inline "AI couldn't respond, try again" message in the thread without a fake assistant bubble.
- Applying an action twice (double-click, stale UI) → `applyAction` checks `applied_at` is still null and 422s if already applied; frontend disables the button immediately on click to avoid the race in the common case.

## Testing

Pest feature tests covering:
- Conversation is created lazily on first `show`, and is unique per (board, user) — a second board member gets a separate conversation.
- A non-member of the board cannot view or send messages (403).
- `sendMessage` stores the user message and, with `Http::fake()` simulating a Gemini text reply, stores a matching assistant message with `tool_action` null.
- `sendMessage` with `Http::fake()` simulating a Gemini function-call reply stores an assistant message with the correct `tool_action` JSON.
- A Gemini HTTP failure (`Http::fake()` returning a 500) does not create a bogus assistant message and returns a friendly error.
- `applyAction` for `create_lists` actually creates the lists on the board, sets `applied_at`, and is blocked (403) for a viewer.
- `applyAction` for `create_cards` creates cards under the named list.
- `applyAction` for `create_cards` with a `list_name` that doesn't match any active list on the board fails with a 422 and creates nothing.
- `applyAction` is rejected (422) if the message was already applied.
- `BoardController::show` passes `aiEnabled: false` when `GEMINI_API_KEY` is unset in config, `true` when set.

## Out of scope (this iteration)

- Checklist-item generation (`create_checklist` tool).
- Multiple conversation threads per board, or renaming/deleting a conversation.
- Any AI feature outside the single board page (no global assistant, no workspace-level assistant).
- Streaming responses (SSE/websocket) — replies are returned as a single response once Gemini finishes.
- Any provider other than Gemini (no user-facing provider switcher).
