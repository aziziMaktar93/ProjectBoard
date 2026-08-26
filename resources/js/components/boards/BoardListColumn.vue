<script setup lang="ts">
import HoverLabel from '@/components/HoverLabel.vue';
import BoardCard from '@/components/boards/BoardCard.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { stripGradient } from '@/lib/colorGradient';
import type { BoardList, Card } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    list: BoardList;
    group: string;
    matchesFilters?: (card: Card) => boolean;
}>();

const emit = defineEmits<{
    'open-card': [card: Card];
    'card-drag-start': [];
    'card-drag-end': [event: { from: HTMLElement; to: HTMLElement }];
}>();

const showAddCard = ref(false);

const addCardForm = useForm({
    name: '',
});

function submitAddCard() {
    addCardForm.post(route('cards.store', props.list.id), {
        preserveScroll: true,
        onSuccess: () => {
            addCardForm.reset();
            showAddCard.value = false;
        },
    });
}

function archiveList() {
    router.patch(route('board-lists.archive', props.list.id), {}, { preserveScroll: true });
}

function duplicateList() {
    router.post(route('board-lists.duplicate', props.list.id), {}, { preserveScroll: true });
}

function onCardDragStart() {
    emit('card-drag-start');
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    emit('card-drag-end', event);
}

const isEditingName = ref(false);
const nameInput = ref<HTMLInputElement | null>(null);

const nameForm = useForm({
    name: props.list.name,
});

async function startEditingName() {
    nameForm.name = props.list.name;
    isEditingName.value = true;
    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
}

function saveName() {
    isEditingName.value = false;

    if (!nameForm.name.trim() || nameForm.name === props.list.name) {
        nameForm.name = props.list.name;
        return;
    }

    nameForm.patch(route('board-lists.update', props.list.id), { preserveScroll: true });
}

function onColorChange(color: string | null) {
    router.patch(route('board-lists.update', props.list.id), { color }, { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex w-72 shrink-0 flex-col overflow-hidden rounded-xl border border-neutral-200/70 bg-white shadow-md dark:border-neutral-800 dark:bg-neutral-900 dark:shadow-none"
    >
        <div v-if="list.color" class="h-1.5 shrink-0" :style="{ backgroundImage: stripGradient(list.color) }" />

        <div class="p-3 pb-0">
            <div class="list-drag-handle mb-2 flex cursor-grab items-center justify-between gap-2 active:cursor-grabbing">
                <div class="flex min-w-0 flex-1 items-center gap-2 px-1">
                    <input
                        v-if="isEditingName"
                        ref="nameInput"
                        v-model="nameForm.name"
                        class="min-w-0 flex-1 truncate rounded bg-white px-1 text-sm font-semibold text-neutral-700 outline-none ring-2 ring-ring dark:bg-neutral-800 dark:text-neutral-200"
                        @blur="saveName"
                        @keydown.enter="saveName"
                        @keydown.escape="isEditingName = false"
                        @mousedown.stop
                    />
                    <p
                        v-else
                        class="min-w-0 flex-1 cursor-text truncate rounded px-1 text-sm font-semibold text-neutral-700 hover:bg-neutral-200 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        @mousedown.stop
                        @click="startEditingName"
                    >
                        {{ list.name }}
                    </p>
                    <span
                        v-if="list.cards.length"
                        class="shrink-0 rounded-full bg-neutral-200 px-1.5 py-0.5 text-xs text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                    >
                        {{ list.cards.length }}
                    </span>
                </div>

                <DropdownMenu>
                    <HoverLabel label="List actions" side="bottom">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="shrink-0 rounded p-0.5 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                aria-label="List actions"
                            >
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                    </HoverLabel>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuItem @click="startEditingName">Rename list</DropdownMenuItem>
                        <DropdownMenuItem @click="duplicateList">Duplicate list</DropdownMenuItem>
                        <DropdownMenuItem @click="archiveList">Archive list</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel>List color</DropdownMenuLabel>
                        <div class="px-2 pb-1">
                            <ColorSwatchPicker :model-value="list.color" @update:model-value="onColorChange" />
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div class="px-3 pb-3">
            <!-- eslint-disable vue/no-mutating-props -->
            <VueDraggable
                v-model="list.cards"
                :group="group"
                item-key="id"
                :animation="150"
                :data-list-id="list.id"
                class="flex max-h-[65vh] flex-col gap-2 overflow-y-auto px-0.5 py-0.5"
                @start="onCardDragStart"
                @end="onCardDragEnd"
            >
                <BoardCard
                    v-for="card in list.cards"
                    :key="card.id"
                    v-show="!matchesFilters || matchesFilters(card)"
                    :card="card"
                    @open="emit('open-card', $event)"
                />
            </VueDraggable>
            <!-- eslint-enable vue/no-mutating-props -->

            <Button
                v-if="!showAddCard"
                variant="ghost"
                size="sm"
                class="mt-2 justify-start text-neutral-500 hover:bg-neutral-200 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                @click="showAddCard = true"
            >
                <Plus class="size-4" /> Add card
            </Button>

            <form v-else class="mt-2 space-y-2" @submit.prevent="submitAddCard">
                <Input v-model="addCardForm.name" placeholder="Card name" autofocus />
                <div class="flex gap-2">
                    <Button type="submit" size="sm" :disabled="addCardForm.processing">Add</Button>
                    <Button type="button" variant="ghost" size="sm" @click="showAddCard = false">Cancel</Button>
                </div>
            </form>
        </div>
    </div>
</template>
