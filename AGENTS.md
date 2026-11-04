# Workorder Application

Laravel 13 / PHP 8.4 app. UI is Blade + Livewire 4 single-file components with Flux UI 2 and Tailwind 4. Authentication is Fortify. There is no Inertia, React, or Vue.

## Commands

| Task | Command |
| --- | --- |
| First-time setup | `composer setup`, then `npm ci` |
| Dev (server, queue, logs, Vite) | `composer run dev` (same as `php artisan dev`); `php artisan dev:list` shows the processes |
| Full gate | `composer test` — `config:clear` → Pint check → PHPStan → Pest |
| Backend format | `composer format`, or `vendor/bin/pint --dirty --format agent` for only changed files |
| Backend static analysis | `composer check` (Larastan level 7: `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`) |
| Frontend lint/format/typecheck | `npm run lint` / `npm run format` (Vite+ `vp check`, type-aware) |
| Frontend build | `npm run build` (Vite+ `vp build`) |
| One test file | `php artisan test --compact tests/Feature/DashboardTest.php` |
| One test | `vendor/bin/pest tests/Feature/DashboardTest.php --filter=testName` |

- Use npm: `package-lock.json` is the committed lockfile (pnpm/yarn lockfiles are ignored). Versions come from `mise.toml` (PHP 8.4, Node 24).
- Vite, formatter, and linter settings all live in `vite.config.ts`; there are no separate ESLint/Prettier config files.
- Pint enforces `declare(strict_types=1)`, `native_function_invocation`, and FQCN imports. Add the strict-types declaration to new PHP files and let Pint fix the rest.
- Pass `--no-interaction` to Artisan commands.

## Architecture

- `routes/web.php` only defines home and dashboard, then requires `routes/settings.php`.
- Auth routes are Fortify vendor routes, not in `routes/`. Views are bound in `app/Providers/FortifyServiceProvider.php`, custom actions live in `app/Actions/Fortify/`, and shared validation rules in `app/Concerns/`. Features and passkeys are toggled in `config/fortify.php`.
- Livewire 4 page components are single-file components at `resources/views/pages/**/⚡*.blade.php`, routed with `Route::livewire('settings/profile', 'pages::settings.profile')`. The PHP class is embedded at the top of the Blade file.
- Layouts are in `resources/views/layouts/`; local Flux overrides are in `resources/views/flux/`.
- `resources/js/app.ts` is a near-empty entrypoint; styles are in `resources/css/app.css` (Tailwind 4 `@theme` plus the Flux CSS import).
- `User` uses Laravel 13 attribute style (`#[Fillable([...])]`, `#[Hidden([...])]`) instead of `$fillable`/`$hidden`. Update the attributes when adding fields.

## Database and tests

- Local default is SQLite (`database/database.sqlite`); CI runs PostgreSQL 18 with framework tables (`cache`, `jobs`, `sessions`) in a separate `system` schema. Keep migrations portable across both.
- The `create_system_schema` migration is PostgreSQL-only and skips itself on other drivers.
- Every Feature test uses `RefreshDatabase` automatically (`tests/Pest.php`) and runs against the connection in `.env`, so a local `php artisan test` resets `database/database.sqlite`. Do not point tests at data you need; `phpunit.xml` does not override the connection.
- Fortify feature tests use `$this->skipUnlessFortifyHas(...)` from `tests/TestCase.php` rather than asserting on disabled features.

## Boost MCP

- `opencode.json` and `.zed/settings.json` start `php artisan boost:mcp`. Prefer Boost tools over shell or manual guesses: `search-docs` for Laravel/Livewire/Flux APIs, `database-query` for read-only SQL, `database-schema` before schema changes, `browser-logs` for frontend errors, and `get-absolute-url` before sharing a URL.
- Read the matching skill before framework work: `livewire-development`, `fluxui-development`, `fortify-development`, `tailwindcss-development`, `laravel-best-practices`, or `testing-best-practices`.

## Conventions

- Follow sibling files for structure, naming, and patterns; reuse existing components before creating new ones.
- Do not add dependencies without approval.
- Only create documentation files when explicitly requested.
- Add a feature test for behavior changes instead of writing a verification script or tinker snippet.
- If a frontend change does not appear or Vite reports a missing manifest entry, run `npm run build` (or start `npm run dev` / `composer run dev`).
