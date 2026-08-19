<script setup lang="ts">
import BoardCard from '@/components/boards/BoardCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BoardList, Card } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    list: BoardList;
}>();

const emit = defineEmits<{
    'open-card': [card: Card];
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
</script>

<template>
    <div class="flex w-72 shrink-0 flex-col rounded-xl bg-muted/50 p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="truncate px-1 text-sm font-medium">{{ list.name }}</p>
        </div>

        <div class="flex flex-col gap-2">
            <BoardCard v-for="card in list.cards" :key="card.id" :card="card" @open="emit('open-card', $event)" />
        </div>

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
