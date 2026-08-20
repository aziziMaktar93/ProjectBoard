<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Workspace } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    workspaces: Workspace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Workspaces', href: '/workspaces' }];

const showCreate = ref(false);

const form = useForm({
    name: '',
});

function submit() {
    form.post(route('workspaces.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Workspaces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold">Your workspaces</h1>
                <Dialog v-model:open="showCreate">
                    <DialogTrigger as-child>
                        <Button size="sm">New workspace</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New workspace</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <Label for="workspace-name">Name</Label>
                                <Input id="workspace-name" v-model="form.name" required autofocus />
                                <InputError :message="form.errors.name" />
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <p v-if="workspaces.length === 0" class="text-sm text-muted-foreground">No workspaces yet — create your first one.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="workspace in workspaces" :key="workspace.id" :href="route('workspaces.show', workspace.id)">
                    <div
                        class="flex h-24 flex-col justify-between rounded-lg border border-neutral-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <p class="line-clamp-2 font-semibold text-neutral-900 dark:text-neutral-100">{{ workspace.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ workspace.boards_count ?? 0 }} board(s)</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
