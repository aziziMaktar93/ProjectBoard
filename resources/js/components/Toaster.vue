<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { Check, X } from 'lucide-vue-next';

const { toasts } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-center gap-2 rounded-md border px-4 py-2.5 text-sm shadow-lg"
                    :class="
                        toast.variant === 'success'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300'
                    "
                >
                    <Check v-if="toast.variant === 'success'" class="size-4 shrink-0" />
                    <X v-else class="size-4 shrink-0" />
                    <span>{{ toast.message }}</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
