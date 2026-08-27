import { onMounted, ref } from 'vue';

export type ColorTheme = 'neutral' | 'blue' | 'green' | 'orange' | 'rose' | 'violet';

const STORAGE_KEY = 'color-theme';

export function applyColorTheme(theme: ColorTheme) {
    if (theme === 'neutral') {
        document.documentElement.removeAttribute('data-color-theme');
    } else {
        document.documentElement.setAttribute('data-color-theme', theme);
    }
}

export function initializeColorTheme() {
    const saved = (localStorage.getItem(STORAGE_KEY) as ColorTheme | null) ?? 'blue';
    applyColorTheme(saved);
}

export function useColorTheme() {
    const colorTheme = ref<ColorTheme>('blue');

    onMounted(() => {
        const saved = (localStorage.getItem(STORAGE_KEY) as ColorTheme | null) ?? 'blue';
        colorTheme.value = saved;
        applyColorTheme(saved);
    });

    function updateColorTheme(value: ColorTheme) {
        colorTheme.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        applyColorTheme(value);
    }

    return {
        colorTheme,
        updateColorTheme,
    };
}
