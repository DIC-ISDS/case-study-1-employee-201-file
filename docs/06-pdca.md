# 6. PDCA

## PLAN

**Administrative problem.** Personnel information is scattered across
spreadsheets, paper 201 files and separate systems. There is no authoritative
employee profile, and no controlled way to change one — edits happen directly
in whichever copy someone is holding, with no record of who changed what.

**Primary users.** Employee, HR Staff, HR Approver.

**MVP scope.**

- Personnel Profile Master (identity, office, position, employment status)
- Profile Change Request as the only route to changing a profile
- Two-step review: HR Staff verifies, HR Approver decides
- Five statuses: Draft, Pending, Approved, Returned, Rejected
- One active primary appointment per employee, enforced by the application
- Every record owned by the user who encoded it, and deleted records kept
- Dashboard, printable master list, audit trail, seeded demo data

**Core entities.** Personnel, Office, Position, Appointment,
ProfileChangeRequest, ProfileChangeItem, plus ProfileChangeHistory for the
audit trail.

**Workflow.** Employee submits → HR Staff verifies → HR Approver approves,
returns or rejects → approved changes are written onto the personnel record.

**Measurable success condition.** An employee's request to change office
reaches the approver, is approved, and updates the personnel record — while a
second attempt to create an active primary assignment for the same employee is
refused, with the data left unchanged.

## DO

Built on Laravel 13 + Filament 5 against MySQL 8.1, in this order:

1. **Data model first** — enums, migrations, models. The one-active-primary
   rule went into the schema at this stage as a generated column plus a unique
   index, rather than being left to the UI.
2. **Business rule guard** in `Appointment::booted()`, so seeders, tinker,
   tests and the approval path are all held to it and the user gets a readable
   message instead of an integrity-constraint error.
3. **Services** — `ProfileChangeWorkflow` owns every status transition and
   writes the audit trail; `AppointmentService` performs a transfer as
   end-then-create inside one transaction.
4. **Policies** for the three roles, including the rule that HR Staff verify
   and only HR Approver decides, and only on a verified request.
5. **Filament resources**, dashboard widgets, printable report.
6. **Seed data** — 24 personnel across 6 offices, and one request in each of
   the five statuses.
7. **Record ownership and soft deletes** across every primary table:
   `created_by`, `updated_by` and `deleted_at`, with the two user columns
   written from the authenticated user by a single model trait rather than by
   any form.

## CHECK

Tested with 56 automated tests (182 assertions), covering the three required
scenarios:

| Scenario | Test | Result |
| --- | --- | --- |
| **1. Normal transaction** | `test_a_request_moves_from_draft_through_verification_to_approval` | Draft → Pending → verified → Approved, and the personnel record is updated |
| **2. Returned / rejected** | `test_a_returned_request_can_be_corrected_and_resubmitted`, `test_a_rejected_request_is_final_and_changes_nothing` | Returned requests become editable again and resubmission resets verification; rejection changes nothing |
| **3. Business rule edge case** | `test_a_second_active_primary_appointment_is_rejected`, `test_the_database_rejects_a_duplicate_even_when_the_model_is_bypassed` | Blocked in the application *and* by the database when the model is bypassed |

### Issues discovered during testing

**Issue 1 — the change-value input silently saved nothing.** The request form
switches between a dropdown (for office, position, employment status) and a
text box (for everything else). Both were initially named `new_value`. Two
Filament components sharing one state path overwrite each other, so choosing an
office produced a saved item with `new_value = null`. The UI gave no error: the
request saved successfully and simply carried no value.

This was invisible until a test asserted the *stored value* rather than that
the save succeeded — the first version of the multi-field test only asserted a
row count, and passed while the bug was live.

**Issue 3 — soft deletes quietly broke the core business rule.** Adding
`deleted_at` to `appointments` was expected to be a routine change. It was not:
the unique index that enforces one active primary appointment per employee
counts soft-deleted rows. So an appointment that HR had deleted went on holding
the employee's only primary slot, and the application refused to open a
replacement — citing a conflicting appointment that was not visible anywhere in
the UI. The same defect applied to `profile_change_items`: removing a line from
a request and adding the same field again collided with the row that had just
been hidden.

The failing test was `test_a_deleted_appointment_frees_the_employee_for_a_new_primary`,
written before the migration on the assumption it would pass trivially.

**Issue 2 — the container would not start on a fresh clone.** `supervisord`
validates every `stdout_logfile` directory at startup and aborts if one is
missing. Since `storage/logs` does not exist before the app is installed, the
php container crash-looped.

## ACT

**Improvement 1 (from Issue 1).** The two inputs were given their own state
paths, `new_value_option` and `new_value_text`, and are collapsed back into the
single stored column by the repeater's
`mutateRelationshipDataBeforeCreateUsing` / `...BeforeSaveUsing` hooks, and
expanded again on fill. The multi-field test was then strengthened to assert
the actual stored values, not just the row count — the weak assertion was the
reason the bug survived as long as it did.

**Improvement 2 (from Issue 2).** `entrypoint.sh` now creates `storage/logs`
and the `framework/*` directories before handing over to supervisord, and the
queue worker waits for `artisan` to exist before starting. A fresh
`docker compose up -d` now boots cleanly.

**Improvement 4 (from Issue 3).** Both unique indexes were rebuilt on generated
columns that evaluate to `NULL` once the row is trashed, so a soft-deleted row
drops out of the constraint while live rows stay under it:

```sql
active_primary_personnel_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE
        WHEN deleted_at IS NULL AND type = 'primary' AND status = 'active'
        THEN personnel_id
    END
) VIRTUAL
```

Restoring an appointment still runs the model guard, so putting one back while
another is active is refused with the same readable message rather than an
integrity-constraint error — covered by
`test_restoring_an_appointment_still_honours_the_business_rule`.

**Improvement 3.** Snapshotting of the old value moved out of the form and into
`ProfileChangeItem::booted()`, so the "from → to" record is correct no matter
how an item is created and cannot be spoofed from the request payload.

## 30–60 second summary for the demo

> We planned a single authoritative 201 file where the only way to change a
> profile is a request that HR verifies and an approver decides. We built the
> master data, the five-status request workflow, role-based access, a
> dashboard and a printable master list — and we put the one-active-primary
> rule in the database as a unique index, not just in the form. Testing found
> that our change-value field silently saved nothing for dropdown fields,
> because two inputs shared one state path, and that our own test was too weak
> to catch it — it only checked that a row existed. We separated the inputs,
> and we rewrote the test to assert the stored value. That is also why the
> business rule is enforced in two places: the form can be wrong, the unique
> index cannot. Adding soft deletes then caught us out in the other direction —
> a deleted appointment still occupied the employee's primary slot, because a
> unique index counts rows nobody can see. We moved the rule onto a generated
> column that goes null once a row is trashed.
