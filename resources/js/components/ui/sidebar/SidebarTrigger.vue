<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { PanelLeft } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { useSidebar } from './utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { toggleSidebar } = useSidebar();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Button data-sidebar="trigger" variant="ghost" size="icon" :class="cn('h-7 w-7', props.class)" @click="toggleSidebar">
                <PanelLeft />
                <span class="sr-only">Toggle Sidebar</span>
            </Button>
        </TooltipTrigger>
        <TooltipContent>Toggle sidebar</TooltipContent>
    </Tooltip>
</template>
