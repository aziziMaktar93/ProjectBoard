<script setup lang="ts">
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatTimestamp } from '@/lib/activitySentence';
import type { AppNotification, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const notifications = computed(() => page.props.notifications);

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
    <Popover>
        <PopoverTrigger as-child>
            <button
                type="button"
                class="relative flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground"
                aria-label="Notifications"
            >
                <Bell class="size-4" />
                <span
                    v-if="notifications && notifications.unreadCount > 0"
                    class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                >
                    {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
                </span>
            </button>
        </PopoverTrigger>
        <PopoverContent class="w-80" align="start">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-semibold">Notifications</p>
                <button
                    v-if="notifications && notifications.unreadCount > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllAsRead"
                >
                    Mark all as read
                </button>
            </div>

            <p v-if="!notifications || !notifications.recent.length" class="py-6 text-center text-sm text-muted-foreground">
                No notifications yet.
            </p>
            <ul v-else class="max-h-80 space-y-1 overflow-y-auto">
                <li v-for="notification in notifications.recent" :key="notification.id">
                    <Link
                        :href="route('notifications.open', notification.id)"
                        class="block rounded-md p-2 text-sm hover:bg-accent"
                        :class="!notification.read_at ? 'bg-accent/60' : ''"
                    >
                        <span>{{ sentenceFor(notification) }}</span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">{{ formatTimestamp(notification.created_at) }}</span>
                    </Link>
                </li>
            </ul>

            <Link
                :href="route('notifications.index')"
                class="mt-2 block rounded-md p-2 text-center text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
            >
                View all notifications
            </Link>
        </PopoverContent>
    </Popover>
</template>
