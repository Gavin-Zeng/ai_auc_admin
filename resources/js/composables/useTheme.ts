import type { DeepReadonly, Ref } from 'vue';
import { readonly, ref } from 'vue';
import type {
    Appearance,
    ResolvedAppearance,
    ThemeDensity,
    ThemeNeutral,
    ThemeRadius,
    ThemeSettings,
    ThemeStyle,
} from '@/types';

export const DEFAULT_THEME_SETTINGS: ThemeSettings = {
    appearance: 'system',
    style: 'default',
    brand: '#0F766E',
    neutral: 'graphite',
    radius: '4',
    density: 'standard',
};

export const CUSTOM_THEME_DEFAULTS = {
    brand: DEFAULT_THEME_SETTINGS.brand,
    neutral: DEFAULT_THEME_SETTINGS.neutral,
    radius: DEFAULT_THEME_SETTINGS.radius,
    density: DEFAULT_THEME_SETTINGS.density,
} as const;

export const BRAND_PRESETS = [
    { label: '深青', value: '#0F766E' },
    { label: '深蓝', value: '#1D4ED8' },
    { label: '靛蓝', value: '#4338CA' },
    { label: '玫红', value: '#9F1239' },
] as const;

type UseThemeReturn = {
    settings: DeepReadonly<Ref<ThemeSettings>>;
    refreshTheme: () => ThemeSettings;
    previewTheme: (settings: ThemeSettings) => void;
    saveTheme: (settings: ThemeSettings) => ThemeSettings;
    restoreTheme: () => void;
};

const appearances: Appearance[] = ['light', 'dark', 'system'];
const styles: ThemeStyle[] = ['default', 'custom'];
const neutrals: ThemeNeutral[] = ['graphite', 'neutral', 'warm'];
const radii: ThemeRadius[] = ['2', '4', '6'];
const densities: ThemeDensity[] = ['compact', 'standard'];
const brandPattern = /^#[0-9A-Fa-f]{6}$/;
const committedSettings = ref<ThemeSettings>({ ...DEFAULT_THEME_SETTINGS });

function includes<T extends string>(
    values: readonly T[],
    value: string | undefined,
    fallback: T,
): T {
    return value && values.includes(value as T) ? (value as T) : fallback;
}

function normalizedBrand(value: string | undefined): string {
    return value && brandPattern.test(value)
        ? value.toUpperCase()
        : DEFAULT_THEME_SETTINGS.brand;
}

function normalizeThemeSettings(settings: ThemeSettings): ThemeSettings {
    return {
        appearance: includes(
            appearances,
            settings.appearance,
            DEFAULT_THEME_SETTINGS.appearance,
        ),
        style: includes(styles, settings.style, DEFAULT_THEME_SETTINGS.style),
        brand: normalizedBrand(settings.brand),
        neutral: includes(
            neutrals,
            settings.neutral,
            DEFAULT_THEME_SETTINGS.neutral,
        ),
        radius: includes(radii, settings.radius, DEFAULT_THEME_SETTINGS.radius),
        density: includes(
            densities,
            settings.density,
            DEFAULT_THEME_SETTINGS.density,
        ),
    };
}

function readThemeSettings(): ThemeSettings {
    if (typeof document === 'undefined') {
        return { ...DEFAULT_THEME_SETTINGS };
    }

    const root = document.documentElement;

    return normalizeThemeSettings({
        appearance: includes(
            appearances,
            root.dataset.appearance,
            DEFAULT_THEME_SETTINGS.appearance,
        ),
        style: includes(
            styles,
            root.dataset.uiTheme,
            DEFAULT_THEME_SETTINGS.style,
        ),
        brand: normalizedBrand(
            root.dataset.themeBrand
                ? `#${root.dataset.themeBrand.replace(/^#/, '')}`
                : undefined,
        ),
        neutral: includes(
            neutrals,
            root.dataset.themeNeutral,
            DEFAULT_THEME_SETTINGS.neutral,
        ),
        radius: includes(
            radii,
            root.dataset.themeRadius,
            DEFAULT_THEME_SETTINGS.radius,
        ),
        density: includes(
            densities,
            root.dataset.themeDensity,
            DEFAULT_THEME_SETTINGS.density,
        ),
    });
}

function resolveAppearance(appearance: Appearance): ResolvedAppearance {
    if (
        appearance === 'dark' ||
        (appearance === 'system' &&
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)
    ) {
        return 'dark';
    }

    return 'light';
}

function hexToRgb(value: string): [number, number, number] {
    const normalized = normalizedBrand(value).slice(1);

    return [
        Number.parseInt(normalized.slice(0, 2), 16),
        Number.parseInt(normalized.slice(2, 4), 16),
        Number.parseInt(normalized.slice(4, 6), 16),
    ];
}

export function applyThemeSettings(settings: ThemeSettings): ThemeSettings {
    const normalized = normalizeThemeSettings(settings);

    if (typeof document === 'undefined') {
        return normalized;
    }

    const root = document.documentElement;
    const [red, green, blue] = hexToRgb(normalized.brand);

    root.dataset.appearance = normalized.appearance;
    root.dataset.uiTheme = normalized.style;
    root.dataset.themeBrand = normalized.brand.slice(1);
    root.dataset.themeNeutral = normalized.neutral;
    root.dataset.themeRadius = normalized.radius;
    root.dataset.themeDensity = normalized.density;
    root.classList.toggle(
        'dark',
        resolveAppearance(normalized.appearance) === 'dark',
    );

    if (normalized.style === 'custom') {
        root.style.setProperty('--app-theme-primary', normalized.brand);
        root.style.setProperty(
            '--app-theme-primary-rgb',
            `${red}, ${green}, ${blue}`,
        );
    } else {
        root.style.removeProperty('--app-theme-primary');
        root.style.removeProperty('--app-theme-primary-rgb');
    }

    return normalized;
}

function setCookie(name: string, value: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${name}=${encodeURIComponent(value)}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

function persistThemeSettings(settings: ThemeSettings): void {
    setCookie('appearance', settings.appearance);
    setCookie('ui_theme', settings.style);
    setCookie('theme_brand', settings.brand.slice(1));
    setCookie('theme_neutral', settings.neutral);
    setCookie('theme_radius', settings.radius);
    setCookie('theme_density', settings.density);
}

function relativeLuminance([red, green, blue]: [
    number,
    number,
    number,
]): number {
    const [r, g, b] = [red, green, blue].map((channel) => {
        const value = channel / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastWithWhite(value: string): number {
    if (!brandPattern.test(value)) {
        return 0;
    }

    return 1.05 / (relativeLuminance(hexToRgb(value)) + 0.05);
}

let systemListenerInitialized = false;

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    committedSettings.value = applyThemeSettings(readThemeSettings());

    if (!systemListenerInitialized) {
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                applyThemeSettings(readThemeSettings());
            });
        systemListenerInitialized = true;
    }
}

export function useTheme(): UseThemeReturn {
    function refreshTheme(): ThemeSettings {
        committedSettings.value = readThemeSettings();

        return { ...committedSettings.value };
    }

    function previewTheme(settings: ThemeSettings): void {
        applyThemeSettings(settings);
    }

    function saveTheme(settings: ThemeSettings): ThemeSettings {
        const normalized = applyThemeSettings(settings);

        committedSettings.value = normalized;
        persistThemeSettings(normalized);

        return { ...normalized };
    }

    function restoreTheme(): void {
        applyThemeSettings(committedSettings.value);
    }

    return {
        settings: readonly(committedSettings),
        refreshTheme,
        previewTheme,
        saveTheme,
        restoreTheme,
    };
}
