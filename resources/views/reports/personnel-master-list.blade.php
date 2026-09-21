<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personnel Master List</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            font: 13px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #111827;
            background: #fff;
        }
        header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 20px; }
        h1 { margin: 0 0 4px; font-size: 20px; letter-spacing: -0.01em; }
        .meta { color: #4b5563; font-size: 12px; }
        .meta strong { color: #111827; }
        .toolbar { margin-bottom: 16px; }
        button {
            font: inherit; padding: 8px 16px; border-radius: 6px;
            border: 1px solid #d1d5db; background: #f9fafb; cursor: pointer;
        }
        button:hover { background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; }
        caption { text-align: left; font-size: 12px; color: #4b5563; padding-bottom: 8px; }
        th, td { padding: 7px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #374151; }
        tbody tr:nth-child(even) { background: #fafafa; }
        td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .muted { color: #9ca3af; }
        tfoot td { font-weight: 600; border-top: 2px solid #111827; border-bottom: none; padding-top: 10px; }
        @media print {
            body { padding: 0; font-size: 11px; }
            .toolbar { display: none; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Personnel Master List</h1>
        <p class="meta">
            Office: <strong>{{ $office?->name ?? 'All offices' }}</strong> &middot;
            Employment status: <strong>{{ $status?->getLabel() ?? ($includesResigned ? 'All' : 'All active') }}</strong> &middot;
            Generated <strong>{{ $generatedAt->format('d M Y, H:i') }}</strong>
        </p>
    </header>

    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <table>
        <caption>{{ $personnel->count() }} {{ Str::plural('record', $personnel->count()) }}</caption>
        <thead>
            <tr>
                <th scope="col">Employee no.</th>
                <th scope="col">Name</th>
                <th scope="col">Office</th>
                <th scope="col">Position</th>
                <th scope="col">Employment status</th>
                <th scope="col">Contact</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($personnel as $person)
                <tr>
                    <td class="num">{{ $person->employee_no }}</td>
                    <td>{{ $person->full_name_last_first }}</td>
                    <td>{{ $person->activePrimaryAppointment?->office?->name ?? '—' }}</td>
                    <td>{{ $person->activePrimaryAppointment?->position?->title ?? '—' }}</td>
                    <td>{{ $person->employment_status->getLabel() }}</td>
                    <td class="num">{{ $person->contact_number ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No personnel match this filter.</td></tr>
            @endforelse
        </tbody>
        @if ($personnel->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Total</td>
                    <td class="num">{{ $personnel->count() }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
