<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { BOARD_ITEM_COLORS } from '@/lib/boardColors';
import { Check, X } from 'lucide-vue-next';

const model = defineModel<string | null>({ default: null });

function select(color: string) {
    model.value = model.value === color ? null : color;
}
</script>

<template>
    <div class="space-y-2">
        <div class="grid grid-cols-5 gap-2">
            <Tooltip v-for="color in BOARD_ITEM_COLORS" :key="color">
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="flex h-8 items-center justify-center rounded-md transition hover:opacity-90"
                        :style="{ backgroundColor: color }"
                        :aria-label="`Use color ${color}`"
                        :aria-pressed="model === color"
                        @click="select(color)"
                    >
                        <Check v-if="model === color" class="size-4 text-white drop-shadow" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>{{ color }}</TooltipContent>
            </Tooltip>
        </div>

        <button
            v-if="model"
            type="button"
            class="flex w-full items-center justify-center gap-1 rounded-md bg-neutral-100 py-1.5 text-xs text-neutral-500 hover:bg-neutral-200 hover:text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
            @click="model = null"
        >
            <X class="size-3.5" /> Remove color
        </button>
    </div>
</template>
