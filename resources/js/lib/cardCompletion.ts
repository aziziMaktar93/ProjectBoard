import type { Card } from '@/types';

export function isCardChecklistComplete(card: Card): boolean {
    const items = (card.checklists ?? []).flatMap((checklist) => checklist.items ?? []);

    return items.length > 0 && items.every((item) => item.is_checked);
}
