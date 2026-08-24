<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Kanban, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface MemberWithWorkspaces {
    id: number;
    name: string;
    email: string;
    workspaces: { id: number; name: string }[];
}

const props = defineProps<{
    members: MemberWithWorkspaces[];
    workspaces: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Members', href: '/members' }];

const ALL = 'all';
const query = ref('');
const selectedWorkspace = ref<string>(ALL);

const filteredMembers = computed(() => {
    const trimmed = query.value.trim().toLowerCase();

    return props.members.filter((member) => {
        const matchesQuery = !trimmed || member.name.toLowerCase().includes(trimmed) || member.email.toLowerCase().includes(trimmed);
        const matchesWorkspace =
            selectedWorkspace.value === ALL || member.workspaces.some((workspace) => String(workspace.id) === selectedWorkspace.value);

        return matchesQuery && matchesWorkspace;
    });
});
</script>

<template>
    <Head title="Members" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-lg font-semibold">Members</h1>
                <p class="text-sm text-muted-foreground">Everyone you share a workspace with.</p>
            </div>

            <div v-if="members.length" class="flex flex-wrap items-center gap-2">
                <div class="relative max-w-sm flex-1">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="query" placeholder="Search by name or email" class="pl-8" />
                </div>

                <Select v-model="selectedWorkspace">
                    <SelectTrigger class="h-10 w-56 gap-1.5">
                        <span class="flex min-w-0 items-center gap-1.5 truncate">
                            <Kanban class="size-3.5 shrink-0 text-muted-foreground" />
                            <SelectValue placeholder="All workspaces" />
                        </span>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All workspaces</SelectItem>
                        <SelectItem v-for="workspace in workspaces" :key="workspace.id" :value="String(workspace.id)">
                            {{ workspace.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <p v-if="members.length === 0" class="text-sm text-muted-foreground">
                You don't share a workspace with anyone yet — invite teammates from a workspace's Members panel.
            </p>

            <p v-else-if="filteredMembers.length === 0" class="text-sm text-muted-foreground">No members match your filters.</p>

            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="member in filteredMembers"
                    :key="member.id"
                    class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700"
                >
                    <MemberAvatar :user="member" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ member.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="workspace in member.workspaces"
                                :key="workspace.id"
                                class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                            >
                                {{ workspace.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
