<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Progress Report</title>
    @include('reports.partials.styles')
    <style>
        .workspace-section { margin-bottom: 26px; }
        .workspace-head {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .workspace-head-left {
            display: table-cell;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #3b5bfd;
            vertical-align: middle;
        }
        .workspace-head-right { display: table-cell; text-align: right; vertical-align: middle; white-space: nowrap; }
        .workspace-meta { color: #9ca3af; font-size: 10px; margin: -6px 0 10px; }
        .board-section { margin-bottom: 18px; }
        .board-head {
            display: table;
            width: 100%;
            background: #3b5bfd;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 8px;
        }
        .board-head-left { display: table-cell; color: #ffffff; font-weight: 700; font-size: 13px; vertical-align: middle; }
        .board-head-right { display: table-cell; text-align: right; vertical-align: middle; white-space: nowrap; }
        .board-head-right .progress-track { background: rgba(255,255,255,0.35); }
        .board-head-right .progress-fill { background-color: #ffffff !important; }
        .board-head-right .progress-percent { color: #ffffff; }
        .board-meta { color: #9ca3af; font-size: 10px; margin: -4px 0 8px 2px; }
    </style>
</head>
<body>
    <h1>Progress Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($grouped->isEmpty())
        <p class="muted">No cards in scope.</p>
    @else
        @php
            $percentClass = fn (?int $percent) => match (true) {
                $percent === 100 => 'complete',
                $percent !== null && $percent < 40 => 'low',
                default => '',
            };
        @endphp

        @foreach ($grouped as $workspaceName => $workspace)
            <div class="workspace-section">
                <div class="workspace-head">
                    <div class="workspace-head-left">Workspace: {{ $workspaceName }}</div>
                    <div class="workspace-head-right">
                        <span class="progress-track">
                            <span class="progress-fill {{ $percentClass($workspace['percent']) }}" style="width: {{ $workspace['percent'] ?? 0 }}%"></span>
                        </span>
                        <span class="progress-percent">{{ $workspace['percent'] !== null ? $workspace['percent'].'%' : 'N/A' }}</span>
                    </div>
                </div>
                <p class="workspace-meta">{{ $workspace['checked'] }} of {{ $workspace['total'] }} checklist items done across this workspace</p>

                @foreach ($workspace['boards'] as $boardName => $board)
                    <div class="board-section">
                        <div class="board-head">
                            <div class="board-head-left">{{ $boardName }}</div>
                            <div class="board-head-right">
                                <span class="progress-track">
                                    <span class="progress-fill {{ $board['percent'] === 100 ? 'complete' : '' }}" style="width: {{ $board['percent'] ?? 0 }}%"></span>
                                </span>
                                <span class="progress-percent">{{ $board['percent'] !== null ? $board['percent'].'%' : 'N/A' }}</span>
                            </div>
                        </div>
                        <p class="board-meta">{{ $board['checked'] }} of {{ $board['total'] }} checklist items done</p>

                        @if ($board['cards']->isEmpty())
                            <p class="muted">No cards in this board.</p>
                        @else
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th width="46%">Card</th>
                                        <th width="18%">Checklist items</th>
                                        <th width="36%">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($board['cards'] as $card)
                                        <tr>
                                            <td>{{ $card['name'] }}</td>
                                            <td>{{ $card['total'] > 0 ? "{$card['checked']}/{$card['total']}" : '—' }}</td>
                                            <td>
                                                @if ($card['percent'] === null)
                                                    <span class="muted">No checklist</span>
                                                @else
                                                    <span class="progress-track">
                                                        <span class="progress-fill {{ $percentClass($card['percent']) }}" style="width: {{ $card['percent'] }}%"></span>
                                                    </span>
                                                    <span class="progress-percent">{{ $card['percent'] }}%</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    <div class="methodology">
        <strong>How this is calculated:</strong> progress % = checklist items checked &divide; total checklist items, per board and per card.
        Cards without any checklist items show "No checklist" and are excluded from the percentage but still counted in the board's card list.
        Archived cards and lists are excluded. Bar color: red under 40%, blue 40&ndash;99%, green at 100%.
    </div>

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
