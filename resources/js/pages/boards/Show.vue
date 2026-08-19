<script setup lang="ts">
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BoardList, BreadcrumbItem, Card } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref } from 'vue';

const props = defineProps<{
    board: Board;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Boards', href: route('boards.index') },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

const lists = ref<BoardList[]>(props.board.lists ?? []);
const cardGroup = `cards-board-${props.board.id}`;

function onListDragEnd() {
    router.patch(
        route('board-lists.reorder', props.board.id),
        { ordered_ids: lists.value.map((list) => list.id) },
        { preserveScroll: true, preserveState: true },
    );
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    const fromListId = Number(event.from.dataset.listId);
    const toListId = Number(event.to.dataset.listId);
    const targetList = lists.value.find((list) => list.id === toListId);

    if (!targetList) {
        return;
    }

    const payload: Record<string, unknown> = {
        target_list_id: toListId,
        target_ordered_ids: targetList.cards.map((card) => card.id),
    };

    if (fromListId !== toListId) {
        const sourceList = lists.value.find((list) => list.id === fromListId);

        if (sourceList) {
            payload.source_list_id = fromListId;
            payload.source_ordered_ids = sourceList.cards.map((card) => card.id);
        }
    }

    router.patch(route('cards.reorder', props.board.id), payload, {
        preserveScroll: true,
        preserveState: true,
    });
}

const showAddList = ref(false);

const addListForm = useForm({
    name: '',
});

function submitAddList() {
    addListForm.post(route('board-lists.store', props.board.id), {
        preserveScroll: true,
        onSuccess: () => {
            addListForm.reset();
            showAddList.value = false;
        },
    });
}

const activeCard = ref<Card | null>(null);
const showCardModal = ref(false);

function openCard(card: Card) {
    activeCard.value = card;
    showCardModal.value = true;
}

function archiveBoard() {
    if (!confirm(`Archive the board "${props.board.name}"?`)) {
        return;
    }

    router.patch(route('boards.archive', props.board.id));
}
</script>

<template>
    <Head :title="board.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between p-4">
            <h1 class="text-lg font-semibold">{{ board.name }}</h1>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button type="button" aria-label="Board actions">
                        <MoreHorizontal class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="archiveBoard">Archive board</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <VueDraggable
            v-model="lists"
            item-key="id"
            :animation="150"
            handle=".list-drag-handle"
            class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0"
            @end="onListDragEnd"
        >
            <BoardListColumn
                v-for="list in lists"
                :key="list.id"
                :list="list"
                :group="cardGroup"
                @open-card="openCard"
                @card-drag-end="onCardDragEnd"
            />
        </VueDraggable>

        <div class="p-4 pt-0">
            <Button v-if="!showAddList" variant="secondary" size="sm" @click="showAddList = true">Add list</Button>
            <form v-else class="flex max-w-xs gap-2" @submit.prevent="submitAddList">
                <Input v-model="addListForm.name" placeholder="List name" autofocus />
                <Button type="submit" size="sm" :disabled="addListForm.processing">Add</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAddList = false">Cancel</Button>
            </form>
        </div>

        <CardDetailModal v-model:open="showCardModal" :card="activeCard" />
    </AppLayout>
</template>
