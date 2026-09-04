<script setup lang="ts">
import type { CompletionTrendPoint } from '@/types';
import { computed, useId } from 'vue';

const props = defineProps<{
    series: CompletionTrendPoint[];
}>();

const gradientId = `completion-trend-${useId()}`;

const WIDTH = 280;
const HEIGHT = 80;
const PADDING = 4;

const maxCount = computed(() => Math.max(1, ...props.series.map((point) => point.count)));

const points = computed(() =>
    props.series.map((point, index) => {
        const x = props.series.length <= 1 ? WIDTH / 2 : (index / (props.series.length - 1)) * (WIDTH - PADDING * 2) + PADDING;
        const y = HEIGHT - PADDING - (point.count / maxCount.value) * (HEIGHT - PADDING * 2);

        return { x, y, ...point };
    }),
);

const linePath = computed(() => points.value.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' '));

const areaPath = computed(() => {
    if (points.value.length === 0) {
        return '';
    }

    const first = points.value[0];
    const last = points.value[points.value.length - 1];

    return `${linePath.value} L ${last.x} ${HEIGHT - PADDING} L ${first.x} ${HEIGHT - PADDING} Z`;
});

const total = computed(() => props.series.reduce((sum, point) => sum + point.count, 0));
</script>

<template>
    <div>
        <p class="text-xs text-muted-foreground">
            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ total }}</span>
            checklist item{{ total === 1 ? '' : 's' }} completed
        </p>
        <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" class="mt-2 h-20 w-full">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path :d="linePath" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <g v-for="point in points" :key="point.date">
                <circle :cx="point.x" :cy="point.y" r="2" fill="#6366f1" />
                <circle :cx="point.x" :cy="point.y" r="8" fill="transparent" class="cursor-default transition hover:fill-indigo-500/15">
                    <title>{{ point.date }}: {{ point.count }} completed</title>
                </circle>
            </g>
        </svg>
        <div class="flex justify-between text-[10px] text-muted-foreground">
            <span>{{ series[0]?.date }}</span>
            <span>{{ series[series.length - 1]?.date }}</span>
        </div>
    </div>
</template>
