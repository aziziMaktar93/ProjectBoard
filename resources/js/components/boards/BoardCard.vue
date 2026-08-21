<script setup lang="ts">
import HoverLabel from '@/components/HoverLabel.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { stripGradient } from '@/lib/colorGradient';
import type { Card, ChecklistItem } from '@/types';
import { router } from '@inertiajs/vue3';
import { Calendar, MoreHorizontal, SquareCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    card: Card;
}>();

const emit = defineEmits<{
    open: [card: Card];
}>();

const checklistSummary = computed(() => {
    const items = props.card.checklists?.[0]?.items ?? [];

    if (items.length === 0) {
        return null;
    }

    const checked = items.filter((item: ChecklistItem) => item.is_checked).length;
    const total = items.length;

    return {
        checked,
        total,
        percentage: Math.round((checked / total) * 100),
    };
});

const dueDateLabel = computed(() => {
    if (!props.card.due_date) {
        return null;
    }

    return new Date(`${props.card.due_date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
});

const isOverdue = computed(() => {
    if (!props.card.due_date) {
        return false;
    }

    return props.card.due_date < new Date().toISOString().slice(0, 10);
});

function archive() {
    router.patch(route('cards.archive', props.card.id), {}, { preserveScroll: true });
}

function duplicateCard() {
    router.post(route('cards.duplicate', props.card.id), {}, { preserveScroll: true });
}

function onColorChange(color: string | null) {
    router.patch(route('cards.update', props.card.id), { color }, { preserveScroll: true });
}
</script>

<template>
    <div
        class="group overflow-hidden rounded-lg border border-neutral-200/80 bg-white text-sm shadow-sm transition hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-lg dark:border-neutral-700 dark:bg-neutral-800 dark:shadow-none dark:hover:border-neutral-600"
    >
        <div v-if="card.color" class="h-1.5" :style="{ backgroundImage: stripGradient(card.color) }" />

        <div class="flex items-start justify-between gap-2 p-3">
            <button type="button" class="flex-1 text-left" @click="emit('open', card)">
                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ card.name }}</p>
                <p v-if="card.description" class="mt-1 line-clamp-2 text-xs text-neutral-500 dark:text-neutral-400">{{ card.description }}</p>
                <div v-if="checklistSummary || dueDateLabel" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <div
                        v-if="dueDateLabel"
                        class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                        :class="
                            isOverdue
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                        "
                    >
                        <Calendar class="size-3.5" />
                        <span>{{ dueDateLabel }}</span>
                    </div>
                    <div
                        v-if="checklistSummary"
                        class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                        :class="
                            checklistSummary.checked === checklistSummary.total
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'
                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                        "
                    >
                        <SquareCheck class="size-3.5" />
                        <span>{{ checklistSummary.checked }}/{{ checklistSummary.total }}</span>
                        <span class="opacity-75">({{ checklistSummary.percentage }}%)</span>
                    </div>
                </div>
                <div v-if="card.members?.length" class="mt-2 flex flex-wrap gap-1">
                    <MemberAvatar v-for="member in card.members" :key="member.id" :user="member" size="xs" />
                </div>
            </button>

            <DropdownMenu>
                <HoverLabel label="Card actions" side="bottom">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded p-0.5 text-neutral-400 opacity-0 hover:bg-neutral-100 hover:text-neutral-700 group-hover:opacity-100 focus-visible:opacity-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                            aria-label="Card actions"
                        >
                            <MoreHorizontal class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                </HoverLabel>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem @click="emit('open', card)">Edit card</DropdownMenuItem>
                    <DropdownMenuItem @click="duplicateCard">Duplicate card</DropdownMenuItem>
                    <DropdownMenuItem @click="archive">Archive</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel>Card color</DropdownMenuLabel>
                    <div class="px-2 pb-1">
                        <ColorSwatchPicker :model-value="card.color" @update:model-value="onColorChange" />
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
