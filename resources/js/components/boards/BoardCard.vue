<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Card } from '@/types';
import { router } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';

const props = defineProps<{
    card: Card;
}>();

const emit = defineEmits<{
    open: [card: Card];
}>();

function archive() {
    router.patch(route('cards.archive', props.card.id), {}, { preserveScroll: true });
}
</script>

<template>
    <div
        class="group flex items-start justify-between gap-2 rounded-md border border-sidebar-border/70 bg-background p-3 text-sm shadow-sm hover:border-sidebar-border dark:border-sidebar-border"
    >
        <button type="button" class="flex-1 text-left" @click="emit('open', card)">
            <p>{{ card.name }}</p>
            <p v-if="card.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ card.description }}</p>
        </button>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button type="button" class="opacity-0 group-hover:opacity-100 focus-visible:opacity-100" aria-label="Card actions">
                    <MoreHorizontal class="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem @click="archive">Archive</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
