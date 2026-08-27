<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f1f1f;
            font-size: 12px;
            margin: 0;
            padding: 24px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        .subtitle {
            color: #6b7280;
            margin: 0 0 20px;
        }

        h2 {
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 24px 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            color: #6b7280;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        .stat-grid {
            width: 100%;
        }

        .stat-grid td {
            border: none;
            padding: 0 12px 0 0;
        }

        .stat-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            color: #6b7280;
            font-size: 10px;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
        }

        .footer {
            margin-top: 24px;
            color: #9ca3af;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <h1>Dashboard Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    <table class="stat-grid">
        <tr>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['total'] }}</span>
                    <span class="stat-label">Total tasks</span>
                </div>
            </td>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['completed'] }}</span>
                    <span class="stat-label">Completed</span>
                </div>
            </td>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['overdue'] }}</span>
                    <span class="stat-label">Overdue</span>
                </div>
            </td>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['dueSoon'] }}</span>
                    <span class="stat-label">Due within 7 days</span>
                </div>
            </td>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['checklistProgress'] !== null ? $stats['checklistProgress'].'%' : '—' }}</span>
                    <span class="stat-label">Checklist progress</span>
                </div>
            </td>
        </tr>
        <tr>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['checklistItemsOverdue'] }}</span>
                    <span class="stat-label">Checklist items overdue</span>
                </div>
            </td>
            <td width="20%">
                <div class="stat-box">
                    <span class="stat-value">{{ $stats['checklistItemsDueSoon'] }}</span>
                    <span class="stat-label">Checklist items due soon</span>
                </div>
            </td>
        </tr>
    </table>

    <h2>Tasks by board</h2>
    @if ($tasksByBoard->isEmpty())
        <p class="muted">No tasks in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Board</th><th>Open tasks</th></tr>
            </thead>
            <tbody>
                @foreach ($tasksByBoard as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($tasksByList !== null)
        <h2>Tasks by list</h2>
        @if ($tasksByList->isEmpty())
            <p class="muted">No lists in scope.</p>
        @else
            <table>
                <thead>
                    <tr><th>List</th><th>Tasks</th></tr>
                </thead>
                <tbody>
                    @foreach ($tasksByList as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ $row['count'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>Workload per member</h2>
    @if ($workload->isEmpty())
        <p class="muted">No assigned tasks in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Member</th><th>Assigned tasks</th></tr>
            </thead>
            <tbody>
                @foreach ($workload as $row)
                    <tr><td>{{ $row['user']->name }}</td><td>{{ $row['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Recent activity</h2>
    @if ($recentActivity->isEmpty())
        <p class="muted">No recent activity in scope.</p>
    @else
        <table>
            <thead>
                <tr><th width="20%">When</th><th width="20%">Board</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @foreach ($recentActivity as $activity)
                    <tr>
                        <td>{{ $activity['created_at']->format('M j, g:i A') }}</td>
                        <td>{{ $activity['board_name'] ?? '—' }}</td>
                        <td>{{ $activity['user_name'] }} {{ $activity['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
