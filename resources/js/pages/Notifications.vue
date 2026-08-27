<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatTimestamp } from '@/lib/activitySentence';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppNotification, BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AtSign, Check, ListChecks, Search, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    notificationList: Paginated<AppNotification>;
    filters: {
        status: 'all' | 'unread' | 'read';
        search: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

const STATUS_OPTIONS: { value: 'all' | 'unread' | 'read'; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'read', label: 'Read' },
];

const search = ref(props.filters.search);

const hasActiveFilters = computed(() => props.filters.status !== 'all' || props.filters.search !== '');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let skipNextSearchWatch = false;

watch(search, (value) => {
    if (skipNextSearchWatch) {
        skipNextSearchWatch = false;
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        reload({ search: value, status: props.filters.status });
    }, 300);
});

function reload(query: { search: string; status: string }) {
    router.get(route('notifications.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setStatus(status: 'all' | 'unread' | 'read') {
    reload({ search: search.value, status });
}

function clearFilters() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    skipNextSearchWatch = true;
    search.value = '';
    reload({ search: '', status: 'all' });
}

function sentenceFor(notification: AppNotification): string {
    if (notification.type === 'mention') {
        return `${notification.data.actor_name} mentioned you on "${notification.data.card_name}"`;
    }

    if (notification.type === 'checklist_item_assigned') {
        return `${notification.data.actor_name} assigned you to "${notification.data.item_name}" on "${notification.data.card_name}"`;
    }

    return `${notification.data.actor_name} assigned you to "${notification.data.card_name}"`;
}

function markAsRead(notification: AppNotification) {
    router.patch(
        route('notifications.read', notification.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function markAllAsRead() {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
}

function goToPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">Notifications</h1>
                    <p class="text-sm text-muted-foreground">Mentions and assignments across every board you belong to.</p>
                </div>
                <Button variant="outline" size="sm" @click="markAllAsRead">Mark all as read</Button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 rounded-md border border-border p-1">
                    <button
                        v-for="option in STATUS_OPTIONS"
                        :key="option.value"
                        type="button"
                        class="rounded px-2.5 py-1 text-sm transition"
                        :class="filters.status === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                        @click="setStatus(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative w-full max-w-xs">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Search notifications..." class="pl-8" />
                    </div>

                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                        <X class="size-3.5" />
                        Clear filters
                    </Button>
                </div>
            </div>

            <p v-if="!notificationList.data.length" class="text-sm text-muted-foreground">No notifications match.</p>

            <ul v-else class="max-w-2xl space-y-2">
                <li v-for="notification in notificationList.data" :key="notification.id" class="flex items-start gap-2">
                    <Link
                        :href="route('notifications.open', notification.id)"
                        class="flex flex-1 items-start gap-3 rounded-lg border border-neutral-200 p-3 hover:bg-accent dark:border-neutral-700"
                        :class="!notification.read_at ? 'bg-accent/60' : ''"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                            :class="{
                                'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400': notification.type === 'mention',
                                'bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-400':
                                    notification.type === 'checklist_item_assigned',
                                'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400':
                                    notification.type === 'card_assigned',
                            }"
                        >
                            <AtSign v-if="notification.type === 'mention'" class="size-4" />
                            <ListChecks v-else-if="notification.type === 'checklist_item_assigned'" class="size-4" />
                            <UserPlus v-else class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">{{ sentenceFor(notification) }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ formatTimestamp(notification.created_at) }}</p>
                        </div>
                        <span v-if="!notification.read_at" class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-500" />
                    </Link>

                    <button
                        v-if="!notification.read_at"
                        type="button"
                        title="Mark as read"
                        class="mt-3 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground"
                        @click="markAsRead(notification)"
                    >
                        <Check class="size-4" />
                    </button>
                </li>
            </ul>

            <div v-if="notificationList.last_page > 1" class="flex flex-wrap items-center gap-1">
                <button
                    v-for="link in notificationList.links"
                    :key="link.label"
                    type="button"
                    v-html="link.label"
                    class="rounded-md px-3 py-1.5 text-sm transition"
                    :disabled="!link.url"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : link.url
                              ? 'text-muted-foreground hover:bg-accent'
                              : 'cursor-not-allowed text-muted-foreground/40'
                    "
                    @click="goToPage(link.url)"
                />
            </div>
        </div>
    </AppLayout>
</template>
