<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Checklist Completion Timeline Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        h2 { font-size: 16px; margin: 20px 0 6px; }
        h3 { font-size: 13px; margin: 12px 0 4px; color: #374151; }
        h4 { font-size: 11px; text-transform: uppercase; color: #6b7280; margin: 8px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { text-align: left; padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 10px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Checklist Completion Timeline Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($grouped->isEmpty())
        <p class="muted">No checklist items with a due date or completion date in scope.</p>
    @else
        @foreach ($grouped as $boardName => $cards)
            <h2>{{ $boardName }}</h2>
            @foreach ($cards as $cardName => $checklists)
                <h3>{{ $cardName }}</h3>
                @foreach ($checklists as $checklistName => $items)
                    <h4>{{ $checklistName }}</h4>
                    <table>
                        <thead>
                            <tr><th>Item</th><th>Due</th><th>Completed</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ $item['name'] }}</td>
                                    <td>{{ $item['due_date'] ? \Carbon\Carbon::parse($item['due_date'])->format('M j, Y') : '—' }}</td>
                                    <td>{{ $item['completed_at'] ? $item['completed_at']->format('M j, Y') : '—' }}</td>
                                    <td>{{ $item['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        @endforeach
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
