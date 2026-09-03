<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>On-Time vs Late Completion Report</title>
    @include('reports.partials.styles')
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

    <span class="section-title">Late items</span>
    @if ($lateDetails->isEmpty())
        <p class="muted">No late items in scope.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="16%">Item</th>
                    <th width="13%">Checklist</th>
                    <th width="12%">Workspace</th>
                    <th width="12%">Board</th>
                    <th width="14%">Assigned</th>
                    <th width="11%">Due</th>
                    <th width="12%">Completed</th>
                    <th width="10%">Days late</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lateDetails as $row)
                    <tr>
                        <td>{{ $row['item_name'] }}</td>
                        <td>{{ $row['checklist_name'] }}</td>
                        <td>{{ $row['workspace_name'] }}</td>
                        <td>{{ $row['board_name'] }}</td>
                        <td>
                            @if ($row['assignees'] !== '')
                                <span class="assignee">{{ $row['assignees'] }}</span>
                            @else
                                <span class="assignee-empty">Unassigned</span>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('M j, Y') }}</td>
                        <td>{{ $row['completed_at']->format('M j, Y') }}</td>
                        <td><span class="pill pill-bad">{{ $row['days_late'] }}d late</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <span class="section-title">On-time items</span>
    @if ($onTimeDetails->isEmpty())
        <p class="muted">No on-time items in scope.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="18%">Item</th>
                    <th width="14%">Checklist</th>
                    <th width="13%">Workspace</th>
                    <th width="13%">Board</th>
                    <th width="16%">Assigned</th>
                    <th width="13%">Due</th>
                    <th width="13%">Completed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($onTimeDetails as $row)
                    <tr>
                        <td>{{ $row['item_name'] }}</td>
                        <td>{{ $row['checklist_name'] }}</td>
                        <td>{{ $row['workspace_name'] }}</td>
                        <td>{{ $row['board_name'] }}</td>
                        <td>
                            @if ($row['assignees'] !== '')
                                <span class="assignee">{{ $row['assignees'] }}</span>
                            @else
                                <span class="assignee-empty">Unassigned</span>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('M j, Y') }}</td>
                        <td>{{ $row['completed_at']->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="methodology">
        <strong>How this is calculated:</strong> only checklist items with both a due date and a completion date are compared.
        An item is <strong>on time</strong> if its completion date is on or before its due date, otherwise it's <strong>late</strong>.
        On-time rate = on time &divide; compared &times; 100%. Days late = due date to completion date, in whole days.
        Items without a due date, or not yet completed, are excluded. Archived cards are excluded.
    </div>

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
