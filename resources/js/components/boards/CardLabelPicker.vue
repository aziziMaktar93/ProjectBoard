<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { showToast } from '@/composables/useToast';
import type { Card, CardLabel } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    card: Card;
    boardId: number;
    boardLabels: CardLabel[];
}>();

const LABEL_COLORS = ['#4bce97', '#f5cd47', '#fea362', '#f87168', '#9f8fef', '#579dff', '#6cc3e0', '#94c748', '#e774bb', '#8590a2'];

function isAssigned(label: CardLabel): boolean {
    return (props.card.labels ?? []).some((assigned) => assigned.id === label.id);
}

function toggle(label: CardLabel) {
    if (isAssigned(label)) {
        router.delete(route('card-labels.destroy', [props.card.id, label.id]), {
            preserveScroll: true,
            onSuccess: () => showToast('Label removed'),
            onError: () => showToast('Could not remove label, try again.', 'error'),
        });
    } else {
        router.post(
            route('card-labels.store', props.card.id),
            { label_id: label.id },
            {
                preserveScroll: true,
                onSuccess: () => showToast('Label added'),
                onError: () => showToast('Could not add label, try again.', 'error'),
            },
        );
    }
}

const showCreate = ref(false);

const createForm = useForm({
    name: '',
    color: LABEL_COLORS[0],
});

function submitCreate() {
    createForm.post(route('board-labels.store', props.boardId), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
            showToast('Label created');
        },
        onError: () => showToast('Could not create label, try again.', 'error'),
    });
}
</script>

<template>
    <div class="space-y-2">
        <ul class="space-y-1">
            <li
                v-for="label in boardLabels"
                :key="label.id"
                class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-2 text-sm text-white hover:opacity-90"
                :style="{ backgroundColor: label.color }"
                @click="toggle(label)"
            >
                <p class="font-medium drop-shadow-sm">{{ label.name }}</p>
                <Check v-if="isAssigned(label)" class="size-4 shrink-0 drop-shadow-sm" />
            </li>
        </ul>

        <Button v-if="!showCreate" variant="ghost" size="sm" class="justify-start text-muted-foreground" @click="showCreate = true">
            <Plus class="size-4" /> Create label
        </Button>

        <form v-else class="space-y-2 rounded-md border border-neutral-200 p-2 dark:border-neutral-700" @submit.prevent="submitCreate">
            <Input v-model="createForm.name" placeholder="Label name" autofocus />
            <div class="grid grid-cols-5 gap-1.5">
                <button
                    v-for="color in LABEL_COLORS"
                    :key="color"
                    type="button"
                    class="flex h-7 items-center justify-center rounded transition hover:opacity-90"
                    :style="{ backgroundColor: color }"
                    :aria-label="`Use color ${color}`"
                    :aria-pressed="createForm.color === color"
                    @click="createForm.color = color"
                >
                    <Check v-if="createForm.color === color" class="size-3.5 text-white drop-shadow" />
                </button>
            </div>
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="createForm.processing || !createForm.name.trim()">Create</Button>
                <Button type="button" variant="ghost" size="sm" @click="showCreate = false">Cancel</Button>
            </div>
        </form>
    </div>
</template>
