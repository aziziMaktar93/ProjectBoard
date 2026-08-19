<script setup lang="ts">
import BoardCard from '@/components/boards/BoardCard.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { BoardList, Card } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { ref } from 'vue';

const props = defineProps<{
    list: BoardList;
    group: string;
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

function onCardDragStart() {
    emit('card-drag-start');
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    emit('card-drag-end', event);
}
</script>

<template>
    <div class="flex w-72 shrink-0 flex-col rounded-xl bg-muted/50 p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="list-drag-handle truncate px-1 text-sm font-medium">{{ list.name }}</p>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" aria-label="List actions">
                        <MoreHorizontal class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="archiveList">Archive list</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- eslint-disable vue/no-mutating-props -->
        <VueDraggable
            v-model="list.cards"
            :group="group"
            item-key="id"
            :animation="150"
            :data-list-id="list.id"
            class="flex flex-col gap-2"
            @start="onCardDragStart"
            @end="onCardDragEnd"
        >
            <BoardCard v-for="card in list.cards" :key="card.id" :card="card" @open="emit('open-card', $event)" />
        </VueDraggable>
        <!-- eslint-enable vue/no-mutating-props -->

        <Button v-if="!showAddCard" variant="ghost" size="sm" class="mt-2 justify-start" @click="showAddCard = true">
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
</template>
