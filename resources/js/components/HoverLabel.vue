<script setup lang="ts">
import { cn } from '@/lib/utils';
import { ref, type HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        side?: 'top' | 'bottom';
        class?: HTMLAttributes['class'];
    }>(),
    {
        side: 'top',
    },
);

const triggerRef = ref<HTMLElement>();
const visible = ref(false);
const position = ref({ top: 0, left: 0 });
let showTimer: ReturnType<typeof setTimeout> | undefined;

function show() {
    showTimer = setTimeout(() => {
        const rect = triggerRef.value?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        position.value = {
            left: rect.left + rect.width / 2,
            top: props.side === 'bottom' ? rect.bottom + 8 : rect.top - 8,
        };
        visible.value = true;
    }, 300);
}

function hide() {
    clearTimeout(showTimer);
    visible.value = false;
}
</script>

<template>
    <span ref="triggerRef" :class="cn('inline-flex', props.class)" @mouseenter="show" @mouseleave="hide" @focusin="show" @focusout="hide">
        <slot />
    </span>
    <Teleport to="body">
        <span
            v-if="visible"
            class="pointer-events-none fixed z-50 -translate-x-1/2 whitespace-nowrap rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md"
            :class="side === 'bottom' ? '' : '-translate-y-full'"
            :style="{ left: `${position.left}px`, top: `${position.top}px` }"
        >
            {{ label }}
        </span>
    </Teleport>
</template>
