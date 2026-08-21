<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, type HTMLAttributes } from 'vue';
import Input from './Input.vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const visible = ref(false);
const inputRef = ref<InstanceType<typeof Input>>();

function toggleVisible() {
    visible.value = !visible.value;
}

defineExpose({
    focus: () => (inputRef.value?.$el as HTMLInputElement | undefined)?.focus(),
});
</script>

<template>
    <div class="relative">
        <Input
            ref="inputRef"
            v-bind="$attrs"
            :type="visible ? 'text' : 'password'"
            :model-value="props.modelValue"
            :class="cn('pr-10', props.class)"
            @update:model-value="(value) => emits('update:modelValue', value)"
        />
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    tabindex="-1"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground transition hover:text-foreground"
                    :aria-label="visible ? 'Hide password' : 'Show password'"
                    @click="toggleVisible"
                >
                    <EyeOff v-if="visible" class="size-4" />
                    <Eye v-else class="size-4" />
                </button>
            </TooltipTrigger>
            <TooltipContent>{{ visible ? 'Hide password' : 'Show password' }}</TooltipContent>
        </Tooltip>
    </div>
</template>
