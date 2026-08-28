# Dashboard AI Assistant (Chatbox) — Design Spec

Date: 2026-08-28

## Context

Trellow already has a board-scoped AI chat assistant (see `docs/superpowers/specs/2026-08-28-ai-board-assistant-design.md`) that can propose creating lists/cards on a single board. This spec adds a second, separate AI chatbox scoped to the Dashboard page instead — a read-only Q&A assistant that answers questions about the user's overall task progress and stats, and can point the user at a specific board by name. It shares infrastructure (Gemini, `csrfFetch`, the `aiEnabled` gating pattern) with the board assistant but is otherwise independent: different tables, different controller, no board-mutation capability at all.

## Scope decisions (from clarifying questions)

- **Action scope**: Q&A plus navigation. The AI answers questions about stats/progress in plain text and may reference a specific board by name, which the UI renders as a clickable link to that board. It has no ability to create, edit, or delete anything — there is no "propose then apply" flow like the board assistant's.
- **Data scope**: always the user's full, unfiltered data (every board across every workspace the user belongs to) — independent of whatever workspace/board filter is currently selected on the Dashboard page itself. The chat's answers stay consistent regardless of what view the user happens to have filtered to on screen.
- **Persistence**: one ongoing conversation per user (no board dimension, since the Dashboard isn't board-scoped) — same "no multi-thread UI" rule as the board assistant.
- **UI placement**: a floating chat bubble on the Dashboard page only, same visual pattern as the board assistant's widget, mounted independently (not the same Vue component instance — a new one, since the two widgets differ in payload shape and have no shared parent).
- **No function-calling / tool declarations for this feature.** Board references are done via a simple text convention (`[[Board Name]]`) parsed client-side into links, not a Gemini function call — there is nothing here that needs "confirmation before executing," so the propose-then-apply machinery the board assistant needs doesn't apply.

## Refactor: extracting `DashboardStatsService`

`DashboardController::buildReportData()` currently computes `stats`, `tasksByBoard`, and `workload` inline as a private method, scoped to whatever `$boardIds` collection is passed in (which may be filtered by workspace/board query params). This spec extracts that computation into `app/Services/DashboardStatsService.php`:

```php
public function build(Collection $boardIds): array // returns ['stats' => [...], 'tasksByBoard' => [...], 'workload' => [...]]
```

`DashboardController::buildReportData()` calls this service with its already-filtered `$boardIds` (behavior unchanged for the Dashboard page itself). The new `DashboardChatController` calls the same service with the user's **full** `$user->boardMemberships()->pluck('boards.id')` (ignoring any filter), which is how "always full data" is achieved without any special-casing — it's just a different input to the same pure function. This is a mechanical extraction with no behavior change to the existing Dashboard page; `tests/Feature/DashboardTest.php`'s existing ~20 tests must continue to pass unmodified as the regression check.

## Data model

**`dashboard_conversations`**
- `id`
- `user_id` — FK to `users`, cascade delete
- timestamps
- Unique on `user_id`

**`dashboard_messages`**
- `id`
- `dashboard_conversation_id` — FK to `dashboard_conversations`, cascade delete
- `role` — string, `user` or `assistant`
- `content` — text
- timestamps

No `tool_action` or `applied_at` columns — there is nothing to propose-and-apply in this feature.

## Backend

**`GeminiClient`** gains a second public method, `converse(string $systemInstruction, array $messages): string`, alongside the existing `reply()`. It reuses the same HTTP call machinery (timeout, header-based API key auth, `ConnectionException` → `GeminiApiException` handling) but sends no `tools` key in the request body, and simply returns the concatenated text from the first candidate's parts (throwing `GeminiApiException` on failure/empty text, same as `reply()`'s text path). `reply()` is untouched — the two methods share private helpers where it's natural (e.g. the `Http` call wrapper) but `converse()` does not go through `reply()`'s function-call parsing at all.

**`DashboardChatController`**
- `show(Request $request)` — `GET`. No board to authorize against — any authenticated user can open their own dashboard conversation. `DashboardConversation::firstOrCreate(['user_id' => $request->user()->id])`, returns `{messages: DashboardMessage[], boards: {id, name}[]}` — the `boards` list (every board the user belongs to) is included so the frontend can resolve `[[Board Name]]` markers into links without a second request.
- `sendMessage(StoreDashboardMessageRequest $request)` — `POST`, body `{content: string}`, `content` max 2000 chars (same rule as the board assistant). Stores the user message, builds the system instruction from `DashboardStatsService::build($user->boardMemberships()->pluck('boards.id'))` plus the board name list, calls `GeminiClient::converse()`, stores the assistant reply. On `GeminiApiException`, same shape as the board assistant: the user message is kept, a friendly error is returned without persisting a bogus assistant row.
- Rate limited the same way: `throttle:20,1` on the send route only.

**Routes** (added to `routes/web.php`, alongside the existing `dashboard`/`dashboard/report` routes):
- `GET dashboard/ai/conversation` → `dashboard-chat.show`
- `POST dashboard/ai/messages` → `dashboard-chat.messages.store`

**`DashboardController::index()`** gains an `aiEnabled` prop, computed identically to the board page's (`filled(config('services.gemini.key'))`).

## Frontend

**`DashboardChatWidget.vue`** (new, mounted only in `Dashboard.vue`) — same floating-bubble-then-panel structure as `AiChatWidget.vue`: a bottom-right button, lazy-loads on first open via `dashboard-chat.show`, message list (user/assistant bubbles), a composer with the same `maxlength="2000"` and disabled-while-loading behavior, and the same try/catch + `error.value` + restore-draft-on-failure pattern the board assistant's fix wave already established. The one new rendering concern: assistant message content is scanned for `[[Board Name]]` patterns; each match found in the `boards` list (fetched once via `show`) is rendered as an inline `<Link :href="route('boards.show', board.id)">Board Name</Link>` instead of literal text; any `[[...]]` that doesn't match a known board name is rendered as plain text with the brackets stripped (never a broken/dead link).

**`Dashboard.vue`** gains an `aiEnabled: boolean` prop and mounts `<DashboardChatWidget :ai-enabled="aiEnabled" />` alongside its existing content.

`csrfFetch.ts` is reused unchanged.

## Error handling

Same three cases as the board assistant, applied identically here:
- Missing API key → `aiEnabled: false`, widget shows "AI chat isn't configured yet," no request ever sent.
- `GeminiApiException` (Gemini failure, including network/timeout via the already-hardened `ConnectionException` handling) → user's message is kept, friendly inline error, no bogus assistant row.
- Network/JSON failure in the widget's own fetch calls → caught, `error.value` set, draft restored on `send()` failure — same as the board assistant's fix wave.

## Testing

Pest feature tests covering:
- `DashboardStatsService::build()` extraction: existing `DashboardTest.php` suite passes unmodified (regression check that the refactor didn't change the Dashboard page's behavior).
- Conversation is created lazily on first `show`, unique per user (two different users get two different conversations).
- `sendMessage` stores the user message and, with `Http::fake()` simulating a Gemini text reply, stores the assistant reply.
- A Gemini HTTP failure does not create a bogus assistant message and returns a friendly error, matching the board assistant's equivalent test.
- The stats/board data passed to `GeminiClient::converse()` reflects the user's **full** board set even when called from a request that doesn't scope to any particular workspace/board (there's nothing to filter by here in the first place, since the endpoint takes no filter params at all).
- `DashboardController::index()` passes `aiEnabled` correctly based on config, mirroring the board page's existing test.
- `GeminiClient::converse()` unit tests (in `tests/Unit/GeminiClientTest.php`, alongside the existing `reply()` tests): returns concatenated text on success, throws `GeminiApiException` on HTTP failure/`ConnectionException`/empty response — no function-call parsing path exists for this method, so no tool-related tests apply.

## Out of scope (this iteration)

- Any create/edit/delete capability from the dashboard chat — it is Q&A + navigation only.
- Respecting the Dashboard page's current workspace/board filter in the AI's context (always full data, per the scope decision above).
- Linking to a specific card (only board-level links via `[[Board Name]]`).
- Multiple conversation threads, renaming, or deleting a conversation.
- Streaming responses.
