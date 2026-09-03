<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Checklist Completion Timeline Report</title>
    @include('reports.partials.styles')
    <style>
        .workspace-section { margin-bottom: 26px; }
        .workspace-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #3b5bfd;
            margin: 0 0 8px;
        }
        .board-section { margin-bottom: 22px; margin-left: 4px; }
        .board-title { margin: 0 0 10px; }
        .card-block { margin: 0 0 14px 4px; padding-left: 10px; border-left: 2px solid #e5e7eb; }
        .card-title { font-size: 13px; font-weight: 700; color: #1f2937; margin: 0 0 6px; }
        .checklist-block { margin: 0 0 10px; }
        .checklist-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px 4px 0 0;
            display: block;
        }
    </style>
</head>
<body>
    <h1>Checklist Completion Timeline Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($grouped->isEmpty())
        <p class="muted">No checklist items with a due date or completion date in scope.</p>
    @else
        @php
            $pillClass = ['Done' => 'pill-good', 'Overdue' => 'pill-bad', 'Pending' => 'pill-neutral'];
        @endphp

        @foreach ($grouped as $workspaceName => $boards)
            <div class="workspace-section">
                <p class="workspace-title">Workspace: {{ $workspaceName }}</p>

                @foreach ($boards as $boardName => $cards)
                    <div class="board-section">
                        <p class="section-title board-title">{{ $boardName }}</p>

                        @foreach ($cards as $cardName => $checklists)
                            <div class="card-block">
                                <p class="card-title">{{ $cardName }}</p>

                                @foreach ($checklists as $checklistName => $items)
                                    <div class="checklist-block">
                                        <span class="checklist-title">{{ $checklistName }}</span>
                                        <table class="data">
                                            <thead>
                                                <tr>
                                                    <th width="28%">Item</th>
                                                    <th width="20%">Assigned</th>
                                                    <th width="14%">Due</th>
                                                    <th width="16%">Completed</th>
                                                    <th width="14%">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($items as $item)
                                                    <tr>
                                                        <td>{{ $item['name'] }}</td>
                                                        <td>
                                                            @if ($item['assignees'] !== '')
                                                                <span class="assignee">{{ $item['assignees'] }}</span>
                                                            @else
                                                                <span class="assignee-empty">Unassigned</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $item['due_date'] ? \Carbon\Carbon::parse($item['due_date'])->format('M j, Y') : '—' }}</td>
                                                        <td>{{ $item['completed_at'] ? $item['completed_at']->format('M j, Y') : '—' }}</td>
                                                        <td>
                                                            <span class="pill {{ $pillClass[$item['status']] }}">{{ $item['status'] }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
