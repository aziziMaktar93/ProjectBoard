<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { tileGradient } from '@/lib/colorGradient';
import type { BreadcrumbItem, Paginated, Workspace } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search, Star, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    workspaces: Paginated<Workspace>;
    filters: {
        search: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Workspaces', href: '/workspaces' }];

const showCreate = ref(false);

const form = useForm({
    name: '',
    background_color: '#0079BF',
});

function submit() {
    form.post(route('workspaces.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}

const search = ref(props.filters.search);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let skipNextSearchWatch = false;

watch(search, (value) => {
    if (skipNextSearchWatch) {
        skipNextSearchWatch = false;
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        router.get(route('workspaces.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function clearSearch() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    skipNextSearchWatch = true;
    search.value = '';
    router.get(route('workspaces.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
}

function goToPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function toggleFavourite(workspace: Workspace) {
    router.patch(route('workspaces.favourite', workspace.id), {}, { preserveScroll: true, preserveState: true });
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
                            <div class="grid gap-2">
                                <Label for="workspace-color">Color</Label>
                                <div class="flex items-center gap-2">
                                    <input
                                        id="workspace-color"
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

            <div class="flex items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search workspaces..." class="pl-8" />
                </div>

                <Button v-if="filters.search" variant="ghost" size="sm" @click="clearSearch">
                    <X class="size-3.5" />
                    Clear
                </Button>
            </div>

            <p v-if="workspaces.data.length === 0" class="text-sm text-muted-foreground">
                {{ filters.search ? 'No workspaces match your search.' : 'No workspaces yet — create your first one.' }}
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div v-for="workspace in workspaces.data" :key="workspace.id" class="group relative">
                    <Link :href="route('workspaces.show', workspace.id)" class="block">
                        <div
                            class="flex h-24 flex-col justify-between rounded-lg p-4 shadow-sm transition group-hover:shadow-md group-hover:brightness-110"
                            :style="{ backgroundImage: tileGradient(workspace.background_color) }"
                        >
                            <p class="line-clamp-2 pr-6 font-semibold text-white drop-shadow-sm">{{ workspace.name }}</p>
                            <p class="text-xs text-white/80 drop-shadow-sm">{{ workspace.boards_count ?? 0 }} board(s)</p>
                        </div>
                    </Link>

                    <button
                        type="button"
                        class="absolute right-2 top-2 rounded p-1 text-white/70 opacity-0 transition hover:text-white group-hover:opacity-100"
                        :class="{ '!opacity-100': workspace.is_favourite }"
                        :aria-label="workspace.is_favourite ? 'Unfavourite workspace' : 'Favourite workspace'"
                        @click.stop.prevent="toggleFavourite(workspace)"
                    >
                        <Star class="size-4" :class="workspace.is_favourite ? 'fill-amber-300 text-amber-300' : ''" />
                    </button>
                </div>
            </div>

            <div v-if="workspaces.last_page > 1" class="flex flex-wrap items-center gap-1">
                <button
                    v-for="link in workspaces.links"
                    :key="link.label"
                    type="button"
                    v-html="link.label"
                    class="rounded-md px-3 py-1.5 text-sm transition"
                    :disabled="!link.url"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : link.url
                              ? 'text-muted-foreground hover:bg-accent'
                              : 'cursor-not-allowed text-muted-foreground/40'
                    "
                    @click="goToPage(link.url)"
                />
            </div>
        </div>
    </AppLayout>
</template>
