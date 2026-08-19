<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import CardChecklist from '@/components/boards/CardChecklist.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Card } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    card: Card | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    description: '' as string | null,
    color: null as string | null,
    due_date: '' as string,
});

watch(
    open,
    (isOpen: boolean) => {
        if (isOpen && props.card) {
            form.name = props.card.name;
            form.description = props.card.description;
            form.color = props.card.color;
            form.due_date = props.card.due_date ?? '';
        }
    },
    { immediate: true },
);

function submit() {
    if (!props.card) {
        return;
    }

    form.patch(route('cards.update', props.card.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

const checklist = computed(() => props.card?.checklists?.[0] ?? null);

function addChecklist() {
    if (!props.card) {
        return;
    }

    router.post(route('checklists.store', props.card.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="card" class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Edit card</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="card-name">Name</Label>
                    <Input id="card-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="card-description">Description</Label>
                    <textarea
                        id="card-description"
                        v-model="form.description"
                        rows="4"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>Color</Label>
                    <ColorSwatchPicker v-model="form.color" />
                    <InputError :message="form.errors.color" />
                </div>

                <div class="grid gap-2">
                    <Label for="card-due-date">Due date</Label>
                    <div class="flex items-center gap-2">
                        <Input id="card-due-date" v-model="form.due_date" type="date" class="w-auto" />
                        <Button v-if="form.due_date" type="button" variant="ghost" size="sm" @click="form.due_date = ''">Clear</Button>
                    </div>
                    <InputError :message="form.errors.due_date" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </DialogFooter>
            </form>

            <div class="space-y-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <Label>Checklist</Label>

                <CardChecklist v-if="checklist" :checklist="checklist" />

                <Button v-else variant="ghost" size="sm" class="justify-start text-muted-foreground" @click="addChecklist">
                    + Add checklist
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
