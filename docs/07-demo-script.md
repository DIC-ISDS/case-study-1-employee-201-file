# 7. Demo Script

The required scenario: an employee requests a change of office/position, HR
verifies it, the approver accepts it — and a second attempt to create another
active primary assignment triggers the business rule.

Reset to a known state first:

```bash
docker compose exec php php artisan migrate:fresh --seed
```

All accounts use the password `password`.

| Role | Email |
| --- | --- |
| Employee (Maria Santos) | `employee@example.com` |
| HR Staff (Jose Cruz) | `hrstaff@example.com` |
| HR Approver (Ana Reyes) | `hrapprover@example.com` |

---

## Act 1 — Employee files a request *(≈60s)*

Sign in as **employee@example.com**.

1. The dashboard shows only *My profile change requests* — no org-wide figures.
2. Open **Personnel**. Only Maria's own record is listed; there is no Create
   button, and Offices/Positions are absent from the navigation.
3. Open her record → **Current primary appointment**: *Instructor I —
   Institute of Computer Science*.
4. Go to **Profile Change Requests → New**.
   - Purpose: *Reassignment to the University Library effective this term.*
   - Add a field to change → **Office** → *University Library*
   - Add a field to change → **Position** → *Librarian II*
   - Create.
5. The request is **Draft**, reference `PCR-<year>-nnnn`. Open **Workflow →
   Submit for review**. Status becomes **Pending**.
6. Open the **History** tab: `Created` and `Submitted` are already recorded.

> Point out: the *Current value* column was filled in by the system, not typed.

---

## Act 2 — HR Staff verifies *(≈45s)*

Sign in as **hrstaff@example.com**.

1. The **Profile Change Requests** nav item carries a badge — requests awaiting
   *this role*.
2. Filter **Awaiting → HR Staff verification**. Maria's request is there.
3. Note that no Approve button is offered to HR Staff at all.
4. **Workflow → Verify**, remarks *Documents complete and checked against the
   201 file.*
5. The **Verified** column flips to *Yes* and the badge moves off this role.

---

## Act 3 — HR Approver decides *(≈45s)*

Sign in as **hrapprover@example.com**.

1. Dashboard: *Active personnel*, *Pending profile changes*, *Returned
   requests*, *Approved this month*, and the **Personnel by office** chart.
2. Open the request → **Workflow → Approve**.
3. Go to **Personnel → Maria Santos**. Her current appointment is now
   *Librarian II — University Library*.
4. Open the **Appointment history** tab: the old appointment is **Ended** with
   an end date, the new one is **Active**. The transfer did not duplicate it.

---

## Act 4 — The business rule *(≈45s, the important part)*

Still on Maria's record, on the **Appointment history** tab:

1. Click **Add appointment**.
2. Office *Accounting Office*, Position *Administrative Officer III*,
   Type **Primary**, Status **Active**, any start date. Save.
3. The save is refused:

   > *Maria L. Santos already holds an active primary appointment (Librarian II
   > — University Library). An employee may have only one active primary
   > appointment at a time — end the current one first, or file this as a
   > secondary appointment.*

4. Change Type to **Secondary** and save — this succeeds, showing the rule
   constrains *primary* appointments specifically, not all of them.
5. To show the correct path: delete the secondary, then use **Transfer
   primary**, which ends the current appointment and opens the new one in a
   single transaction.

### Showing that the rule is not just a form check

```bash
docker compose exec php php artisan tinker --execute '
$p = App\Models\Personnel::where("last_name","Santos")->first();
try {
    DB::table("appointments")->insert([
        "personnel_id" => $p->id,
        "office_id" => 1, "position_id" => 1,
        "type" => "primary", "status" => "active",
        "start_date" => now()->toDateString(),
        "created_by" => App\Models\User::where("email","hrstaff@example.com")->value("id"),
        "created_at" => now(), "updated_at" => now(),
    ]);
    echo "NOT BLOCKED\n";
} catch (Illuminate\Database\UniqueConstraintViolationException $e) {
    echo "BLOCKED BY THE DATABASE\n";
}'
```

A raw insert that skips the model entirely is still refused, by the unique
index on the generated column.

---

## Act 5 — Report and audit *(≈30s)*

1. **Personnel → Print master list** (or **Reports → Personnel Master List**)
   opens the printable list: name, office, position, employment status.
   *Print / Save as PDF* is a browser print away.
2. Back on the request, the **History** tab shows the full chain —
   Created → Submitted → Verified by HR Staff → Approved and applied — with who
   did each and when.

---

## Act 6 — Ownership and soft deletes *(≈45s)*

Still signed in as **hrapprover@example.com**.

1. On **Personnel → Maria Santos → View**, scroll to **Record trail**: *Encoded
   by Jose Cruz* (HR Staff, who seeded the roster) and *Last updated by Ana
   Reyes* after the approval. Neither field appears on the Edit form —
   `created_by` is taken from the signed-in user and is not selectable.
2. On the **Personnel** table, open the column toggle and switch on *Encoded
   by* / *Last updated by* to show the same thing across the roster.
3. Open **Offices**, delete one — say *Accounting Office*. It disappears from
   the list.
4. Set the **Trashed** filter to *With trashed*: the office is back in view,
   with a **Deleted** timestamp. Nothing was destroyed.
5. Click **Restore**. The record returns to the normal listing.
6. As **hrstaff@example.com**, the Restore button is not offered — restoring is
   the approver's call.

> Point out: there is no permanent-delete action anywhere. `forceDelete` is
> closed to every role, because a deleted record is still part of the history.

---

## Alternate path, if time allows *(≈30s)*

Sign in as the employee, open the seeded **Returned** request (Miguel Torres,
address change): the approver's remarks are visible, the request is editable
again, and resubmitting sends it back for verification from the start.

---

## Running the tests live

```bash
docker compose exec php php artisan test
```

56 tests, 182 assertions — including the two that prove the rule holds in both
the application and the database, and the ones that prove a soft-deleted
appointment neither holds the employee's primary slot nor escapes the rule when
it is restored.
