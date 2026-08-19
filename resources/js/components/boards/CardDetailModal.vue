<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Card } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    card: Card | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    description: '' as string | null,
});

watch(
    () => props.card,
    (card) => {
        if (card) {
            form.name = card.name;
            form.description = card.description;
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
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="card">
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

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
