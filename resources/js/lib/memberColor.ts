/**
 * Deterministic per-user accent color so avatars stay visually distinct and
 * stable across renders (same user always gets the same color) without
 * needing to store a color on the User model.
 */
const MEMBER_PALETTE = [
    '#0079BF',
    '#DE350B',
    '#519839',
    '#89609E',
    '#CD5A91',
    '#00AECC',
    '#D29034',
    '#4BBF6B',
    '#F2711C',
    '#6554C0',
];

export function colorForUser(userId: number): string {
    return MEMBER_PALETTE[userId % MEMBER_PALETTE.length];
}
