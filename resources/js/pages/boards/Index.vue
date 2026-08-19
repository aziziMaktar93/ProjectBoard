<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Board, type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    boards: Board[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boards', href: '/boards' }];

const showCreate = ref(false);

const form = useForm({
    name: '',
    background_color: '#0079BF',
});

function submit() {
    form.post(route('boards.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Boards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Your boards</h1>
                <div class="flex items-center gap-4">
                    <Link :href="route('boards.archived')" class="text-sm text-muted-foreground underline">Archived boards</Link>
                    <Dialog v-model:open="showCreate">
                        <DialogTrigger as-child>
                            <Button size="sm">New board</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New board</DialogTitle>
                            </DialogHeader>
                            <form class="space-y-4" @submit.prevent="submit">
                                <div class="grid gap-2">
                                    <Label for="board-name">Name</Label>
                                    <Input id="board-name" v-model="form.name" required autofocus />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="board-color">Color</Label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="board-color"
                                            v-model="form.background_color"
                                            type="color"
                                            class="h-9 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                        />
                                        <span class="text-sm text-muted-foreground">{{ form.background_color }}</span>
                                    </div>
                                    <InputError :message="form.errors.background_color" />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" :disabled="form.processing">Create</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)" class="group block">
                    <div
                        class="flex h-24 flex-col justify-between rounded-lg p-4 shadow-sm transition group-hover:shadow-md group-hover:brightness-110"
                        :style="{ backgroundColor: board.background_color || '#44546f' }"
                    >
                        <p class="line-clamp-2 font-semibold text-white drop-shadow-sm">{{ board.name }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
