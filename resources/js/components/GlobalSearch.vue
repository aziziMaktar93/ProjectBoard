<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Link } from '@inertiajs/vue3';
import { Kanban, Search, SquareCheck } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

interface SearchBoard {
    id: number;
    name: string;
    workspace_name: string;
}

interface SearchCard {
    id: number;
    name: string;
    board_id: number;
    board_name: string;
}

const open = ref(false);
const query = ref('');
const boards = ref<SearchBoard[]>([]);
const cards = ref<SearchCard[]>([]);
const searching = ref(false);
const hasSearched = ref(false);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestToken = 0;

watch(query, (value) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    const trimmed = value.trim();

    if (trimmed.length < 2) {
        boards.value = [];
        cards.value = [];
        hasSearched.value = false;
        return;
    }

    debounceTimer = setTimeout(() => runSearch(trimmed), 250);
});

async function runSearch(term: string) {
    searching.value = true;
    const token = ++requestToken;

    try {
        const response = await fetch(`${route('search')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (token !== requestToken) {
            return;
        }

        boards.value = data.boards;
        cards.value = data.cards;
        hasSearched.value = true;
    } finally {
        if (token === requestToken) {
            searching.value = false;
        }
    }
}

function close() {
    open.value = false;
    query.value = '';
    boards.value = [];
    cards.value = [];
    hasSearched.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = !open.value;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

defineExpose({ open });
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-2 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-accent"
        @click="open = true"
    >
        <Search class="size-4" />
        <span class="flex-1 text-left">Search...</span>
        <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium">Ctrl K</kbd>
    </button>

    <Dialog :open="open" @update:open="(value) => (value ? (open = true) : close())">
        <DialogContent class="top-[20%] max-w-lg translate-y-0 gap-3 p-0">
            <DialogHeader class="border-b border-border p-3 pb-0">
                <DialogTitle class="sr-only">Search</DialogTitle>
                <Input v-model="query" placeholder="Search boards and cards..." autofocus class="border-0 shadow-none focus-visible:ring-0" />
            </DialogHeader>

            <div class="max-h-96 overflow-y-auto px-3 pb-3">
                <p v-if="query.trim().length < 2" class="py-6 text-center text-sm text-muted-foreground">
                    Type at least 2 characters to search.
                </p>
                <p v-else-if="searching" class="py-6 text-center text-sm text-muted-foreground">Searching...</p>
                <p v-else-if="hasSearched && !boards.length && !cards.length" class="py-6 text-center text-sm text-muted-foreground">
                    No boards or cards matched "{{ query }}".
                </p>
                <template v-else>
                    <div v-if="boards.length" class="mb-3">
                        <p class="mb-1 px-1 text-xs font-semibold text-muted-foreground">Boards</p>
                        <Link
                            v-for="board in boards"
                            :key="`board-${board.id}`"
                            :href="route('boards.show', board.id)"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                            @click="close"
                        >
                            <Kanban class="size-4 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ board.name }}</span>
                            <span class="ml-auto shrink-0 truncate text-xs text-muted-foreground">{{ board.workspace_name }}</span>
                        </Link>
                    </div>

                    <div v-if="cards.length">
                        <p class="mb-1 px-1 text-xs font-semibold text-muted-foreground">Cards</p>
                        <Link
                            v-for="card in cards"
                            :key="`card-${card.id}`"
                            :href="route('boards.show', { board: card.board_id, card: card.id })"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                            @click="close"
                        >
                            <SquareCheck class="size-4 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ card.name }}</span>
                            <span class="ml-auto shrink-0 truncate text-xs text-muted-foreground">{{ card.board_name }}</span>
                        </Link>
                    </div>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
