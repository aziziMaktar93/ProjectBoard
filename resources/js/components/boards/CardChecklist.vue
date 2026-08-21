<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { Checklist, ChecklistItem } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    checklist: Checklist;
}>();

const hideChecked = ref(false);
const showAddItem = ref(false);
const newItemName = ref('');

const isEditingName = ref(false);
const nameInput = ref<HTMLInputElement | null>(null);

const nameForm = useForm({
    name: props.checklist.name,
});

async function startEditingName() {
    nameForm.name = props.checklist.name;
    isEditingName.value = true;
    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
}

function saveName() {
    isEditingName.value = false;

    if (!nameForm.name.trim() || nameForm.name === props.checklist.name) {
        nameForm.name = props.checklist.name;
        return;
    }

    nameForm.patch(route('checklists.update', props.checklist.id), { preserveScroll: true });
}

const visibleItems = computed(() => (hideChecked.value ? props.checklist.items.filter((item) => !item.is_checked) : props.checklist.items));

const progress = computed(() => {
    const total = props.checklist.items.length;

    if (total === 0) {
        return 0;
    }

    const checked = props.checklist.items.filter((item) => item.is_checked).length;

    return Math.round((checked / total) * 100);
});

function toggleItem(item: ChecklistItem) {
    router.patch(route('checklist-items.update', item.id), { is_checked: !item.is_checked }, { preserveScroll: true });
}

function deleteItem(item: ChecklistItem) {
    router.delete(route('checklist-items.destroy', item.id), { preserveScroll: true });
}

function submitAddItem() {
    if (!newItemName.value.trim()) {
        return;
    }

    router.post(
        route('checklist-items.store', props.checklist.id),
        { name: newItemName.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                newItemName.value = '';
            },
        },
    );
}

function deleteChecklist() {
    if (!confirm('Delete this checklist? This cannot be undone.')) {
        return;
    }

    router.delete(route('checklists.destroy', props.checklist.id), { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
        <div class="flex items-center justify-between gap-3">
            <input
                v-if="isEditingName"
                ref="nameInput"
                v-model="nameForm.name"
                class="min-w-0 flex-1 truncate rounded bg-white px-1 text-sm font-semibold outline-none ring-2 ring-ring dark:bg-neutral-800"
                @blur="saveName"
                @keydown.enter="saveName"
                @keydown.escape="isEditingName = false"
            />
            <p
                v-else
                class="min-w-0 flex-1 cursor-text truncate rounded px-1 text-sm font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800"
                @click="startEditingName"
            >
                {{ checklist.name }}
            </p>

            <div class="flex shrink-0 items-center gap-3">
                <button
                    v-if="checklist.items.length"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="hideChecked = !hideChecked"
                >
                    {{ hideChecked ? 'Show checked items' : 'Hide checked items' }}
                </button>
                <button type="button" class="text-xs text-muted-foreground hover:text-destructive" @click="deleteChecklist">Delete</button>
            </div>
        </div>

        <div v-if="checklist.items.length" class="flex items-center gap-2">
            <span class="w-9 shrink-0 text-xs text-muted-foreground">{{ progress }}%</span>
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: `${progress}%` }" />
            </div>
        </div>

        <ul class="space-y-1">
            <li v-for="item in visibleItems" :key="item.id" class="group flex items-center gap-2">
                <input
                    :id="`checklist-item-${item.id}`"
                    type="checkbox"
                    :checked="item.is_checked"
                    class="size-4 shrink-0 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500 dark:border-neutral-600"
                    @change="toggleItem(item)"
                />
                <label
                    :for="`checklist-item-${item.id}`"
                    class="flex-1 cursor-pointer text-sm"
                    :class="item.is_checked ? 'text-muted-foreground line-through' : ''"
                >
                    {{ item.name }}
                </label>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="text-muted-foreground opacity-0 hover:text-destructive group-hover:opacity-100"
                            aria-label="Delete item"
                            @click="deleteItem(item)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Delete item</TooltipContent>
                </Tooltip>
            </li>
        </ul>

        <Button v-if="!showAddItem" variant="ghost" size="sm" class="justify-start text-muted-foreground" @click="showAddItem = true">
            + Add an item
        </Button>
        <form v-else class="flex gap-2" @submit.prevent="submitAddItem">
            <Input v-model="newItemName" placeholder="Add an item" autofocus />
            <Button type="submit" size="sm">Add</Button>
            <Button type="button" variant="ghost" size="sm" @click="showAddItem = false">Cancel</Button>
        </form>
    </div>
</template>
