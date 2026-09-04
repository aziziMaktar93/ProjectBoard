<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    completed: number;
    overdue: number;
    dueSoon: number;
    other: number;
}>();

const RADIUS = 40;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const segments = computed(() => [
    { label: 'Completed', value: props.completed, color: '#10b981' },
    { label: 'Overdue', value: props.overdue, color: '#ef4444' },
    { label: 'Due soon', value: props.dueSoon, color: '#f59e0b' },
    { label: 'Other', value: props.other, color: '#a3a3a3' },
]);

const total = computed(() => segments.value.reduce((sum, segment) => sum + segment.value, 0));

const arcs = computed(() => {
    let offset = 0;

    return segments.value
        .filter((segment) => segment.value > 0)
        .map((segment) => {
            const fraction = total.value === 0 ? 0 : segment.value / total.value;
            const length = fraction * CIRCUMFERENCE;
            const arc = { ...segment, dasharray: `${length} ${CIRCUMFERENCE - length}`, dashoffset: -offset };
            offset += length;

            return arc;
        });
});
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="relative shrink-0">
            <svg viewBox="0 0 100 100" class="size-24 -rotate-90">
                <circle cx="50" cy="50" :r="RADIUS" fill="none" stroke-width="14" class="stroke-neutral-100 dark:stroke-neutral-800" />
                <circle
                    v-for="arc in arcs"
                    :key="arc.label"
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    :stroke="arc.color"
                    stroke-width="14"
                    :stroke-dasharray="arc.dasharray"
                    :stroke-dashoffset="arc.dashoffset"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ total }}</span>
                <span class="text-[10px] text-muted-foreground">tasks</span>
            </div>
        </div>
        <ul class="flex-1 space-y-1.5 text-xs">
            <li v-for="segment in segments" :key="segment.label" class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                    <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: segment.color }" />
                    {{ segment.label }}
                </span>
                <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ segment.value }}</span>
            </li>
        </ul>
    </div>
</template>
