# ProjectBoard

A Trello-style project management app built with Laravel, Inertia, and Vue. Organize work into workspaces, boards, lists, and cards, collaborate with your team, and track progress from a dashboard.

## Features

**Workspaces & boards**
- Workspaces with their own members and color, each holding multiple boards
- Boards with drag-and-drop lists and cards, per-item colors, and archive/restore for both lists and boards
- Duplicate a list or a single card (including its checklist) in one click

**Cards**
- Descriptions, due dates, checklists with progress tracking, and color labels
- Card members, searchable by name or email
- Comments and an auto-generated activity feed (moves, checklist changes, member changes, archiving)

**Collaboration**
- Workspace, board, and card-level membership with role-appropriate authorization
- Email verification and password reset, both sent through a queued job so the request never blocks on SMTP

**Dashboard**
- Total/completed/overdue/due-soon task counts and aggregate checklist progress
- Tasks-by-board (or tasks-by-list when a single board is selected) and workload-per-member breakdowns
- Recent activity feed, filterable by workspace or board

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
