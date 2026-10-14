# Manager Library API

REST backend for Manager Library, built with PHP 8.2+, Laravel 12, and PostgreSQL 16. This repository contains the API only. The React frontend, infrastructure containers, public website, and demo seeder are maintained in their respective repositories.

## Functional scope

The API implements authentication for administrators and librarians, fixed categories, a book catalog with covers, members, loans with per-item returns, overdue-loan listing, and a most-borrowed-books report. It does not include fines, reservations, renewals, notifications, bulk import, branches, or book attributes beyond title, author, ISBN, category, copies, and cover.

The language-independent product behavior is documented in the sibling `test-manager-library-3145555-docs` repository. PHP/Laravel and React are the selected implementation stack. Code, route, and database identifiers use English; messages presented to users remain in Spanish.

## Contract clarifications

- The cover section requires `GET /books/{id}/cover`, although the route is missing from the endpoint table. The API implements it so clients can retrieve the image referenced by `cover_key`.
- The authentication row omits the authorization scheme. Clients send the Bearer scheme specified by the login example and security section.
- ADR-002 refers to a version token that is absent from the physical model. To preserve the documented six-table schema, the API reserves copies using a conditional atomic update inside a transaction; no version column is added.
- Category names shown in the UI remain the Spanish catalog values specified by the product. Table names, columns, routes, and implementation identifiers use English.

The initial migration enables `ck_book_total_positive` and `ck_user_role` immediately rather than postponing them to a later task. This strengthens PostgreSQL invariants without adding business tables or columns.

## Implemented routes

All routes except `GET /health` and `POST /api/v1/auth/login` require a Bearer token.

| Method | Route | Access |
|---|---|---|
| GET | `/health` | Public |
| POST | `/api/v1/auth/login` | Public |
| POST | `/api/v1/users` | admin |
| GET | `/api/v1/categories` | Authenticated |
| GET, POST | `/api/v1/books` | Read: authenticated; create: admin |
| GET, PUT, DELETE | `/api/v1/books/{id}` | Read: authenticated; write: admin |
| GET, PUT, DELETE | `/api/v1/books/{id}/cover` | Read: authenticated; write: admin |
| GET, POST | `/api/v1/members` | admin, librarian |
| GET | `/api/v1/members/{id}` | admin, librarian |
| GET, POST | `/api/v1/loans` | admin, librarian |
| GET | `/api/v1/loans/{id}` | admin, librarian |
| GET | `/api/v1/loans/overdue` | admin, librarian |
| POST | `/api/v1/loans/{loan_id}/items/{item_id}/return` | admin, librarian |
| GET | `/api/v1/reports/most-borrowed` | admin |

## Setup and run

Requirements: PHP 8.2+ with `pdo_pgsql`, `mbstring`, and `fileinfo`; Composer 2; and PostgreSQL 16. The business schema starts empty. The application migrations create its tables, indexes, constraints, and categories.

1. Run `composer install`.
2. Copy `.env.example` to `.env` and set `APP_KEY`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `JWT_SECRET`, `ADMIN_USERNAME`, `ADMIN_PASSWORD`, and `CORS_ORIGIN`.
3. Run `php artisan key:generate` if `APP_KEY` is not already set.
4. Create the PostgreSQL database and grant access to the application user. The initial migration creates the `library` schema.
5. Run `php artisan migrate --force`.
6. Start the API with `php artisan serve`.

After migrations run, the HTTP application idempotently provisions the initial administrator from environment credentials. You can also run `php artisan library:provision-admin` explicitly. Migrations do not store credentials or create that user. Do not configure `DB_CONNECTION=sqlite`: the required partial indexes, constraints, and concurrency behavior depend on PostgreSQL.

The API is available under `/api/v1`; public `GET /health` returns `{"status":"ok"}`. API errors use a common `code`, `message`, and `details` shape. Passwords, hashes, SQL queries, and stack traces are never returned.

## Tests

Run `php artisan test`. Unit and contract tests do not require a database connection; integration tests require a separate PostgreSQL test database. Never point tests at a database containing data that must be preserved.
