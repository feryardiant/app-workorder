# Laravel Template

Laravel 13 / PHP 8.4 app. UI is Blade + Livewire 4 + Flux UI 2 + Tailwind 4; auth is Fortify. Core domain: role-based user management and a database-backed notification inbox.

## Commands

| Task | Command |
| --- | --- |
| First-time setup | `composer setup`, then `npm ci` |
| Dev (server, queue, logs, Vite) | `composer run dev` (same as `php artisan dev`); `php artisan dev:list` shows the processes |
| Full gate | `composer test` — Pint check → PHPStan → Pest (Feature) → Pest (Browser) |
| Feature tests | `composer test:feature` (also run by `composer test`) — clears config, then Pest on `tests/Feature`; forwards extra args |
| Browser (end-to-end) tests | `composer test:browser` (also run by `composer test`) — once per clone: `vpx playwright install chromium` |
| Backend format | `composer format`, or `vendor/bin/pint --dirty --format agent` for only changed files |
| Backend static analysis | `composer check` (Larastan level 7: `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`) |
| Frontend lint/format/typecheck | `npm run lint` / `npm run format` (Vite+ `vp check`, type-aware) |
| Frontend build | `npm run build` (Vite+ `vp build`) |
| One test file | `php artisan test --compact tests/Feature/DashboardTest.php` |
| One test | `vendor/bin/pest tests/Feature/DashboardTest.php --filter=testName` |

- Before running tests outside `composer test`, run `php artisan config:clear`. A cached `bootstrap/cache/config.php` freezes `APP_ENV`, so `.env.testing` is skipped and `RefreshDatabase` wipes the dev database. `composer test` already clears it.
- `.env` and `.env.testing` are gitignored PostgreSQL configs with `APP_URL=http://localhost:8080`; `.env.testing` sets `DB_DATABASE=testing`. Without it (fresh clone, CI), tests fall back to the `.env` database.
- Start `docker compose up -d pgsql mailpit` before `composer setup`, migrations, or host tests. Compose services: `app`, `pgsql`, `mailpit`.
- Use npm: `package-lock.json` is committed (pnpm/yarn lockfiles are ignored). Versions come from `mise.toml` (PHP 8.4, Node 24).
- Lint, format, and staged-file settings live in `vite.config.ts`; `npm run *` delegates to Vite+ (`vp`). Commitlint config is in `package.json`; there are no ESLint/Prettier config files.
- Pint enforces `declare(strict_types=1)`, `native_function_invocation`, and FQCN imports. Add the strict-types declaration to new PHP files and let Pint fix the rest.

## Architecture

- `routes/web.php`: `/` redirects to `/dashboard`; dashboard, `users` index/show, and `notifications` index/poller/ack require `auth` + `verified`; signed `email/verify-new/{id}/{hash}` verifies invited users; settings profile requires `auth`, appearance/security require `auth` + `verified`, and security also uses `password.confirm`.
- Livewire 4 components are class-based: classes in `app/Livewire/**` with Blade views in `resources/views/livewire/**`, routed by class via `Route::livewire('users/{user}', Show::class)`.
- `App\Enums\Role` is int-backed (`Owner=0`, `Admin=1`, `Member=2`, `canManageUsers()`). Authorization is checked inside Livewire methods (`abort_unless`, `ChangeUserRole`) rather than policies: only managers can change users, only an Owner can assign Owner, and nobody can change their own role.
- Registration is disabled. Owners/Admins create accounts in `/users`; `VerifyNewUserEmailController` verifies the signed invite link and redirects to the password-reset screen so the new user sets their password. `app/Actions/Users/ChangeUserRole.php` dispatches `UserRoleChanged`, which notifies the target user (database + mail).
- Notification polling: `NotificationPollerController` returns the newest unread notification plus a pending list and tracks seen/pending IDs in the session; `ack` clears them. The inbox is `App\Livewire\Notifications\Index`.
- Fortify features are password reset, email verification, and 2FA (registration is absent). Auth screens are plain Blade forms under `resources/views/livewire/auth/`, bound in `app/Providers/FortifyServiceProvider.php`. Custom actions live in `app/Actions/Fortify/`, shared validation rules in `app/Concerns/`.
- Layouts are in `resources/views/layouts/`; local Flux overrides are in `resources/views/flux/`.
- `User` uses Laravel 13 attribute style (`#[Fillable([...])]`, `#[Hidden([...])]`) instead of `$fillable`/`$hidden`. Update the attributes when adding fields.
- `resources/js/app.ts` is a near-empty entrypoint; styles are in `resources/css/app.css` (Tailwind 4 `@theme` plus the Flux CSS import).

## Database and tests

- CI (`.github/workflows/tests.yml`) uses PostgreSQL 18, builds with `vp build`, then runs Pint, PHPStan, `php artisan test`, and `composer test:browser`. The job installs only the headless Chromium shell (`--only-shell`). Framework tables (`cache`, `jobs`, `sessions`) live in a separate `system` schema; `create_system_schema` is PostgreSQL-only and skips itself on other drivers. Keep migrations portable.
- `phpunit.xml` deliberately leaves the DB env vars commented, so tests use the `.env.testing` (or `.env`) connection: a local `php artisan test` runs against PostgreSQL and `RefreshDatabase` wipes that database. Never point tests at data you need.
- Every Feature and Browser test automatically uses `RefreshDatabase` (`tests/Pest.php`). Fortify feature tests use `$this->skipUnlessFortifyHas(...)` from `tests/TestCase.php` instead of asserting on disabled features.
- Browser tests live in `tests/Browser` (Pest Browser + Playwright) and also run as part of `composer test`; run them alone with `composer test:browser`. Use the shared `browserLogin()` helper in `tests/Pest.php`. They need built assets (`npm run build`) and Chromium (`vpx playwright install chromium`); CI installs only the headless shell, so use the full install locally for headed debugging. Assert the Indonesian UI text because `APP_LOCALE=id`, and run `php artisan optimize:clear` when Livewire assets 404 on a stale cache.
- Add a feature test for behavior changes instead of writing a verification script or tinker snippet; reserve browser tests for critical end-to-end paths (auth, main flows, accessibility, mobile usability).

## Boost MCP

- `opencode.json` and `.zed/settings.json` start `php artisan boost:mcp`. Prefer Boost tools over shell or manual guesses: `search-docs` for Laravel/Livewire/Flux APIs, `database-query` for read-only SQL, `database-schema` before schema changes, `browser-logs` for frontend errors, and `get-absolute-url` before sharing a URL.
- Read the matching skill in `.agents/skills/` before framework work: `livewire-development`, `fluxui-development`, `fortify-development`, `tailwindcss-development`, `laravel-best-practices`, or `testing-best-practices`.

## Localization

- Locale defaults are `id` with `en` fallback (`APP_LOCALE`, `APP_FALLBACK_LOCALE`).
- Write app strings in English with `__('English text')`; their exact text is the key in `resources/lang/id.json`. Update the matching key when changing the English text.
- Framework groups (`auth`, `passwords`, `pagination`, `validation`) live in `resources/lang/id/*.php`. Do not add English translation files.
- Preserve replacement placeholders such as `:name` and `:role` in translated values.
- Use sentence case for multiword action labels and lowercase for inline fragments. Tests that assert translated output should use Indonesian values and set the locale explicitly when needed.

## Conventions

- Git hooks in `.vite-hooks/` run `vp staged` before commits (auto-formats staged files) and commitlint on the message; use Conventional Commits.
- Clickable controls use `pointer` cursors and popover containers use `default` (base rules in `resources/css/app.css`). Put touch-only affordances behind `[@media(hover:none)]`; `->on()->mobile()` emulates `hover: none` and `pointer: coarse`.
- Do not add dependencies without approval.
- Only create documentation files when explicitly requested.
- If a frontend change does not appear or Vite reports a missing manifest entry, run `npm run build` (or start `npm run dev` / `composer run dev`).
