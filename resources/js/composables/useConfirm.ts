import { reactive } from 'vue';

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
}

interface ConfirmState extends Required<Omit<ConfirmOptions, 'description'>> {
    open: boolean;
    description?: string;
    resolve: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmState>({
    open: false,
    title: '',
    description: undefined,
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
    resolve: null,
});

export function confirmDialog(options: ConfirmOptions | string): Promise<boolean> {
    const opts = typeof options === 'string' ? { title: options } : options;

    return new Promise((resolve) => {
        // Deferred so this dialog opens after a still-closing Radix overlay (e.g. a
        // DropdownMenuItem's own click handler) finishes its unmount cleanup — opening
        // in the same tick leaves the new dialog's buttons unresponsive to clicks.
        setTimeout(() => {
            state.title = opts.title;
            state.description = opts.description;
            state.confirmText = opts.confirmText ?? 'Confirm';
            state.cancelText = opts.cancelText ?? 'Cancel';
            state.variant = opts.variant ?? 'default';
            state.resolve = resolve;
            state.open = true;
        }, 0);
    });
}

export function respondConfirm(value: boolean) {
    state.open = false;
    state.resolve?.(value);
    state.resolve = null;
}

export function useConfirm() {
    return { state };
}
