<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { BoardList, Card } from '@/types';
import { router } from '@inertiajs/vue3';

defineProps<{
    lists: BoardList[];
    cards: Card[];
}>();

const open = defineModel<boolean>('open', { default: false });

function restoreList(list: BoardList) {
    router.patch(route('board-lists.restore', list.id), {}, { preserveScroll: true });
}

function deleteList(list: BoardList) {
    if (!confirm(`Permanently delete the list "${list.name}" and all its cards? This cannot be undone.`)) {
        return;
    }

    router.delete(route('board-lists.destroy', list.id), { preserveScroll: true });
}

function restoreCard(card: Card) {
    router.patch(route('cards.restore', card.id), {}, { preserveScroll: true });
}

function deleteCard(card: Card) {
    if (!confirm(`Permanently delete the card "${card.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('cards.destroy', card.id), { preserveScroll: true });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Archive</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div>
                    <h3 class="mb-2 text-sm font-medium text-muted-foreground">Lists</h3>
                    <p v-if="lists.length === 0" class="text-sm text-muted-foreground">No archived lists.</p>
                    <ul class="space-y-2">
                        <li v-for="list in lists" :key="list.id" class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                            <span class="truncate">{{ list.name }}</span>
                            <div class="flex shrink-0 gap-1">
                                <Button variant="ghost" size="sm" @click="restoreList(list)">Restore</Button>
                                <Button variant="ghost" size="sm" @click="deleteList(list)">Delete</Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-medium text-muted-foreground">Cards</h3>
                    <p v-if="cards.length === 0" class="text-sm text-muted-foreground">No archived cards.</p>
                    <ul class="space-y-2">
                        <li v-for="card in cards" :key="card.id" class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                            <span class="truncate">{{ card.name }}</span>
                            <div class="flex shrink-0 gap-1">
                                <Button variant="ghost" size="sm" @click="restoreCard(card)">Restore</Button>
                                <Button variant="ghost" size="sm" @click="deleteCard(card)">Delete</Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
