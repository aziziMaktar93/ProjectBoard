<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    /**
     * @param  Collection<int, Card>  $cards  Each must be eager-loaded with 'boardList.board', 'checklists.items.members', and 'members'.
     * @param  int|null  $limit  Cap tasksByBoard/workload to this many entries (top by count); null for no cap.
     * @return array{stats: array<string, mixed>, tasksByBoard: Collection, workload: Collection}
     */
    public function build(Collection $cards, ?int $limit = 8): array
    {
        $today = now()->toDateString();
        $weekAhead = now()->addDays(7)->toDateString();

        $allChecklistItems = $cards->flatMap(fn (Card $card) => $card->checklists)->flatMap(fn ($checklist) => $checklist->items);

        $isCompleted = function (Card $card): bool {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);

            return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);
        };

        $stats = [
            'total' => $cards->count(),
            'completed' => $cards->filter($isCompleted)->count(),
            'overdue' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date < $today && ! $isCompleted($card))->count(),
            'dueSoon' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date >= $today && $card->due_date <= $weekAhead)->count(),
            'checklistProgress' => $allChecklistItems->isEmpty()
                ? null
                : (int) round($allChecklistItems->filter(fn ($item) => $item->is_checked)->count() / $allChecklistItems->count() * 100),
            'checklistItemsOverdue' => $allChecklistItems->filter(fn ($item) => $item->due_date && $item->due_date < $today && ! $item->is_checked)->count(),
            'checklistItemsDueSoon' => $allChecklistItems->filter(fn ($item) => $item->due_date && ! $item->is_checked && $item->due_date >= $today && $item->due_date <= $weekAhead)->count(),
        ];

        $tasksByBoard = $cards
            ->groupBy(fn (Card $card) => $card->boardList->board->name)
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->when($limit !== null, fn ($collection) => $collection->take($limit))
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();

        $workload = $cards
            ->flatMap(fn (Card $card) => $card->members)
            ->merge($allChecklistItems->flatMap(fn ($item) => $item->members))
            ->groupBy('id')
            ->map(fn ($group) => ['user' => $group->first(), 'count' => $group->count()])
            ->sortByDesc('count')
            ->when($limit !== null, fn ($collection) => $collection->take($limit))
            ->values();

        return [
            'stats' => $stats,
            'tasksByBoard' => $tasksByBoard,
            'workload' => $workload,
        ];
    }
}
