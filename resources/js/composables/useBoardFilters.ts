import { isCardChecklistComplete } from '@/lib/cardCompletion';
import type { Card } from '@/types';
import { ref } from 'vue';

export interface BoardFilters {
    labelIds: number[];
    memberIds: number[];
    dueDate: 'any' | 'overdue' | 'week' | 'none';
}

export function useBoardFilters() {
    const filters = ref<BoardFilters>({ labelIds: [], memberIds: [], dueDate: 'any' });

    function cardMatchesFilters(card: Card): boolean {
        const { labelIds, memberIds, dueDate } = filters.value;

        if (labelIds.length && !(card.labels ?? []).some((label) => labelIds.includes(label.id))) {
            return false;
        }

        if (memberIds.length && !(card.members ?? []).some((member) => memberIds.includes(member.id))) {
            return false;
        }

        if (dueDate !== 'any') {
            const today = new Date().toISOString().slice(0, 10);
            const weekAhead = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

            if (dueDate === 'overdue' && !(card.due_date && card.due_date < today && !isCardChecklistComplete(card))) {
                return false;
            }

            if (dueDate === 'week' && !(card.due_date && card.due_date >= today && card.due_date <= weekAhead)) {
                return false;
            }

            if (dueDate === 'none' && card.due_date) {
                return false;
            }
        }

        return true;
    }

    return { filters, cardMatchesFilters };
}
