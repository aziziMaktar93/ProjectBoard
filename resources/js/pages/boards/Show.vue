<script setup lang="ts">
import HoverLabel from '@/components/HoverLabel.vue';
import AiChatWidget from '@/components/boards/AiChatWidget.vue';
import ArchivePanel from '@/components/boards/ArchivePanel.vue';
import BoardChatWidget from '@/components/boards/BoardChatWidget.vue';
import BoardFilterBar from '@/components/boards/BoardFilterBar.vue';
import BoardListColumn from '@/components/boards/BoardListColumn.vue';
import BoardMemberPanel from '@/components/boards/BoardMemberPanel.vue';
import CardDetailModal from '@/components/boards/CardDetailModal.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useBoardFilters } from '@/composables/useBoardFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { washGradient } from '@/lib/colorGradient';
import type { Board, BoardList, BreadcrumbItem, Card, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Archive, CalendarDays, CheckSquare, MoreHorizontal, Tag, Users, X } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    board: Board;
    archivedLists: BoardList[];
    archivedCards: Card[];
    initialCardId: number | null;
    canEdit: boolean;
    canManageDueDates: boolean;
    aiEnabled: boolean;
}>();

const currentUserId = computed(() => usePage<SharedData>().props.auth.user.id);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Workspaces', href: route('workspaces.index') },
    { title: props.board.workspace?.name ?? '', href: route('workspaces.show', props.board.workspace_id) },
    { title: props.board.name, href: route('boards.show', props.board.id) },
]);

const lists = ref<BoardList[]>(props.board.lists ?? []);
const cardGroup = `cards-board-${props.board.id}`;
const reorderError = ref<string | null>(null);
const { filters, cardMatchesFilters } = useBoardFilters();

const collapsedListsStorageKey = `board-${props.board.id}-collapsed-lists`;
const collapsedLists = ref<Record<number, boolean>>({});

function persistCollapsedLists() {
    try {
        localStorage.setItem(collapsedListsStorageKey, JSON.stringify(collapsedLists.value));
    } catch {
        // Best-effort only — a private window or disabled storage just means
        // collapsed state won't survive a reload.
    }
}

function toggleListCollapse(listId: number) {
    collapsedLists.value = { ...collapsedLists.value, [listId]: !collapsedLists.value[listId] };
    persistCollapsedLists();
}

function expandAllLists() {
    collapsedLists.value = {};
    persistCollapsedLists();
}

function collapseAllLists() {
    collapsedLists.value = Object.fromEntries(lists.value.map((list: BoardList): [number, boolean] => [list.id, true]));
    persistCollapsedLists();
}

watch(
    () => props.board.lists,
    (newLists: BoardList[] | undefined) => {
        lists.value = newLists ?? [];

        // The card detail modal holds its own snapshot (`activeCard`) of whichever
        // card is open. Without this, checklist/edit actions taken while the modal
        // is open would persist correctly server-side but never appear in the
        // modal itself, since `activeCard` would keep pointing at the pre-update
        // object — the same staleness this file's `lists` resync exists to avoid.
        if (activeCard.value) {
            for (const list of lists.value) {
                const match = list.cards.find((card: Card) => card.id === activeCard.value?.id);

                if (match) {
                    activeCard.value = match;
                    break;
                }
            }
        }
    },
);

// Snapshots captured at drag-start, since vue-draggable-plus mutates the
// v-model array as soon as the item is dropped — by the time the "end"
// handlers below run, `lists.value` already reflects the new (unsaved)
// order. These snapshots preserve the last known-good order so we can roll
// back if the server rejects or fails to persist the change.
let listOrderSnapshot: BoardList[] | null = null;
let cardOrderSnapshot: Map<number, Card[]> | null = null;

function onListDragStart() {
    listOrderSnapshot = [...lists.value];
}

function onListDragEnd() {
    const previousOrder = listOrderSnapshot ?? [...lists.value];

    router.patch(
        route('board-lists.reorder', props.board.id),
        { ordered_ids: lists.value.map((list) => list.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reorderError.value = null;
            },
            onError: () => {
                lists.value = previousOrder;
                reorderError.value = 'Could not save the new list order. Please try again.';
            },
        },
    );
}

function onCardDragStart() {
    cardOrderSnapshot = new Map(lists.value.map((list: BoardList): [number, Card[]] => [list.id, [...list.cards]]));
}

function onCardDragEnd(event: { from: HTMLElement; to: HTMLElement }) {
    const fromListId = Number(event.from.dataset.listId);
    const toListId = Number(event.to.dataset.listId);
    const targetList = lists.value.find((list) => list.id === toListId);

    if (!targetList) {
        return;
    }

    const snapshot = cardOrderSnapshot;

    const payload: Record<string, unknown> = {
        target_list_id: toListId,
        target_ordered_ids: targetList.cards.map((card) => card.id),
    };

    if (fromListId !== toListId) {
        const sourceList = lists.value.find((list) => list.id === fromListId);

        if (sourceList) {
            payload.source_list_id = fromListId;
            payload.source_ordered_ids = sourceList.cards.map((card) => card.id);
        }
    }

    router.patch(route('cards.reorder', props.board.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            reorderError.value = null;
        },
        onError: () => {
            if (snapshot) {
                const target = lists.value.find((list: BoardList) => list.id === toListId);
                if (target) {
                    target.cards = snapshot.get(toListId) ?? target.cards;
                }

                if (fromListId !== toListId) {
                    const source = lists.value.find((list: BoardList) => list.id === fromListId);
                    if (source) {
                        source.cards = snapshot.get(fromListId) ?? source.cards;
                    }
                }
            }

            reorderError.value = 'Could not save the new card order. Please try again.';
        },
    });
}

const showAddList = ref(false);

const addListForm = useForm({
    name: '',
});

function submitAddList() {
    addListForm.post(route('board-lists.store', props.board.id), {
        preserveScroll: true,
        onSuccess: () => {
            addListForm.reset();
            showAddList.value = false;
        },
    });
}

const activeCard = ref<Card | null>(null);
const showCardModal = ref(false);
const showArchive = ref(false);
const showMembers = ref(false);

function openCard(card: Card) {
    activeCard.value = card;
    showCardModal.value = true;
}

onMounted(() => {
    try {
        const raw = localStorage.getItem(collapsedListsStorageKey);
        collapsedLists.value = raw ? JSON.parse(raw) : {};
    } catch {
        collapsedLists.value = {};
    }

    if (!props.initialCardId) {
        return;
    }

    for (const list of lists.value) {
        const match = list.cards.find((card: Card) => card.id === props.initialCardId);

        if (match) {
            openCard(match);
            break;
        }
    }
});

function archiveBoard() {
    if (!confirm(`Archive the board "${props.board.name}"?`)) {
        return;
    }

    router.patch(route('boards.archive', props.board.id));
}

const isEditingBoardName = ref(false);
const boardNameInput = ref<HTMLInputElement | null>(null);

const boardNameForm = useForm({
    name: props.board.name,
});

async function startEditingBoardName() {
    boardNameForm.name = props.board.name;
    isEditingBoardName.value = true;
    await nextTick();
    boardNameInput.value?.focus();
    boardNameInput.value?.select();
}

function saveBoardName() {
    isEditingBoardName.value = false;

    if (!boardNameForm.name.trim() || boardNameForm.name === props.board.name) {
        boardNameForm.name = props.board.name;
        return;
    }

    boardNameForm.patch(route('boards.update', props.board.id), { preserveScroll: true });
}

function onBoardColorChange(color: string | null) {
    router.patch(route('boards.update', props.board.id), { background_color: color }, { preserveScroll: true });
}

const selectMode = ref(false);
const selectedCardIds = ref<Set<number>>(new Set());
const bulkProcessing = ref(false);

function toggleSelectMode() {
    selectMode.value = !selectMode.value;
    selectedCardIds.value = new Set();
}

function toggleCardSelection(cardId: number) {
    const next = new Set(selectedCardIds.value);

    if (next.has(cardId)) {
        next.delete(cardId);
    } else {
        next.add(cardId);
    }

    selectedCardIds.value = next;
}

function clearSelection() {
    selectMode.value = false;
    selectedCardIds.value = new Set();
}

function bulkArchive() {
    if (!confirm(`Archive ${selectedCardIds.value.size} selected card(s)?`)) {
        return;
    }

    bulkProcessing.value = true;
    router.post(
        route('cards.bulk-archive', props.board.id),
        { card_ids: Array.from(selectedCardIds.value) },
        {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
            onFinish: () => {
                bulkProcessing.value = false;
            },
        },
    );
}

function bulkMove(boardListId: string) {
    bulkProcessing.value = true;
    router.post(
        route('cards.bulk-move', props.board.id),
        { card_ids: Array.from(selectedCardIds.value), board_list_id: Number(boardListId) },
        {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
            onFinish: () => {
                bulkProcessing.value = false;
            },
        },
    );
}

function bulkAddLabel(labelId: number) {
    bulkProcessing.value = true;
    router.post(
        route('cards.bulk-label', props.board.id),
        { card_ids: Array.from(selectedCardIds.value), label_id: labelId },
        {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
            onFinish: () => {
                bulkProcessing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="board.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="reorderError" class="mx-4 mt-4 flex items-center justify-between gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900 dark:bg-red-950">
            <p class="text-sm text-red-600 dark:text-red-500">{{ reorderError }}</p>
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="text-sm text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-300"
                        aria-label="Dismiss error"
                        @click="reorderError = null"
                    >
                        &times;
                    </button>
                </TooltipTrigger>
                <TooltipContent>Dismiss</TooltipContent>
            </Tooltip>
        </div>

        <div class="flex flex-1 flex-col rounded-xl" :style="{ backgroundImage: washGradient(board.background_color) }">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-black/5 p-4 dark:border-white/5">
                <div class="flex min-w-0 items-center gap-2">
                    <span v-if="board.background_color" class="size-3 shrink-0 rounded-full" :style="{ backgroundColor: board.background_color }" />
                    <input
                        v-if="isEditingBoardName"
                        ref="boardNameInput"
                        v-model="boardNameForm.name"
                        class="min-w-0 rounded bg-transparent px-1 text-xl font-semibold tracking-tight outline-none ring-2 ring-ring"
                        @blur="saveBoardName"
                        @keydown.enter="saveBoardName"
                        @keydown.escape="isEditingBoardName = false"
                    />
                    <h1
                        v-else
                        class="min-w-0 truncate rounded px-1 text-xl font-semibold tracking-tight"
                        :class="canEdit ? 'cursor-text hover:bg-accent' : ''"
                        @click="canEdit && startEditingBoardName()"
                    >
                        {{ board.name }}
                    </h1>
                    <span
                        v-if="!canEdit"
                        class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                    >
                        View only
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <BoardFilterBar v-model:filters="filters" :labels="board.labels ?? []" :members="board.members ?? []" />
                    <Button
                        v-if="canEdit"
                        :variant="selectMode ? 'default' : 'outline'"
                        size="sm"
                        @click="toggleSelectMode"
                    >
                        <CheckSquare class="size-3.5" />
                        <span class="hidden sm:inline">{{ selectMode ? 'Cancel select' : 'Select' }}</span>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="route('boards.calendar', board.id)">
                            <CalendarDays class="size-3.5" />
                            <span class="hidden sm:inline">Calendar</span>
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" @click="showMembers = true">
                        <Users class="size-3.5" />
                        <span class="hidden sm:inline">Members ({{ (board.members ?? []).length }})</span>
                        <span class="sm:hidden">{{ (board.members ?? []).length }}</span>
                    </Button>
                    <Button variant="outline" size="sm" @click="showArchive = true">
                        <Archive class="size-3.5" />
                        <span class="hidden sm:inline">View archive</span>
                    </Button>

                    <DropdownMenu>
                        <HoverLabel label="Board actions">
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                    aria-label="Board actions"
                                >
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                        </HoverLabel>
                        <DropdownMenuContent align="end" class="w-56">
                            <template v-if="canEdit">
                                <DropdownMenuItem @click="startEditingBoardName">Rename board</DropdownMenuItem>
                                <DropdownMenuItem @click="archiveBoard">Archive board</DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>Board color</DropdownMenuLabel>
                                <div class="px-2 pb-1">
                                    <ColorSwatchPicker :model-value="board.background_color" @update:model-value="onBoardColorChange" />
                                </div>
                                <DropdownMenuSeparator />
                            </template>
                            <DropdownMenuItem @click="expandAllLists">Expand all lists</DropdownMenuItem>
                            <DropdownMenuItem @click="collapseAllLists">Collapse all lists</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div class="flex flex-1 items-start gap-4 overflow-x-auto p-4 pt-0">
                <VueDraggable
                    v-model="lists"
                    item-key="id"
                    :animation="150"
                    :disabled="!canEdit || selectMode"
                    handle=".list-drag-handle"
                    class="flex items-start gap-4"
                    @start="onListDragStart"
                    @end="onListDragEnd"
                >
                    <BoardListColumn
                        v-for="list in lists"
                        :key="list.id"
                        :list="list"
                        :group="cardGroup"
                        :can-edit="canEdit"
                        :collapsed="!!collapsedLists[list.id]"
                        :select-mode="selectMode"
                        :selected-card-ids="selectedCardIds"
                        :matches-filters="cardMatchesFilters"
                        @open-card="openCard"
                        @toggle-select="toggleCardSelection"
                        @toggle-collapse="toggleListCollapse(list.id)"
                        @card-drag-start="onCardDragStart"
                        @card-drag-end="onCardDragEnd"
                    />
                </VueDraggable>

                <div v-if="canEdit" class="w-72 shrink-0">
                    <Button
                        v-if="!showAddList"
                        variant="ghost"
                        size="sm"
                        class="w-full justify-start border border-dashed border-neutral-300 bg-white/80 text-neutral-600 shadow-sm hover:bg-white hover:text-neutral-800 dark:border-neutral-700 dark:bg-neutral-900/80 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-neutral-100"
                        @click="showAddList = true"
                    >
                        + Add another list
                    </Button>
                    <form v-else class="flex flex-col gap-2" @submit.prevent="submitAddList">
                        <Input v-model="addListForm.name" placeholder="List name" autofocus />
                        <div class="flex gap-2">
                            <Button type="submit" size="sm" :disabled="addListForm.processing">Add</Button>
                            <Button type="button" variant="ghost" size="sm" @click="showAddList = false">Cancel</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="selectedCardIds.size > 0"
            class="fixed bottom-4 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 shadow-xl dark:border-neutral-700 dark:bg-neutral-900"
        >
            <span class="text-sm font-medium">{{ selectedCardIds.size }} selected</span>

            <Select @update:model-value="(value) => bulkMove(String(value))">
                <SelectTrigger class="h-8 w-40 text-xs" :disabled="bulkProcessing">
                    <SelectValue placeholder="Move to..." />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="list in lists" :key="list.id" :value="String(list.id)">{{ list.name }}</SelectItem>
                </SelectContent>
            </Select>

            <Popover v-if="(board.labels ?? []).length">
                <PopoverTrigger as-child>
                    <Button variant="outline" size="sm" :disabled="bulkProcessing">
                        <Tag class="size-3.5" /> Add label
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-56">
                    <p class="mb-2 text-xs font-semibold text-muted-foreground">Add label to selected cards</p>
                    <ul class="space-y-1">
                        <li
                            v-for="label in board.labels"
                            :key="label.id"
                            class="flex cursor-pointer items-center gap-2 rounded-md p-1.5 text-sm text-white hover:opacity-90"
                            :style="{ backgroundColor: label.color }"
                            @click="bulkAddLabel(label.id)"
                        >
                            <span class="font-medium drop-shadow-sm">{{ label.name }}</span>
                        </li>
                    </ul>
                </PopoverContent>
            </Popover>

            <Button variant="outline" size="sm" :disabled="bulkProcessing" @click="bulkArchive">
                <Archive class="size-3.5" /> Archive
            </Button>

            <Button variant="ghost" size="sm" @click="clearSelection">
                <X class="size-3.5" /> Cancel
            </Button>
        </div>

        <CardDetailModal
            v-model:open="showCardModal"
            :card="activeCard"
            :board-id="board.id"
            :board-members="board.members ?? []"
            :board-labels="board.labels ?? []"
            :can-edit="canEdit"
            :can-manage-due-dates="canManageDueDates"
        />
        <ArchivePanel v-model:open="showArchive" :lists="archivedLists" :cards="archivedCards" />
        <BoardMemberPanel v-model:open="showMembers" :board="board" :workspace-members="board.workspace?.members ?? []" :can-edit="canEdit" />
        <AiChatWidget :board-id="board.id" :ai-enabled="aiEnabled" :can-edit="canEdit" />
        <BoardChatWidget :board-id="board.id" :current-user-id="currentUserId" :members="board.members ?? []" />
    </AppLayout>
</template>
