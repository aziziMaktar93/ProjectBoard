# ProjectBoard

A self-hosted, Trello-style project management app built with Laravel, Inertia, and Vue. Organize work into workspaces, boards, lists, and cards, collaborate with your team, and track progress from a dashboard, five exportable reports, and two AI assistants.

📄 **[Full system overview with screenshots (PDF)](docs/ProjectBoard-System-Overview-EN.pdf)**

## Features

**Workspaces & boards**
- Workspaces with their own members and color, each holding multiple boards
- Workspace and board tiles show a live checklist-progress bar and member avatars at a glance
- Boards with drag-and-drop lists and cards, per-item colors, and archive/restore for both lists and boards
- Duplicate a list or a single card (including its checklist) in one click
- Favourite boards and workspaces to pin them to the top of the list
- Global search across boards and workspaces

**Cards & checklists**
- Descriptions, due dates, color labels, file attachments (with an optional cover image), and card members
- Checklists with progress tracking; each item can have its own due date, assignee, and completion date (recorded automatically when checked)
- Bulk actions — archive, move, or label multiple cards at once
- Comments and an auto-generated activity feed (moves, checklist changes, member changes, archiving), including which checklist a completed item belongs to
- @mention support in comments and board chat, with notifications

**Roles & access control**
- Workspace, board, and card-level membership with role-appropriate authorization
- Three board roles: Viewer (read-only), Editor (full edit rights), and HOD (Editor rights plus the exclusive ability to set due dates) — only the board owner can promote a member to HOD
- Email verification and password reset, both sent through a queued job so the request never blocks on SMTP, plus enforced password strength requirements

**Calendar**
- Every card and checklist-item due date across a board, or all boards, in one month view

**Dashboard**
- Total/completed/overdue/due-soon task counts and aggregate checklist progress
- Tasks-by-board (or tasks-by-list when a single board is selected) and workload-per-member breakdowns
- Recent activity feed, filterable by workspace or board, downloadable as a PDF report
- A read-only AI assistant that answers questions about overall progress and links to the relevant board

**Reports**
- Five scoped, downloadable reports: Progress % (per workspace/board/card), On-Time vs Late Completion, Member Performance, Checklist Completion Timeline, and Activity Log (PDF + CSV)

**AI Board Assistant**
- A chat widget on the board page that brainstorms board structure and can propose new lists or cards — nothing is created until an editor explicitly approves it

**Board Chat**
- A shared group chat per board, open to every member regardless of role, with @mention notifications and an unread badge

**Notifications**
- Assignment and mention notifications across every board, searchable and filterable by read status

**Look & feel**
- Light/dark mode plus a handful of accent color themes, each with a matching gradient treatment across tiles, avatars, and the sidebar

## Tech stack

- [Laravel 12](https://laravel.com) (PHP 8.2+)
- [Inertia.js v2](https://inertiajs.com) with [Vue 3](https://vuejs.org) (Composition API, TypeScript)
- [Tailwind CSS](https://tailwindcss.com) with [shadcn-vue](https://www.shadcn-vue.com)-style components on [radix-vue](https://www.radix-vue.com)
- [Pest](https://pestphp.com) for testing, [Laravel Pint](https://laravel.com/docs/pint) for PHP formatting, [ESLint](https://eslint.org) + [Prettier](https://prettier.io) for the frontend

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- A database (SQLite by default; MySQL/PostgreSQL also supported)

## Getting started

```bash
git clone <repository-url>
cd trellow

composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite is the default — create the file if it doesn't exist yet:
touch database/database.sqlite

php artisan migrate

# Optional: seed 10 sample users (password: "password")
php artisan db:seed
```

Start the app:

```bash
composer run dev
```

This runs the PHP dev server, a queue worker, and the Vite dev server together — the queue worker matters because email verification and password-reset mail are dispatched as queued jobs. Visit `http://localhost:8000`.

## Configuring email

By default `MAIL_MAILER=log` writes outgoing mail to `storage/logs/laravel.log` instead of sending it — fine for local development. To actually deliver mail (required for a real signup flow), set `MAIL_MAILER=smtp` and the accompanying `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD` in `.env` to a real provider (Mailtrap, Gmail, Mailgun, etc.).

## Testing

```bash
php artisan test
```

## Code style

```bash
vendor/bin/pint    # PHP
npm run lint        # Vue/TypeScript
npm run format       # Prettier
```
