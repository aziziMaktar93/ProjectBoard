<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Member Performance Report</title>
    @include('reports.partials.styles')
    <style>
        .rank {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            background: #eef0f7;
            color: #4b5563;
            font-size: 9px;
            font-weight: 700;
        }
        .member-section { margin-bottom: 20px; }
        .member-heading {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #e5e7eb;
        }
        .type-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <h1>Member Performance Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($rows->isEmpty())
        <p class="muted">No assigned tasks in scope.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="6%">#</th>
                    <th width="28%">Member</th>
                    <th width="16%">Completed</th>
                    <th width="16%">Pending</th>
                    <th width="16%">Overdue</th>
                    <th width="18%">Avg days late</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td><span class="rank">{{ $index + 1 }}</span></td>
                        <td>{{ $row['user']->name }}</td>
                        <td><span class="pill pill-good">{{ $row['completed'] }}</span></td>
                        <td>
                            @if ($row['pending'] > 0)
                                <span class="pill pill-neutral">{{ $row['pending'] }}</span>
                            @else
                                <span class="pill pill-neutral">0</span>
                            @endif
                        </td>
                        <td>
                            @if ($row['overdue'] > 0)
                                <span class="pill pill-bad">{{ $row['overdue'] }}</span>
                            @else
                                <span class="pill pill-neutral">0</span>
                            @endif
                        </td>
                        <td>{{ $row['avg_days_late'] !== null ? $row['avg_days_late'].'d' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($rows->isNotEmpty())
        @php
            $statusPill = ['Done' => 'pill-good', 'Overdue' => 'pill-bad', 'Pending' => 'pill-neutral'];
        @endphp

        <span class="section-title">Task details</span>
        @foreach ($rows as $row)
            <div class="member-section">
                <p class="member-heading">{{ $row['user']->name }}</p>

                @if ($row['tasks']->isEmpty())
                    <p class="muted">No assigned tasks.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr>
                                <th width="30%">Task</th>
                                <th width="14%">Type</th>
                                <th width="14%">Workspace</th>
                                <th width="14%">Board</th>
                                <th width="12%">Due</th>
                                <th width="12%">Completed</th>
                                <th width="14%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($row['tasks'] as $task)
                                <tr>
                                    <td>{{ $task['name'] }}</td>
                                    <td><span class="type-tag">{{ $task['type'] }}</span></td>
                                    <td>{{ $task['workspace_name'] }}</td>
                                    <td>{{ $task['board_name'] }}</td>
                                    <td>{{ $task['due_date'] ? \Carbon\Carbon::parse($task['due_date'])->format('M j, Y') : '—' }}</td>
                                    <td>{{ $task['completed_at'] ? $task['completed_at']->format('M j, Y') : '—' }}</td>
                                    <td><span class="pill {{ $statusPill[$task['status']] }}">{{ $task['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
