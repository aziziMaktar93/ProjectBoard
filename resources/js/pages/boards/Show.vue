<script setup lang="ts">
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Board, BreadcrumbItem, Card } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
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

function openCard(card: Card) {
    // Wired up to CardDetailModal in the next task.
    console.log('open card', card);
}
</script>

<template>
    <Head :title="board.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between p-4">
            <h1 class="text-lg font-semibold">{{ board.name }}</h1>
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
    </AppLayout>
</template>
