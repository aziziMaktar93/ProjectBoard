<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    password: string;
}>();

const requirements = computed(() => [
    { label: 'At least 8 characters', met: props.password.length >= 8 },
    { label: 'One letter', met: /[a-zA-Z]/.test(props.password) },
    { label: 'One number', met: /\d/.test(props.password) },
    { label: 'One symbol', met: /[^a-zA-Z0-9]/.test(props.password) },
]);

const score = computed(() => requirements.value.filter((requirement) => requirement.met).length);

const strength = computed(() => {
    switch (score.value) {
        case 0:
        case 1:
            return { label: 'Weak', barColor: 'bg-red-500', textColor: 'text-red-600 dark:text-red-400' };
        case 2:
            return { label: 'Fair', barColor: 'bg-amber-500', textColor: 'text-amber-600 dark:text-amber-400' };
        case 3:
            return { label: 'Good', barColor: 'bg-blue-500', textColor: 'text-blue-600 dark:text-blue-400' };
        default:
            return { label: 'Strong', barColor: 'bg-emerald-500', textColor: 'text-emerald-600 dark:text-emerald-400' };
    }
});
</script>

<template>
    <div v-if="password.length > 0" class="space-y-2">
        <div class="flex items-center gap-2">
            <div class="flex flex-1 gap-1">
                <div
                    v-for="segment in 4"
                    :key="segment"
                    class="h-1.5 flex-1 rounded-full transition-colors"
                    :class="segment <= score ? strength.barColor : 'bg-neutral-200 dark:bg-neutral-700'"
                />
            </div>
            <span class="text-xs font-medium" :class="strength.textColor">{{ strength.label }}</span>
        </div>
        <ul class="grid grid-cols-2 gap-x-3 gap-y-1">
            <li
                v-for="requirement in requirements"
                :key="requirement.label"
                class="flex items-center gap-1 text-xs"
                :class="requirement.met ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
            >
                <Check v-if="requirement.met" class="size-3 shrink-0" />
                <X v-else class="size-3 shrink-0" />
                {{ requirement.label }}
            </li>
        </ul>
    </div>
</template>
