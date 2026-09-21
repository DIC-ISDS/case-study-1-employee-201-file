# 1. System Context Diagram

Scope: the MVP as actually built. There is no external system integration —
the only "external" dependency is the MySQL database the application owns.

```mermaid
flowchart TB
    subgraph actors[" "]
        direction LR
        EMP["👤 Employee<br/><i>owns a 201 file</i>"]
        STAFF["👤 HR Staff<br/><i>verifies requests</i>"]
        APPR["👤 HR Approver<br/><i>decides requests</i>"]
    end

    subgraph boundary["Employee 201 File &amp; HR Profile System<br/><i>Laravel 13 + Filament 5</i>"]
        APP["Web application<br/>· Personnel Profile Master<br/>· Profile Change Requests<br/>· Approval workflow<br/>· Dashboard &amp; report<br/>· Audit trail"]
    end

    DB[("MySQL 8.1<br/><i>application database</i>")]
    PRINT["🖨️ Browser print / PDF<br/><i>personnel master list</i>"]

    EMP -->|"files and corrects<br/>profile change requests"| APP
    APP -->|"shows own 201 file<br/>and request status"| EMP

    STAFF -->|"verifies requests,<br/>maintains master data"| APP
    APP -->|"work queue awaiting<br/>verification"| STAFF

    APPR -->|"approves / returns / rejects"| APP
    APP -->|"work queue awaiting<br/>decision, dashboard"| APPR

    APP <-->|"reads / writes"| DB
    APP -->|"renders printable<br/>master list"| PRINT

    style boundary fill:#eff6ff,stroke:#3b82f6,stroke-width:2px
    style actors fill:none,stroke:none
    style DB fill:#f3f4f6,stroke:#6b7280
    style PRINT fill:#f3f4f6,stroke:#6b7280
```

## Interactions

| Actor | What they do | What they get back |
| --- | --- | --- |
| Employee | Files a Profile Change Request against their own 201 file; corrects and resubmits returned requests | Their own personnel record and the status of their own requests only |
| HR Staff | Verifies submitted requests against supporting documents; maintains Personnel, Office and Position master data | Queue of requests awaiting verification; full personnel roster; dashboard |
| HR Approver | Approves, returns or rejects verified requests; maintains master data; deletes and restores records | Queue of requests awaiting decision; dashboard; printable master list |

A request cannot reach the HR Approver until HR Staff has verified it. That
gate is what separates the two HR roles.

Every write carries the acting user with it: each record stores the user who
created it and the user who last changed it, and deletion hides a record rather
than destroying it. Restoring one is the HR Approver's call alone.
