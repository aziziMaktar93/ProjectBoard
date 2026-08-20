<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import CardActivityFeed from '@/components/boards/CardActivityFeed.vue';
import CardChecklist from '@/components/boards/CardChecklist.vue';
import CardMemberPicker from '@/components/boards/CardMemberPicker.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Card, User } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    card: Card | null;
    boardMembers: User[];
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
        <DialogContent v-if="card" class="max-h-[85vh] overflow-hidden sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>Edit card</DialogTitle>
            </DialogHeader>

            <div class="grid max-h-[calc(85vh-5rem)] grid-cols-1 gap-6 overflow-hidden md:grid-cols-[1fr_320px]">
                <div class="space-y-6 overflow-y-auto pr-1">
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

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">Save</Button>
                        </div>
                    </form>

                    <div class="space-y-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                        <Label>Members</Label>
                        <CardMemberPicker v-if="card" :card="card" :board-members="boardMembers" />
                    </div>

                    <div class="space-y-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                        <Label>Checklist</Label>

                        <CardChecklist v-if="checklist" :checklist="checklist" />

                        <Button v-else variant="ghost" size="sm" class="justify-start text-muted-foreground" @click="addChecklist">
                            + Add checklist
                        </Button>
                    </div>
                </div>

                <div
                    class="space-y-3 overflow-y-auto border-t border-neutral-200 pt-4 md:border-t-0 md:border-l md:pl-4 md:pt-0 dark:border-neutral-700"
                >
                    <Label>Comments and activity</Label>
                    <CardActivityFeed :card="card" />
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
