import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface ChecklistItem {
    id: number;
    checklist_id: number;
    name: string;
    is_checked: boolean;
    position: number;
    created_at: string;
    updated_at: string;
}

export interface Checklist {
    id: number;
    card_id: number;
    name: string;
    position: number;
    created_at: string;
    updated_at: string;
    items: ChecklistItem[];
}

export type CardActivityType =
    | 'comment'
    | 'moved'
    | 'checklist_item_completed'
    | 'checklist_item_uncompleted'
    | 'member_added'
    | 'member_removed'
    | 'label_added'
    | 'label_removed'
    | 'attachment_added'
    | 'attachment_removed'
    | 'archived'
    | 'restored';

export interface CardActivity {
    id: number;
    card_id: number;
    user_id: number;
    type: CardActivityType;
    body: string | null;
    data: Record<string, string> | null;
    created_at: string;
    updated_at: string;
    user: User;
    card?: {
        id: number;
        name: string;
        board_list?: {
            board?: {
                id: number;
                name: string;
            };
        };
    };
}

export interface CardLabel {
    id: number;
    board_id: number;
    name: string;
    color: string;
    created_at: string;
    updated_at: string;
}

export interface CardAttachment {
    id: number;
    card_id: number;
    user_id: number;
    name: string;
    path: string;
    size: number;
    mime_type: string;
    created_at: string;
    updated_at: string;
    user?: User;
}

export interface Card {
    id: number;
    board_list_id: number;
    name: string;
    description: string | null;
    position: number;
    color: string | null;
    due_date: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    checklists?: Checklist[];
    members?: User[];
    labels?: CardLabel[];
    attachments?: CardAttachment[];
    activities?: CardActivity[];
}

export interface BoardList {
    id: number;
    board_id: number;
    name: string;
    position: number;
    color: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    cards: Card[];
}

export interface DashboardStats {
    total: number;
    completed: number;
    overdue: number;
    dueSoon: number;
    checklistProgress: number | null;
}

export interface BoardTaskCount {
    name: string;
    count: number;
}

export interface MemberWorkload {
    user: User;
    count: number;
}

export interface Workspace {
    id: number;
    owner_id: number;
    name: string;
    background_color: string | null;
    created_at: string;
    updated_at: string;
    boards_count?: number;
    members?: User[];
}

export interface Board {
    id: number;
    user_id: number;
    workspace_id: number;
    name: string;
    background_color: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    lists?: BoardList[];
    members?: User[];
    labels?: CardLabel[];
    workspace?: Workspace;
}
