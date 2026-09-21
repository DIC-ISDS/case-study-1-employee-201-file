# Case Study 1 — Employee 201 File and HR Profile

An authoritative Personnel Profile master plus a **Profile Change Request**
workflow: an employee requests a change, HR Staff verifies it, an HR Approver
decides, and approved changes are written onto the personnel record.

**Core business rule:** an employee may hold only **one active primary
appointment** at a time — enforced in the application *and* by a unique index
in the database.

Every primary record also captures **who created it**, and normal deletion is a
**soft delete** — see [Record ownership](#record-ownership-and-soft-deletes).

## Quick start

```bash
docker compose up -d
docker compose exec php php artisan migrate:fresh --seed
```

Open **http://localhost:8092/admin**. All demo accounts use the password
`password`:

| Role | Email | Sees |
| --- | --- | --- |
| Employee | `employee@example.com` | Own 201 file and own requests only |
| HR Staff | `hrstaff@example.com` | Full roster, master data, verification queue |
| HR Approver | `hrapprover@example.com` | The above, plus approve / return / reject |

Demo credentials only — replace before this is exposed anywhere.

## Documentation

| Document | What it covers |
| --- | --- |
| [System Context](docs/01-system-context.md) | Boundary, the three roles, interactions |
| [ERD](docs/02-erd.md) | Tables, keys, cardinalities — matches the migrations |
| [Process Flow](docs/03-process-flow.md) | Statuses, decision points, alternate paths |
| [Data Flow (Level 0)](docs/04-data-flow-level-0.md) | Processes, data stores, inputs and outputs |
| [Architecture](docs/05-architecture.md) | Containers and where each concern lives *(bonus)* |
| [PDCA](docs/06-pdca.md) | Plan, Do, Check, Act — including what testing found |
| [Demo Script](docs/07-demo-script.md) | The full demo, act by act |

## What is implemented

| Requirement | Where |
| --- | --- |
| Login | Filament panel at `/admin` |
| Access differs by role | `app/Policies/**` + query scoping on each resource |
| Master data maintained | Personnel, Offices, Positions resources |
| Primary transaction | Profile Change Request with one item per field |
| Review / approval step | HR Staff verifies → HR Approver decides |
| Alternate path | Return for revision, and Reject |
| Business rule enforced | `Appointment::booted()` + unique index on a generated column |
| Status clearly visible | Status badges, `Verified` column, nav badge per role |
| Search and filter | Employee no./name search; office, position, status, "awaiting" filters |
| Dashboard | `app/Filament/Widgets/**` — stats + personnel-by-office chart |
| History / audit trail | `profile_change_histories`, shown on each request |
| Report | Printable personnel master list at `/reports/personnel-master-list` |
| Ownership captured automatically | `created_by` / `updated_by` via `App\Models\Concerns\TracksOwnership` |
| Soft deletes | `deleted_at` on every primary table, with Trashed filter and Restore |
| Seeded demo data | 24 personnel, 6 offices, 8 positions, one request per status |

### The five statuses

`Draft` → `Pending` → `Approved` / `Returned` / `Rejected`

A request stays `Pending` through HR Staff verification; verification is what
unlocks the approver's decision, which is how the two HR roles differ.

## Record ownership and soft deletes

Every table except `users` and the append-only `profile_change_histories`
carries `created_by`, `updated_by` and `deleted_at`.

- **`created_by` is not selectable.** It appears in no Filament form and is not
  mass-assignable; `App\Models\Concerns\TracksOwnership` fills it from the
  authenticated user, so seeders, tinker and the approval workflow are held to
  the same rule as the UI. `updated_by` stays `NULL` until a record is actually
  changed.
- **Delete means hide.** Deleted records drop out of every listing, dashboard
  count and report, but stay on file. Each table offers a **Trashed** filter,
  and an HR Approver can **Restore**. Permanent deletion is closed to every
  role — `forceDelete` returns `false` in each policy.
- **Visibility is scoped** by role and ownership: an employee sees only their
  own 201 file and their own requests; HR sees the whole roster.

One consequence was not obvious: a unique index counts soft-deleted rows, so
both business-rule indexes are built on generated columns that go `NULL` once a
row is trashed. Without that, a deleted appointment would go on holding the
employee's only active primary slot. See
[docs/02-erd.md](docs/02-erd.md#soft-deletes-and-the-unique-indexes) and the
Check / Act sections of [docs/06-pdca.md](docs/06-pdca.md).

## Tests

```bash
docker compose exec php php artisan test
```

56 tests / 182 assertions, covering the three scenarios the case study asks
for — a normal transaction, a returned and a rejected one, and the
business-rule edge case — plus record ownership and soft deletes. The suite
runs against **MySQL**, not SQLite, so it exercises the real unique indexes and
generated columns — see `phpunit.xml`.

## Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13.32 |
| Admin UI | FilamentPHP 5.8 |
| PHP | 8.4 (php-fpm) |
| Database | MySQL 8.1 |
| Frontend | Filament + Livewire + Tailwind |
| Tests | PHPUnit 12 |

## Ports

Chosen to avoid collisions with the other projects in `/Users/dic-isds/PROJECT`.

| Service | Host | Container |
| --- | --- | --- |
| App (nginx) | http://localhost:8092 | 80 |
| phpMyAdmin | http://localhost:8093 | 80 |
| MySQL | 127.0.0.1:3311 | 3306 |
| Vite HMR | 5180 | 5173 |

Database `laravel`, user `uplb`, password `uplb`, root password `root`. The
test suite uses a separate `laravel_testing` database.

## Docker setup

Based on `UPLB-HR SERVICES`, with these changes:

- **Kafka removed** — build layers and all four consumers. External system
  integration is bonus-only for this MVP.
- **`.docker/db/my.cnf` is a real file**, not the empty directory Docker had
  auto-created in the source project, where that mount silently did nothing.
- **`entrypoint.sh` creates `storage/logs`** before supervisord starts.
  supervisord validates every `stdout_logfile` directory up front and aborts if
  one is missing, so without this the container dies on a fresh clone.
- **Config `COPY`s at the end of the Dockerfile**, so editing `php.ini` or
  `supervisord.conf` rebuilds a few cheap layers rather than re-running the
  apt/pecl installs.
- **Composer from the official `composer:2` image** rather than curling
  `getcomposer.org` during the build, which timed out and failed the build.
- **`restart: unless-stopped` on php and nginx** — nginx hard-exits when its
  `php` upstream is unresolvable and will not retry by itself.

## Layout

```
app/
  Enums/              UserRole, EmploymentStatus, RequestStatus, Appointment{Type,Status}
  Exceptions/         BusinessRuleViolation
  Filament/           Resources, RelationManagers, Widgets
  Http/Controllers/   Reports/PersonnelMasterListController
  Models/             Personnel, Office, Position, Appointment, ProfileChange*
    Concerns/         TracksOwnership — created_by / updated_by from the session
  Policies/           One per model, role-based
  Services/           ProfileChangeWorkflow, AppointmentService
  Support/            ProfileField — the editable fields and how they render
database/
  migrations/         Schema, incl. the generated columns + unique indexes
  seeders/            DatabaseSeeder — the full demo dataset, each row owned
docs/                 The four required diagrams, PDCA, demo script
tests/Feature/        Business rule, workflow, authorization, panel, form,
                      ownership and soft deletes
.docker/              nginx, php, db configs
```
