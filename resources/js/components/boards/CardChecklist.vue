<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import ChecklistItemMemberPicker from '@/components/boards/ChecklistItemMemberPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { celebrate } from '@/composables/useCelebration';
import type { Checklist, ChecklistItem, User } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Trash2, Users } from 'lucide-vue-next';
import { computed, nextTick, ref, watch, type ComponentPublicInstance } from 'vue';

const props = defineProps<{
    checklist: Checklist;
    canEdit: boolean;
    boardMembers: User[];
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

watch(progress, (current: number, previous: number) => {
    if (current === 100 && previous < 100 && props.checklist.items.length > 0) {
        celebrate();
    }
});

function toggleItem(item: ChecklistItem) {
    router.patch(route('checklist-items.update', item.id), { is_checked: !item.is_checked }, { preserveScroll: true });
}

const editingItemId = ref<number | null>(null);
const editingItemName = ref('');
const editingItemInput = ref<HTMLInputElement | null>(null);

function setEditingItemInputRef(el: Element | ComponentPublicInstance | null) {
    editingItemInput.value = el as HTMLInputElement | null;
}

async function startEditingItem(item: ChecklistItem) {
    editingItemId.value = item.id;
    editingItemName.value = item.name;
    await nextTick();
    editingItemInput.value?.focus();
    editingItemInput.value?.select();
}

function saveItemName(item: ChecklistItem) {
    editingItemId.value = null;

    if (!editingItemName.value.trim() || editingItemName.value === item.name) {
        return;
    }

    router.patch(route('checklist-items.update', item.id), { name: editingItemName.value }, { preserveScroll: true });
}

function deleteItem(item: ChecklistItem) {
    router.delete(route('checklist-items.destroy', item.id), { preserveScroll: true });
}

function setItemDueDate(item: ChecklistItem, value: string) {
    router.patch(route('checklist-items.update', item.id), { due_date: value || null }, { preserveScroll: true });
}

function itemDueDateLabel(item: ChecklistItem): string | null {
    if (!item.due_date) {
        return null;
    }

    return new Date(`${item.due_date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function isItemOverdue(item: ChecklistItem): boolean {
    if (!item.due_date || item.is_checked) {
        return false;
    }

    return item.due_date < new Date().toISOString().slice(0, 10);
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

function duplicateChecklist() {
    router.post(route('checklists.duplicate', props.checklist.id), {}, { preserveScroll: true });
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
                class="min-w-0 flex-1 truncate rounded px-1 text-sm font-semibold"
                :class="canEdit ? 'cursor-text hover:bg-neutral-100 dark:hover:bg-neutral-800' : ''"
                @click="canEdit && startEditingName()"
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
                <template v-if="canEdit">
                    <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="duplicateChecklist">
                        Duplicate
                    </button>
                    <button type="button" class="text-xs text-muted-foreground hover:text-destructive" @click="deleteChecklist">Delete</button>
                </template>
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
                    type="checkbox"
                    :checked="item.is_checked"
                    :disabled="!canEdit"
                    :aria-labelledby="`checklist-item-label-${item.id}`"
                    class="size-4 shrink-0 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500 dark:border-neutral-600"
                    @change="toggleItem(item)"
                />
                <input
                    v-if="editingItemId === item.id"
                    :ref="setEditingItemInputRef"
                    v-model="editingItemName"
                    class="min-w-0 flex-1 truncate rounded bg-white px-1 text-sm outline-none ring-2 ring-ring dark:bg-neutral-800"
                    @blur="saveItemName(item)"
                    @keydown.enter="saveItemName(item)"
                    @keydown.escape="editingItemId = null"
                />
                <span
                    v-else
                    :id="`checklist-item-label-${item.id}`"
                    class="min-w-0 flex-1 truncate rounded px-1 text-sm"
                    :class="[
                        item.is_checked ? 'text-muted-foreground line-through' : '',
                        canEdit ? 'cursor-text hover:bg-neutral-100 dark:hover:bg-neutral-800' : '',
                    ]"
                    @click="canEdit && startEditingItem(item)"
                >
                    {{ item.name }}
                </span>
                <span
                    v-if="itemDueDateLabel(item)"
                    class="shrink-0 rounded px-1.5 py-0.5 text-[11px]"
                    :class="
                        isItemOverdue(item)
                            ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                    "
                >
                    {{ itemDueDateLabel(item) }}
                </span>
                <div v-if="item.members?.length" class="flex shrink-0 -space-x-1.5">
                    <MemberAvatar v-for="member in item.members" :key="member.id" :user="member" size="xs" />
                </div>
                <Popover v-if="canEdit">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            title="Assign member"
                            class="shrink-0 text-muted-foreground opacity-0 hover:text-foreground group-hover:opacity-100"
                            :class="{ '!opacity-100': item.members?.length }"
                            aria-label="Assign member"
                        >
                            <Users class="size-3.5" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-64">
                        <p class="mb-2 text-xs font-semibold text-muted-foreground">Assign member</p>
                        <ChecklistItemMemberPicker :item="item" :board-members="boardMembers" />
                    </PopoverContent>
                </Popover>
                <Popover v-if="canEdit">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            title="Due date"
                            class="shrink-0 text-muted-foreground opacity-0 hover:text-foreground group-hover:opacity-100"
                            :class="{ '!opacity-100': item.due_date }"
                            aria-label="Set due date"
                        >
                            <CalendarDays class="size-3.5" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-64">
                        <p class="mb-2 text-xs font-semibold text-muted-foreground">Due date</p>
                        <div class="flex items-center gap-2">
                            <Input
                                type="date"
                                :model-value="item.due_date ?? ''"
                                @change="setItemDueDate(item, ($event.target as HTMLInputElement).value)"
                            />
                            <Button v-if="item.due_date" type="button" variant="ghost" size="sm" @click="setItemDueDate(item, '')">
                                Clear
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>
                <Tooltip v-if="canEdit">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="shrink-0 text-muted-foreground opacity-0 hover:text-destructive group-hover:opacity-100"
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

        <template v-if="canEdit">
            <Button v-if="!showAddItem" variant="ghost" size="sm" class="justify-start text-muted-foreground" @click="showAddItem = true">
                + Add an item
            </Button>
            <form v-else class="flex gap-2" @submit.prevent="submitAddItem">
                <Input v-model="newItemName" placeholder="Add an item" autofocus />
                <Button type="submit" size="sm">Add</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAddItem = false">Cancel</Button>
            </form>
        </template>
    </div>
</template>
