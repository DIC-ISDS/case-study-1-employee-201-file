# 3. Process Flow Diagram

End-to-end flow of a Profile Change Request, including the alternate paths.

```mermaid
flowchart TD
    START([Start]) --> CREATE["Employee creates<br/>Profile Change Request"]
    CREATE --> ADD["Employee adds one line<br/>per field to change"]
    ADD --> DRAFT{{"Status: DRAFT"}}
    DRAFT --> SUBMIT["Employee submits"]

    SUBMIT --> SNAP["System snapshots the<br/>current value of each field<br/>and writes a history row"]
    SNAP --> PENDING{{"Status: PENDING"}}

    PENDING --> REVIEW["HR Staff reviews against<br/>supporting documents"]
    REVIEW --> D1{"Documents<br/>in order?"}

    D1 -->|No| RET1["HR Staff returns<br/>with remarks"]
    D1 -->|Yes| VERIFY["HR Staff verifies<br/><i>reviewed_by / reviewed_at set</i>"]

    VERIFY --> GATE{{"Status: PENDING<br/>+ verified"}}
    GATE --> DECIDE["HR Approver opens<br/>the request"]

    DECIDE --> D2{"Decision?"}
    D2 -->|Approve| APPLY["System applies every item<br/>to the personnel record"]
    D2 -->|Return| RET2["HR Approver returns<br/>with remarks"]
    D2 -->|Reject| REJ{{"Status: REJECTED"}}

    APPLY --> D3{"Does the request change<br/>office or position?"}
    D3 -->|No| APPROVED{{"Status: APPROVED"}}
    D3 -->|Yes| TRANSFER["End the current active primary<br/>appointment, open the new one<br/><i>single transaction</i>"]

    TRANSFER --> RULE{"Would this leave two<br/>active primary appointments?"}
    RULE -->|"Yes — blocked"| VIOLATION["/ Business rule violation:<br/>nothing is written /"]
    RULE -->|No| APPROVED

    RET1 --> RETURNED{{"Status: RETURNED"}}
    RET2 --> RETURNED
    RETURNED --> CORRECT["Employee corrects<br/>the request"]
    CORRECT --> SUBMIT

    APPROVED --> END([End])
    REJ --> END
    VIOLATION --> DECIDE

    style DRAFT fill:#f3f4f6,stroke:#6b7280
    style PENDING fill:#fef3c7,stroke:#d97706
    style GATE fill:#fef3c7,stroke:#d97706
    style RETURNED fill:#dbeafe,stroke:#2563eb
    style APPROVED fill:#dcfce7,stroke:#16a34a
    style REJ fill:#fee2e2,stroke:#dc2626
    style VIOLATION fill:#fee2e2,stroke:#dc2626
    style RULE fill:#fff7ed,stroke:#ea580c
```

## Decision points

| # | Decision | Taken by | Outcomes |
| --- | --- | --- | --- |
| 1 | Are the supporting documents in order? | HR Staff | Verify → on to the approver; Return → back to the employee |
| 2 | Approve, return or reject? | HR Approver | Approve applies the changes; Return reopens it for the employee; Reject ends it |
| 3 | Would this create a second active primary appointment? | System | Blocked with a message; nothing is written |

## Status transitions

| From | Action | To | Who |
| --- | --- | --- | --- |
| — | Create | Draft | Employee |
| Draft | Submit | Pending | Employee |
| Pending | Verify | Pending *(verified)* | HR Staff |
| Pending | Return for revision | Returned | HR Staff or HR Approver |
| Pending *(verified)* | Approve | Approved | HR Approver |
| Pending *(verified)* | Reject | Rejected | HR Approver |
| Returned | Resubmit | Pending *(verification reset)* | Employee |

Approved and Rejected are terminal. Resubmitting a returned request clears the
earlier verification, so review starts over rather than carrying a stale
approval forward.
