export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';
export type ThemeStyle = 'default' | 'custom';
export type ThemeNeutral = 'graphite' | 'neutral' | 'warm';
export type ThemeRadius = '2' | '4' | '6';
export type ThemeDensity = 'compact' | 'standard';

export type ThemeSettings = {
    appearance: Appearance;
    style: ThemeStyle;
    brand: string;
    neutral: ThemeNeutral;
    radius: ThemeRadius;
    density: ThemeDensity;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};
