<script setup lang="ts">
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useMonthCalendar } from '@/composables/useMonthCalendar';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BoardEvent, BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock, Kanban, ListChecks, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    board: Board;
    cards: { id: number; board_list_id: number; name: string; due_date: string; color: string | null; is_completed: boolean }[];
    events: BoardEvent[];
    checklistItems: {
        id: number;
        card_id: number;
        card_name: string;
        checklist_name: string;
        name: string;
        due_date: string;
        is_checked: boolean;
    }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.board.workspace?.name ?? '', href: route('workspaces.show', props.board.workspace_id) },
    { title: props.board.name, href: route('boards.show', props.board.id) },
    { title: 'Calendar', href: route('boards.calendar', props.board.id) },
]);

const { WEEKDAYS, monthLabel, todayKey, gridDays, goToMonth, goToToday } = useMonthCalendar();

function cardsForDay(dateKey: string) {
    return props.cards.filter((card) => card.due_date === dateKey);
}

function eventsForDay(dateKey: string) {
    return props.events.filter((event) => dateKey >= event.start_date && dateKey <= (event.end_date ?? event.start_date));
}

function checklistItemsForDay(dateKey: string) {
    return props.checklistItems.filter((item) => item.due_date === dateKey);
}

const openAddPopover = ref<string | null>(null);

const addEventForm = useForm({
    name: '',
    start_date: '',
    end_date: '',
    color: null as string | null,
});

function startAddEvent(dateKey: string) {
    addEventForm.reset();
    addEventForm.start_date = dateKey;
    addEventForm.color = null;
}

function submitAddEvent() {
    if (!addEventForm.name.trim()) {
        return;
    }

    addEventForm.post(route('board-events.store', props.board.id), {
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
    <Head :title="`${board.name} — Calendar`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-lg font-semibold">{{ monthLabel }}</h1>
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
                    <div class="mt-2 flex items-center gap-2">
                        <span
                            class="flex items-center gap-1.5 rounded-md border border-border bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <Clock class="size-3.5" /> Card due date
                        </span>
                        <span
                            class="flex items-center gap-1.5 rounded-md border border-border bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <CalendarDays class="size-3.5" /> Event
                        </span>
                        <span
                            class="flex items-center gap-1.5 rounded-md border border-border bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <ListChecks class="size-3.5" /> Checklist due date
                        </span>
                        <span
                            class="flex items-center gap-1.5 rounded-md border border-border bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <CheckCircle2 class="size-3.5" /> <span class="line-through">Completed</span>
                        </span>
                    </div>
                </div>

                <Button as-child variant="outline" size="sm">
                    <Link :href="route('boards.show', board.id)">
                        <Kanban class="size-3.5" />
                        Board view
                    </Link>
                </Button>
            </div>

            <div class="-mx-4 overflow-x-auto px-4 pb-2">
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
                        class="group flex min-h-28 flex-col gap-1 bg-white p-1.5 dark:bg-neutral-950"
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
                                        <Input v-model="addEventForm.name" placeholder="Event name" autofocus />
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
                            :href="route('boards.show', { board: board.id, card: card.id })"
                            class="flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-xs font-medium hover:opacity-80"
                            :class="card.is_completed ? 'opacity-60' : ''"
                            :title="card.is_completed ? 'Completed' : undefined"
                            :style="
                                card.color
                                    ? { backgroundColor: card.color, color: 'white' }
                                    : { backgroundColor: 'var(--muted)', color: 'var(--muted-foreground)' }
                            "
                        >
                            <CheckCircle2 v-if="card.is_completed" class="size-3 shrink-0" />
                            <Clock v-else class="size-3 shrink-0" />
                            <span class="truncate" :class="card.is_completed ? 'line-through' : ''">{{ card.name }}</span>
                        </Link>

                        <Link
                            v-for="item in checklistItemsForDay(day.key)"
                            :key="`checklist-item-${item.id}`"
                            :href="route('boards.show', { board: board.id, card: item.card_id })"
                            class="flex flex-col truncate rounded bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-700 hover:opacity-80 dark:bg-violet-900/40 dark:text-violet-300"
                            :class="item.is_checked ? 'opacity-60' : ''"
                            :title="`${item.card_name} • ${item.checklist_name}${item.is_checked ? ' (Completed)' : ''}`"
                        >
                            <span class="flex items-center gap-1 truncate">
                                <CheckCircle2 v-if="item.is_checked" class="size-3 shrink-0" />
                                <ListChecks v-else class="size-3 shrink-0" />
                                <span class="truncate" :class="item.is_checked ? 'line-through' : ''">{{ item.name }}</span>
                            </span>
                            <span class="truncate pl-4 text-[10px] opacity-70">{{ item.card_name }}</span>
                        </Link>

                        <Popover
                            v-for="event in eventsForDay(day.key)"
                            :key="`event-${event.id}`"
                            :open="openEditPopover === `${day.key}-${event.id}`"
                            @update:open="(v) => (openEditPopover = v ? `${day.key}-${event.id}` : null)"
                        >
                            <PopoverTrigger as-child>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-1 truncate rounded px-1.5 py-0.5 text-left text-xs font-medium text-white hover:opacity-80"
                                    :style="{ backgroundColor: event.color ?? '#8590a2' }"
                                    @click="startEditEvent(event)"
                                >
                                    <CalendarDays class="size-3 shrink-0" />
                                    <span class="truncate">{{ event.name }}</span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold text-muted-foreground">Edit event</p>
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
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
