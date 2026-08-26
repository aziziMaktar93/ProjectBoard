<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatTimestamp } from '@/lib/activitySentence';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppNotification, BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AtSign, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    notifications: AppNotification[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

const unreadCount = computed(() => props.notifications.filter((notification) => !notification.read_at).length);

function sentenceFor(notification: AppNotification): string {
    if (notification.type === 'mention') {
        return `${notification.data.actor_name} mentioned you on "${notification.data.card_name}"`;
    }

    return `${notification.data.actor_name} assigned you to "${notification.data.card_name}"`;
}

function markAllAsRead() {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold">Notifications</h1>
                    <p class="text-sm text-muted-foreground">Mentions and assignments across every board you belong to.</p>
                </div>
                <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllAsRead">Mark all as read</Button>
            </div>

            <p v-if="!notifications.length" class="text-sm text-muted-foreground">You don't have any notifications yet.</p>

            <ul v-else class="max-w-2xl space-y-2">
                <li v-for="notification in notifications" :key="notification.id">
                    <Link
                        :href="route('notifications.open', notification.id)"
                        class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 hover:bg-accent dark:border-neutral-700"
                        :class="!notification.read_at ? 'bg-accent/60' : ''"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                            :class="notification.type === 'mention' ? 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'"
                        >
                            <AtSign v-if="notification.type === 'mention'" class="size-4" />
                            <UserPlus v-else class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">{{ sentenceFor(notification) }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ formatTimestamp(notification.created_at) }}</p>
                        </div>
                        <span v-if="!notification.read_at" class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-500" />
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
