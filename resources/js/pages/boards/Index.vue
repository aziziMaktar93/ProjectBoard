<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
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
                                <DialogFooter>
                                    <Button type="submit" :disabled="form.processing">Create</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <p v-if="boards.length === 0" class="text-sm text-muted-foreground">No boards yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                <Link v-for="board in boards" :key="board.id" :href="route('boards.show', board.id)">
                    <Card
                        class="border-t-4 transition hover:border-sidebar-border"
                        :style="{ borderTopColor: board.background_color ?? undefined }"
                    >
                        <CardHeader>
                            <CardTitle>{{ board.name }}</CardTitle>
                        </CardHeader>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
