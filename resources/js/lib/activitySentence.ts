import type { CardActivity } from '@/types';

export function sentenceFor(activity: CardActivity): string {
    const data = activity.data ?? {};

    switch (activity.type) {
        case 'moved':
            return `moved this card from ${data.from_list} to ${data.to_list}`;
        case 'checklist_item_completed':
            return `completed ${data.item_name} on this card`;
        case 'checklist_item_uncompleted':
            return `marked ${data.item_name} incomplete on this card`;
        case 'member_added':
            return `added ${data.member_name} to this card`;
        case 'member_removed':
            return `removed ${data.member_name} from this card`;
        case 'label_added':
            return `added the ${data.label_name} label to this card`;
        case 'label_removed':
            return `removed the ${data.label_name} label from this card`;
        case 'attachment_added':
            return `added ${data.attachment_name} to this card`;
        case 'attachment_removed':
            return `removed ${data.attachment_name} from this card`;
        case 'archived':
            return 'archived this card';
        case 'restored':
            return 'restored this card';
        default:
            return '';
    }
}

export function formatTimestamp(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
