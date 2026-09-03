<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Activity Log Report</title>
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
    <h1>Activity Log Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($activities->isEmpty())
        <p class="muted">No activity in scope.</p>
    @else
        <table>
            <thead>
                <tr><th width="18%">When</th><th width="20%">Board</th><th width="15%">User</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @foreach ($activities as $activity)
                    <tr>
                        <td>{{ $activity->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $activity->card->boardList->board->name ?? '—' }}</td>
                        <td>{{ $activity->user->name }}</td>
                        <td>{{ $describer->describe($activity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
