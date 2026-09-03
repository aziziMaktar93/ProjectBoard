<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Member Performance Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Member Performance Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($rows->isEmpty())
        <p class="muted">No assigned tasks in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Member</th><th>Completed</th><th>Overdue</th><th>Avg days late</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ $row['completed'] }}</td>
                        <td>{{ $row['overdue'] }}</td>
                        <td>{{ $row['avg_days_late'] !== null ? $row['avg_days_late'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
