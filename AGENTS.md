# Repository Guidelines

## Project Structure & Module Organization
`blogme` is a PHP 8.1+ MVC app with SQLite storage.

- `app/`: core application code (`Controllers/`, `Repositories/`, `Services/`, `Core/`, `Support/`).
- `bootstrap/`: startup wiring (`autoload.php`, app bootstrap).
- `public/`: web entrypoint (`index.php`) and published assets/uploads.
- `resources/`: source templates, locales, admin assets, and default theme files.
- `data/`: runtime theme and upload data (copied/served from here).
- `tests/`: PHPUnit tests (e.g., `RouterTest.php`, `PostRepositoryTest.php`).
- Root runtime/config files: `db.sqlite`, `config.json`, `phpunit.xml`, `cli.php`.

## Build, Test, and Development Commands
- `composer install`: install PHP dependencies.
- `php -S 127.0.0.1:8080 public/index.php`: run local dev server.
- `composer test`: run PHPUnit suite (alias of `phpunit` from `composer.json`).
- `php vendor/bin/phpunit --colors=never`: CI-friendly explicit test run.
- `php -l app/Controllers/PublicController.php`: syntax check a file before commit.
- `php cli.php reset-password <email>`: reset an account password from CLI.

## Coding Style & Naming Conventions
- Follow existing PHP style: `declare(strict_types=1);`, 4-space indentation, typed signatures, and early returns.
- Use PSR-4 namespaces: `Blogme\\...` under `app/`, `Blogme\\Tests\\...` under `tests/`.
- Class files use `PascalCase.php`; test classes end with `Test` and live in `tests/`.
- Keep controllers thin; place data access in repositories and shared logic in services/support.

## Testing Guidelines
- Framework: PHPUnit 10 (`phpunit.xml`, bootstrap `bootstrap/autoload.php`).
- Add/update tests for behavior changes in routing, repositories, and helpers.
- Name tests clearly by behavior, e.g., `testPatternMatchAndVars`.
- Run `composer test` before opening a PR.

## Commit & Pull Request Guidelines
- Use Conventional Commit prefixes seen in history: `feat: ...`, `fix: ...`.
- Keep subject lines imperative and specific (what changed, not why only).
- PRs should include: concise summary, affected paths, test evidence (`composer test` output), and screenshots for template/admin UI updates.
- Link related issues/tasks and call out config/data impacts (`config.json`, `db.sqlite`, `data/`).
