# 4. Data Flow Diagram — Level 0

```mermaid
flowchart LR
    EMP["👤 Employee"]
    STAFF["👤 HR Staff"]
    APPR["👤 HR Approver"]

    P1(["1.0<br/>Maintain<br/>Master Data"])
    P2(["2.0<br/>File Profile<br/>Change Request"])
    P3(["3.0<br/>Verify &amp;<br/>Decide"])
    P4(["4.0<br/>Apply Approved<br/>Changes"])
    P5(["5.0<br/>Report &amp;<br/>Monitor"])

    D1[("D1 · Personnel")]
    D2[("D2 · Offices / Positions")]
    D3[("D3 · Appointments")]
    D4[("D4 · Profile Change Requests<br/>+ Items")]
    D5[("D5 · Request History")]

    STAFF -->|"personnel, office,<br/>position details"| P1
    P1 -->|"master records"| D1
    P1 -->|"lookup records"| D2
    P1 -->|"appointment records"| D3

    EMP -->|"requested field values,<br/>purpose"| P2
    D1 -->|"current values"| P2
    P2 -->|"request + items<br/>(old value snapshot)"| D4
    P2 -->|"created / submitted"| D5

    D4 -->|"pending requests"| P3
    STAFF -->|"verification + remarks"| P3
    APPR -->|"approve / return / reject<br/>+ remarks"| P3
    P3 -->|"updated status"| D4
    P3 -->|"transition record"| D5
    P3 -->|"request status"| EMP

    P3 -->|"approved request"| P4
    P4 -->|"updated profile fields"| D1
    P4 -->|"ended + new appointment"| D3
    D2 -->|"valid office / position"| P4

    D1 -->|"personnel + status"| P5
    D3 -->|"active primary appointments"| P5
    D4 -->|"counts by status"| P5
    D5 -->|"audit trail"| P5
    P5 -->|"dashboard, master list,<br/>request history"| STAFF
    P5 -->|"dashboard"| APPR
    P5 -->|"own record + own requests"| EMP

    style P1 fill:#eff6ff,stroke:#3b82f6
    style P2 fill:#eff6ff,stroke:#3b82f6
    style P3 fill:#eff6ff,stroke:#3b82f6
    style P4 fill:#eff6ff,stroke:#3b82f6
    style P5 fill:#eff6ff,stroke:#3b82f6
```

## Processes

| # | Process | Main input | Main output | Data stores |
| --- | --- | --- | --- | --- |
| 1.0 | Maintain Master Data | Personnel, office, position details from HR | Authoritative master records | D1, D2, D3 |
| 2.0 | File Profile Change Request | Requested values + purpose from the employee | Draft/submitted request with an old-value snapshot | D1 (read), D4, D5 |
| 3.0 | Verify & Decide | Verification and decision with remarks | Updated status, transition record, notice to employee | D4, D5 |
| 4.0 | Apply Approved Changes | An approved request | Updated personnel record; ended + new appointment | D1, D2 (read), D3 |
| 5.0 | Report & Monitor | Stored records | Dashboard, printable master list, audit trail | D1, D3, D4, D5 (read) |

Process 4.0 is the only path that writes to Appointments as part of the
workflow, and it always goes through the transfer routine — which is why the
one-active-primary rule cannot be sidestepped by approving a request.

Every write into D1–D4 also stamps the acting user onto the row (`created_by`
on insert, `updated_by` on change), and deletion writes `deleted_at` rather
than removing the row, so nothing in the diagram above is ever a destructive
flow. D5 is append-only and is never written by any process other than 2.0 and
3.0.
