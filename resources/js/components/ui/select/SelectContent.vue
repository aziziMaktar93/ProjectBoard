<script setup lang="ts">
import { cn } from '@/lib/utils';
import {
    SelectContent,
    SelectPortal,
    SelectViewport,
    type SelectContentEmits,
    type SelectContentProps,
    useForwardPropsEmits,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = withDefaults(defineProps<SelectContentProps & { class?: HTMLAttributes['class'] }>(), {
    position: 'popper',
});
const emits = defineEmits<SelectContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <SelectPortal>
        <SelectContent
            v-bind="forwarded"
            :class="
                cn(
                    'relative z-50 max-h-96 min-w-32 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    position === 'popper' && 'translate-y-1',
                    props.class,
                )
            "
        >
            <SelectViewport class="p-1">
                <slot />
            </SelectViewport>
        </SelectContent>
    </SelectPortal>
</template>
