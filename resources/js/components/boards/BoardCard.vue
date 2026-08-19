<script setup lang="ts">
import type { Card } from '@/types';

defineProps<{
    card: Card;
}>();

const emit = defineEmits<{
    open: [card: Card];
}>();
</script>

<template>
    <button
        type="button"
        class="w-full rounded-md border border-sidebar-border/70 bg-background p-3 text-left text-sm shadow-sm hover:border-sidebar-border dark:border-sidebar-border"
        @click="emit('open', card)"
    >
        <p>{{ card.name }}</p>
        <p v-if="card.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ card.description }}</p>
    </button>
</template>
