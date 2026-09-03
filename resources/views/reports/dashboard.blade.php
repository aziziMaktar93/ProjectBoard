<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Report</title>
    @include('reports.partials.styles')
    <style>
        .stat-box.warn { border-left-color: #b3441f; }
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
                <div class="stat-box {{ $stats['overdue'] > 0 ? 'warn' : '' }}">
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
                <div class="stat-box {{ $stats['checklistItemsOverdue'] > 0 ? 'warn' : '' }}">
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

    <span class="section-title">Tasks by board</span>
    @if ($tasksByBoard->isEmpty())
        <p class="muted">No tasks in scope.</p>
    @else
        <table class="data">
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
        <span class="section-title">Tasks by list</span>
        @if ($tasksByList->isEmpty())
            <p class="muted">No lists in scope.</p>
        @else
            <table class="data">
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

    <span class="section-title">Workload per member</span>
    @if ($workload->isEmpty())
        <p class="muted">No assigned tasks in scope.</p>
    @else
        <table class="data">
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

    <span class="section-title">Recent activity</span>
    @if ($recentActivity->isEmpty())
        <p class="muted">No recent activity in scope.</p>
    @else
        <table class="data">
            <thead>
                <tr><th width="20%">When</th><th width="20%">Board</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @foreach ($recentActivity as $activity)
                    <tr>
                        <td>{{ $activity['created_at']->timezone('Asia/Kuala_Lumpur')->format('M j, g:i A') }}</td>
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
