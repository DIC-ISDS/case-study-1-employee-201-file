# 5. Application Architecture (bonus)

```mermaid
flowchart TB
    BROWSER["🌐 User browser<br/><i>Filament UI · Livewire · Tailwind</i>"]

    subgraph docker["Docker Compose"]
        NGINX["nginx<br/><i>:8092 → :80</i>"]
        PHP["php 8.4-fpm<br/><i>supervisord: php-fpm + queue worker</i>"]
        DB[("MySQL 8.1<br/><i>:3311 → :3306</i>")]
        PMA["phpMyAdmin<br/><i>:8093 → :80</i>"]
    end

    BROWSER -->|HTTP| NGINX
    NGINX -->|FastCGI :9000| PHP
    PHP -->|PDO| DB
    PMA --> DB

    subgraph app["Laravel 13 + Filament 5"]
        RES["Filament Resources<br/>Personnel · Requests · Offices · Positions"]
        POL["Policies<br/><i>role-based authorization</i>"]
        SVC["Services<br/>ProfileChangeWorkflow · AppointmentService"]
        MOD["Eloquent Models<br/><i>business rule guard</i>"]
    end

    PHP --- app
    RES --> POL
    RES --> SVC
    SVC --> MOD
    MOD --> DB

    style docker fill:#f9fafb,stroke:#9ca3af
    style app fill:#eff6ff,stroke:#3b82f6
```

## Where each concern lives

| Concern | Location |
| --- | --- |
| Screens, forms, tables, actions | `app/Filament/Resources/**` |
| Who may do what | `app/Policies/**` |
| Status transitions + audit writes | `app/Services/ProfileChangeWorkflow.php` |
| Appointment transfer | `app/Services/AppointmentService.php` |
| Core business rule (app layer) | `app/Models/Appointment.php` — `booted()` |
| Core business rule (database layer) | `database/migrations/2026_09_18_000005_*` — generated column + unique index |
| Editable profile fields + display | `app/Support/ProfileField.php` |
| Dashboard | `app/Filament/Widgets/**` |
| Printable report | `app/Http/Controllers/Reports/**`, `resources/views/reports/**` |

Every status change goes through `ProfileChangeWorkflow`, so the audit trail
cannot drift out of step with the request. No file storage or external APIs are
used.
