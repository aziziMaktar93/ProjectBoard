<script setup lang="ts">
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useMonthCalendar } from '@/composables/useMonthCalendar';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BoardEvent, BreadcrumbItem, SharedData, User } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    cards: { id: number; name: string; due_date: string; color: string | null; board_id: number }[];
    events: BoardEvent[];
    boards: { id: number; name: string; workspace_name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendar', href: '/calendar' }];

const { WEEKDAYS, monthLabel, todayKey, gridDays, goToMonth, goToToday } = useMonthCalendar();

const currentUserId = usePage<SharedData>().props.auth.user.id;

const boardsById = computed(() => new Map(props.boards.map((board) => [board.id, board])));

function boardLabel(boardId: number | null): string {
    if (boardId === null) {
        return 'General';
    }

    return boardsById.value.get(boardId)?.name ?? '';
}

function boardTitle(boardId: number | null): string {
    if (boardId === null) {
        return 'General (not tied to a board)';
    }

    const board = boardsById.value.get(boardId);

    return board ? `${board.workspace_name} / ${board.name}` : '';
}

function eventLabel(event: { board_id: number | null; user?: User }): string {
    if (event.board_id === null) {
        return event.user?.id === currentUserId ? 'General' : `General · ${event.user?.name ?? 'Unknown'}`;
    }

    return boardLabel(event.board_id);
}

function eventTitle(event: { board_id: number | null; user?: User }): string {
    if (event.board_id === null) {
        return event.user?.id === currentUserId ? 'General (not tied to a board)' : `General event by ${event.user?.name ?? 'Unknown'}`;
    }

    return boardTitle(event.board_id);
}

function canEditEvent(event: BoardEvent): boolean {
    return event.board_id !== null || event.user_id === currentUserId;
}

function cardsForDay(dateKey: string) {
    return props.cards.filter((card) => card.due_date === dateKey);
}

function eventsForDay(dateKey: string) {
    return props.events.filter((event) => dateKey >= event.start_date && dateKey <= (event.end_date ?? event.start_date));
}

const openAddPopover = ref<string | null>(null);

const GENERAL = 'general';

const addEventForm = useForm({
    board_id: GENERAL as number | string,
    name: '',
    start_date: '',
    end_date: '',
    color: null as string | null,
});

function startAddEvent(dateKey: string) {
    addEventForm.reset();
    addEventForm.board_id = GENERAL;
    addEventForm.start_date = dateKey;
    addEventForm.color = null;
}

function submitAddEvent() {
    if (!addEventForm.name.trim() || !addEventForm.board_id) {
        return;
    }

    const url = addEventForm.board_id === GENERAL ? route('events.store') : route('board-events.store', addEventForm.board_id);

    addEventForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            addEventForm.reset();
            openAddPopover.value = null;
        },
    });
}

const openEditPopover = ref<string | null>(null);

const editEventForm = useForm({
    name: '',
    start_date: '',
    end_date: '',
    color: null as string | null,
});

function startEditEvent(event: BoardEvent) {
    editEventForm.reset();
    editEventForm.name = event.name;
    editEventForm.start_date = event.start_date;
    editEventForm.end_date = event.end_date ?? '';
    editEventForm.color = event.color;
}

function submitEditEvent(eventId: number) {
    if (!editEventForm.name.trim()) {
        return;
    }

    editEventForm.patch(route('board-events.update', eventId), {
        preserveScroll: true,
        onSuccess: () => {
            openEditPopover.value = null;
        },
    });
}

function deleteEvent(eventId: number) {
    if (!confirm('Delete this event? This cannot be undone.')) {
        return;
    }

    router.delete(route('board-events.destroy', eventId), {
        preserveScroll: true,
        onSuccess: () => {
            openEditPopover.value = null;
        },
    });
}
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold">{{ monthLabel }}</h1>
                    <p class="text-sm text-muted-foreground">Due dates and events across every board you belong to.</p>
                </div>

                <div class="flex items-center gap-1">
                    <Button variant="outline" size="sm" class="size-8 p-0" aria-label="Previous month" @click="goToMonth(-1)">
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="goToToday">Today</Button>
                    <Button variant="outline" size="sm" class="size-8 p-0" aria-label="Next month" @click="goToMonth(1)">
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>

            <div
                v-if="boards.length === 0"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-12 text-center dark:border-neutral-700"
            >
                <p class="text-sm text-muted-foreground">No boards yet — create a workspace and board to see your calendar here.</p>
                <Button as-child size="sm">
                    <Link :href="route('workspaces.index')">Go to Workspaces</Link>
                </Button>
            </div>

            <div v-else class="-mx-4 overflow-x-auto px-4 pb-2">
                <div
                    class="grid min-w-[700px] grid-cols-7 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200 dark:border-neutral-700 dark:bg-neutral-700"
                >
                    <div
                        v-for="weekday in WEEKDAYS"
                        :key="weekday"
                        class="bg-neutral-50 p-2 text-center text-xs font-semibold text-muted-foreground dark:bg-neutral-900"
                    >
                        {{ weekday }}
                    </div>

                    <div
                        v-for="day in gridDays"
                        :key="day.key"
                        class="group flex min-h-32 flex-col gap-1 bg-white p-1.5 dark:bg-neutral-950"
                        :class="!day.inMonth ? 'opacity-40' : ''"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-5 items-center justify-center rounded-full text-xs"
                                :class="day.key === todayKey ? 'bg-primary font-semibold text-primary-foreground' : 'text-muted-foreground'"
                            >
                                {{ day.date.getDate() }}
                            </span>

                            <Popover :open="openAddPopover === day.key" @update:open="(v) => (openAddPopover = v ? day.key : null)">
                                <PopoverTrigger as-child>
                                    <button
                                        type="button"
                                        class="rounded p-0.5 text-muted-foreground opacity-0 hover:bg-accent group-hover:opacity-100"
                                        aria-label="Add event"
                                        @click="startAddEvent(day.key)"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent class="w-64">
                                    <p class="mb-2 text-xs font-semibold text-muted-foreground">Add event</p>
                                    <form class="space-y-2" @submit.prevent="submitAddEvent">
                                        <Select
                                            :model-value="String(addEventForm.board_id)"
                                            @update:model-value="(v) => (addEventForm.board_id = v === GENERAL ? GENERAL : Number(v))"
                                        >
                                            <SelectTrigger class="h-9 text-sm">
                                                <SelectValue placeholder="Board" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="GENERAL">General (no board)</SelectItem>
                                                <SelectItem v-for="board in boards" :key="board.id" :value="String(board.id)">
                                                    {{ board.workspace_name }} - {{ board.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Input v-model="addEventForm.name" placeholder="Event name" />
                                        <div class="flex items-center gap-2">
                                            <Input v-model="addEventForm.start_date" type="date" class="flex-1" />
                                            <span class="text-xs text-muted-foreground">to</span>
                                            <Input v-model="addEventForm.end_date" type="date" class="flex-1" />
                                        </div>
                                        <ColorSwatchPicker v-model="addEventForm.color" />
                                        <Button type="submit" size="sm" class="w-full" :disabled="addEventForm.processing">Add</Button>
                                    </form>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <Link
                            v-for="card in cardsForDay(day.key)"
                            :key="`card-${card.id}`"
                            :href="route('boards.show', { board: card.board_id, card: card.id })"
                            class="block rounded px-1.5 py-0.5 leading-tight hover:opacity-80"
                            :title="boardTitle(card.board_id)"
                            :style="
                                card.color
                                    ? { backgroundColor: card.color, color: 'white' }
                                    : { backgroundColor: 'var(--muted)', color: 'var(--muted-foreground)' }
                            "
                        >
                            <span class="block truncate text-xs font-medium">{{ card.name }}</span>
                            <span class="block truncate text-[10px] opacity-80">{{ boardLabel(card.board_id) }}</span>
                        </Link>

                        <template v-for="event in eventsForDay(day.key)" :key="`event-${event.id}`">
                            <Popover
                                v-if="canEditEvent(event)"
                                :open="openEditPopover === `${day.key}-${event.id}`"
                                @update:open="(v) => (openEditPopover = v ? `${day.key}-${event.id}` : null)"
                            >
                                <PopoverTrigger as-child>
                                    <button
                                        type="button"
                                        class="block w-full rounded px-1.5 py-0.5 text-left leading-tight text-white hover:opacity-80"
                                        :style="{ backgroundColor: event.color ?? '#8590a2' }"
                                        :title="eventTitle(event)"
                                        @click="startEditEvent(event)"
                                    >
                                        <span class="block truncate text-xs font-medium">{{ event.name }}</span>
                                        <span class="block truncate text-[10px] opacity-80">{{ eventLabel(event) }}</span>
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent class="w-64">
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-xs font-semibold text-muted-foreground">
                                            Edit event
                                            <span class="font-normal text-muted-foreground">· {{ eventLabel(event) }}</span>
                                        </p>
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-destructive"
                                            aria-label="Delete event"
                                            @click="deleteEvent(event.id)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                    <form class="space-y-2" @submit.prevent="submitEditEvent(event.id)">
                                        <Input v-model="editEventForm.name" placeholder="Event name" autofocus />
                                        <div class="flex items-center gap-2">
                                            <Input v-model="editEventForm.start_date" type="date" class="flex-1" />
                                            <span class="text-xs text-muted-foreground">to</span>
                                            <Input v-model="editEventForm.end_date" type="date" class="flex-1" />
                                        </div>
                                        <ColorSwatchPicker v-model="editEventForm.color" />
                                        <Button type="submit" size="sm" class="w-full" :disabled="editEventForm.processing">Save</Button>
                                    </form>
                                </PopoverContent>
                            </Popover>

                            <span
                                v-else
                                class="block w-full cursor-default truncate rounded px-1.5 py-0.5 text-left leading-tight text-white"
                                :style="{ backgroundColor: event.color ?? '#8590a2' }"
                                :title="eventTitle(event)"
                            >
                                <span class="block truncate text-xs font-medium">{{ event.name }}</span>
                                <span class="block truncate text-[10px] opacity-80">{{ eventLabel(event) }}</span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
