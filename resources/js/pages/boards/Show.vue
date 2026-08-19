<script setup lang="ts">
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, Card } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    board: Board;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Boards', href: route('boards.index') },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

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

        <div class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0">
            <BoardListColumn v-for="list in board.lists" :key="list.id" :list="list" @open-card="openCard" />
        </div>

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
