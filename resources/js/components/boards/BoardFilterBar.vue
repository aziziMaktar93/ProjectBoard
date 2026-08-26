<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { BoardFilters } from '@/composables/useBoardFilters';
import type { CardLabel, User } from '@/types';
import { Check, ListFilter } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    labels: CardLabel[];
    members: User[];
}>();

const filters = defineModel<BoardFilters>('filters', { required: true });

const DUE_DATE_OPTIONS: { value: BoardFilters['dueDate']; label: string }[] = [
    { value: 'any', label: 'Any due date' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'week', label: 'Due within 7 days' },
    { value: 'none', label: 'No due date' },
];

const activeFilterCount = computed(
    () => filters.value.labelIds.length + filters.value.memberIds.length + (filters.value.dueDate !== 'any' ? 1 : 0),
);

function toggleLabel(labelId: number) {
    filters.value.labelIds = filters.value.labelIds.includes(labelId)
        ? filters.value.labelIds.filter((id) => id !== labelId)
        : [...filters.value.labelIds, labelId];
}

function toggleMember(memberId: number) {
    filters.value.memberIds = filters.value.memberIds.includes(memberId)
        ? filters.value.memberIds.filter((id) => id !== memberId)
        : [...filters.value.memberIds, memberId];
}

function clearFilters() {
    filters.value = { labelIds: [], memberIds: [], dueDate: 'any' };
}
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button variant="outline" size="sm" :class="activeFilterCount > 0 ? 'border-primary text-primary' : ''">
                <ListFilter class="size-3.5" />
                Filter
                <span v-if="activeFilterCount > 0" class="ml-1 rounded-full bg-primary px-1.5 text-xs text-primary-foreground">
                    {{ activeFilterCount }}
                </span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-72" align="start">
            <div class="space-y-4">
                <div v-if="props.labels.length">
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Labels</p>
                    <ul class="space-y-1">
                        <li
                            v-for="label in props.labels"
                            :key="label.id"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-1.5 text-sm text-white hover:opacity-90"
                            :style="{ backgroundColor: label.color }"
                            @click="toggleLabel(label.id)"
                        >
                            <span class="font-medium drop-shadow-sm">{{ label.name }}</span>
                            <Check v-if="filters.labelIds.includes(label.id)" class="size-4 shrink-0 drop-shadow-sm" />
                        </li>
                    </ul>
                </div>

                <div v-if="props.members.length">
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Members</p>
                    <ul class="space-y-1">
                        <li
                            v-for="member in props.members"
                            :key="member.id"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-1.5 text-sm hover:bg-accent"
                            @click="toggleMember(member.id)"
                        >
                            <span class="flex items-center gap-2">
                                <MemberAvatar :user="member" size="xs" />
                                {{ member.name }}
                            </span>
                            <Check v-if="filters.memberIds.includes(member.id)" class="size-4 shrink-0" />
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Due date</p>
                    <ul class="space-y-1">
                        <li
                            v-for="option in DUE_DATE_OPTIONS"
                            :key="option.value"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-1.5 text-sm hover:bg-accent"
                            @click="filters.dueDate = option.value"
                        >
                            <span>{{ option.label }}</span>
                            <Check v-if="filters.dueDate === option.value" class="size-4 shrink-0" />
                        </li>
                    </ul>
                </div>

                <Button v-if="activeFilterCount > 0" variant="ghost" size="sm" class="w-full" @click="clearFilters">
                    Clear filters
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
