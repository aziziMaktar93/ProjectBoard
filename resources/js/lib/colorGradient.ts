/**
 * Shared gradient helpers so every colored surface (workspace/board tiles,
 * page washes, list/card color strips) reads as one system instead of flat
 * fills. Built on CSS `color-mix()` so any arbitrary hex the user picks
 * (not just the fixed theme palette) gets a consistent lighter/darker
 * gradient without hand-rolled color math.
 */

const DEFAULT_TILE_COLOR = '#44546f';

export function tileGradient(color: string | null | undefined): string {
    const base = color || DEFAULT_TILE_COLOR;

    return `linear-gradient(135deg, color-mix(in srgb, ${base} 85%, white) 0%, ${base} 45%, color-mix(in srgb, ${base} 78%, black) 100%)`;
}

export function washGradient(color: string | null | undefined): string | undefined {
    if (!color) {
        return undefined;
    }

    return `linear-gradient(160deg, color-mix(in srgb, ${color} 14%, transparent) 0%, color-mix(in srgb, ${color} 30%, transparent) 100%)`;
}

export function stripGradient(color: string | null | undefined): string | undefined {
    if (!color) {
        return undefined;
    }

    return `linear-gradient(90deg, color-mix(in srgb, ${color} 65%, white) 0%, ${color} 100%)`;
}
