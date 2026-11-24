# KSA Drop Portal — instructions for AI agents

Laravel 13 (PHP 8.3) + Inertia/React. Tests: PHPUnit 12 (`php artisan test`).

## Logging (mandatory)

Read [docs/logging/README.md](docs/logging/README.md) before adding or changing any logging.

- **Never** call `Log::`, `logger()` or `error_log()`. Always `AppLog::emit(Event::CASE, key: value)` or `AppLog::failure(Event::CASE, $e, …)` from `App\Logging`.
- A log line holds **IDs only**: event ID, row IDs, `Reason`/enum values, vendor codes. No messages, names, phones, emails, tokens or free text. Descriptions live only in `app/Logging/Events/*`, `Reason` and `Ctx`.
- **Reuse** an existing event/`Reason`/`Ctx` key (see `docs/logging/catalog.md`) before adding one. New event IDs take the next free number in the domain's sequence block (`DDNNN`).
- **Every important action emits a HISTORY event** (`keep: Retention::HISTORY`): create/delete/restore, status changes, money, access/permission/security changes, settings changes, integrations connected, messages sent to customers, compliance. Everything else is PRUNABLE (deleted after 45 days). Model writes are recorded automatically for every model in `App\Logging\Enums\ModelType` — add new business models there. Query-builder `update()`/`delete()` bypass that, so emit `AuditEvent::RECORD_UPDATED` per row yourself. Anything that is not a model write (messages sent, integrations, sign-ins) needs its own HISTORY event.
- **Never renumber or reuse an ID.** To remove an event, delete the case and add its ID to that enum's `RETIRED` list.
- Tests run with `AppLog::fake()` installed (`$this->logs`), which still validates context strictly.
- After adding events run `php artisan log:catalog` and `php artisan test --filter=Logging`.
- Never weaken `NoRawLoggingTest`: it fails on any `Log::`, `logger()` or `error_log()` outside `app/Logging`.
- Debug with `php artisan log:decode [file] --trace=<id>`; the error page "reference" is the trace ID.

## Testing notes

- A handful of `RequestsLockTest` tests fail on a clean checkout (env default); compare failures against a baseline before blaming a change.
- Run `php artisan route:clear` first if a new route 404s.
- After UI edits run `npm run build` and `npx vite build`.
