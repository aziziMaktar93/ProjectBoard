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
                <tr><th>Board</th><th>Completed</th><th>Total tasks</th><th style="width: 160px;">Progress</th></tr>
            </thead>
            <tbody>
                @foreach ($tasksByBoard as $row)
                    @php
                        $boardCompleted = $row['completed'] ?? 0;
                        $boardPercent = $row['count'] > 0 ? round($boardCompleted / $row['count'] * 100) : 0;
                    @endphp
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $boardCompleted }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>
                            <span class="progress-track">
                                <span class="progress-fill {{ $boardPercent === 100 ? 'complete' : '' }}" style="width: {{ $boardPercent }}%"></span>
                            </span>
                            <span class="progress-percent">{{ $boardPercent }}%</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <span class="section-title">Completion trend (last 14 days)</span>
    @if ($completionTrend->isEmpty() || $completionTrend->sum('count') === 0)
        <p class="muted">No checklist items completed in this period.</p>
    @else
        @php $trendMax = max(1, $completionTrend->max('count')); @endphp
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                @foreach ($completionTrend as $point)
                    <td style="text-align: center; vertical-align: bottom; padding: 2px;">
                        <div style="height: {{ max(2, round($point['count'] / $trendMax * 40)) }}px; background-color: #6366f1; border-radius: 2px;"></div>
                    </td>
                @endforeach
            </tr>
            <tr>
                @foreach ($completionTrend as $point)
                    <td style="text-align: center; padding: 1px; font-size: 8px; color: #4b5563; font-weight: 700;">{{ $point['count'] }}</td>
                @endforeach
            </tr>
            <tr>
                @foreach ($completionTrend as $point)
                    <td style="text-align: center; padding: 1px; font-size: 7px; color: #9ca3af;">{{ $point['date'] }}</td>
                @endforeach
            </tr>
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
