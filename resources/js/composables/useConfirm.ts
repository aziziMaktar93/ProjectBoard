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
        state.title = opts.title;
        state.description = opts.description;
        state.confirmText = opts.confirmText ?? 'Confirm';
        state.cancelText = opts.cancelText ?? 'Cancel';
        state.variant = opts.variant ?? 'default';
        state.resolve = resolve;
        state.open = true;
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
