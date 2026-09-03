<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Activity Log Report</title>
    @include('reports.partials.styles')
    <style>
        .when { color: #6b7280; white-space: nowrap; }
        .board-tag { display: inline-block; background: #eef0f7; color: #3b5bfd; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; }
    </style>
</head>
<body>
    <h1>Activity Log Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($activities->isEmpty())
        <p class="muted">No activity in scope.</p>
    @else
        <table class="data">
            <thead>
                <tr><th width="16%">When</th><th width="12%">Workspace</th><th width="16%">Board</th><th width="14%">User</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @foreach ($activities as $activity)
                    @php($board = $activity->card->boardList->board ?? null)
                    <tr>
                        <td class="when">{{ $activity->created_at->timezone('Asia/Kuala_Lumpur')->format('M j, Y g:i A') }}</td>
                        <td>{{ $board?->workspace->name ?? '—' }}</td>
                        <td>
                            @if ($board)
                                <span class="board-tag">{{ $board->name }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
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
