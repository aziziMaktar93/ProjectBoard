<script setup lang="ts">
import { useColorTheme, type ColorTheme } from '@/composables/useColorTheme';
import { Check } from 'lucide-vue-next';

interface Props {
    class?: string;
}

const { class: containerClass = '' } = defineProps<Props>();

const { colorTheme, updateColorTheme } = useColorTheme();

const swatches: { value: ColorTheme; label: string; color: string }[] = [
    { value: 'neutral', label: 'Neutral', color: '#171717' },
    { value: 'blue', label: 'Blue', color: '#2563eb' },
    { value: 'green', label: 'Green', color: '#16a34a' },
    { value: 'orange', label: 'Orange', color: '#f97316' },
    { value: 'rose', label: 'Rose', color: '#e11d48' },
    { value: 'violet', label: 'Violet', color: '#7c3aed' },
];
</script>

<template>
    <div :class="['flex flex-wrap gap-3', containerClass]">
        <button
            v-for="swatch in swatches"
            :key="swatch.value"
            type="button"
            class="flex flex-col items-center gap-1.5"
            :aria-label="`Use ${swatch.label} theme`"
            :aria-pressed="colorTheme === swatch.value"
            @click="updateColorTheme(swatch.value)"
        >
            <span
                class="flex size-9 items-center justify-center rounded-full ring-2 ring-offset-2 ring-offset-background transition"
                :class="colorTheme === swatch.value ? 'ring-neutral-400 dark:ring-neutral-500' : 'ring-transparent'"
                :style="{ backgroundColor: swatch.color }"
            >
                <Check v-if="colorTheme === swatch.value" class="size-4 text-white drop-shadow" />
            </span>
            <span class="text-xs text-muted-foreground">{{ swatch.label }}</span>
        </button>
    </div>
</template>
