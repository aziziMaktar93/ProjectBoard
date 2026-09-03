<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>On-Time vs Late Completion Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        h2 { font-size: 14px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin: 24px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .stat-grid { width: 100%; }
        .stat-grid td { border: none; padding: 0 12px 0 0; }
        .stat-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .stat-value { font-size: 18px; font-weight: 700; display: block; }
        .stat-label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>On-Time vs Late Completion Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    <table class="stat-grid">
        <tr>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $totalCompleted }}</span>
                    <span class="stat-label">Compared</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $onTimeCount }}</span>
                    <span class="stat-label">On time</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $lateCount }}</span>
                    <span class="stat-label">Late</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $onTimePercent !== null ? $onTimePercent.'%' : '—' }}</span>
                    <span class="stat-label">On-time rate</span>
                </div>
            </td>
        </tr>
    </table>

    <h2>Late items</h2>
    @if ($lateDetails->isEmpty())
        <p class="muted">No late items in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Item</th><th>Checklist</th><th>Board</th><th>Due</th><th>Completed</th><th>Days late</th></tr>
            </thead>
            <tbody>
                @foreach ($lateDetails as $row)
                    <tr>
                        <td>{{ $row['item_name'] }}</td>
                        <td>{{ $row['checklist_name'] }}</td>
                        <td>{{ $row['board_name'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('M j, Y') }}</td>
                        <td>{{ $row['completed_at']->format('M j, Y') }}</td>
                        <td>{{ $row['days_late'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
