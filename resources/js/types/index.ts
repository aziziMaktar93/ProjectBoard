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

export interface Card {
    id: number;
    board_list_id: number;
    name: string;
    description: string | null;
    position: number;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface BoardList {
    id: number;
    board_id: number;
    name: string;
    position: number;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    cards: Card[];
}

export interface Board {
    id: number;
    user_id: number;
    name: string;
    background_color: string | null;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    lists?: BoardList[];
}
