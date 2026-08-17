<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class ThemePreferences
{
    public const DEFAULT_APPEARANCE = 'system';

    public const DEFAULT_STYLE = 'default';

    public const DEFAULT_BRAND = '0F766E';

    public const DEFAULT_NEUTRAL = 'graphite';

    public const DEFAULT_RADIUS = '4';

    public const DEFAULT_DENSITY = 'standard';

    public function __construct(
        public string $appearance,
        public string $style,
        public string $brand,
        public string $neutral,
        public string $radius,
        public string $density,
        public bool $appearanceCookiePresent,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            appearance: self::allowedCookie(
                $request,
                'appearance',
                ['light', 'dark', 'system'],
                self::DEFAULT_APPEARANCE,
            ),
            style: self::allowedCookie(
                $request,
                'ui_theme',
                ['default', 'custom'],
                self::DEFAULT_STYLE,
            ),
            brand: self::brandCookie($request),
            neutral: self::allowedCookie(
                $request,
                'theme_neutral',
                ['graphite', 'neutral', 'warm'],
                self::DEFAULT_NEUTRAL,
            ),
            radius: self::allowedCookie(
                $request,
                'theme_radius',
                ['2', '4', '6'],
                self::DEFAULT_RADIUS,
            ),
            density: self::allowedCookie(
                $request,
                'theme_density',
                ['compact', 'standard'],
                self::DEFAULT_DENSITY,
            ),
            appearanceCookiePresent: $request->cookies->has('appearance'),
        );
    }

    /**
     * @return array{
     *     appearance: string,
     *     uiTheme: string,
     *     themeBrand: string,
     *     themeNeutral: string,
     *     themeRadius: string,
     *     themeDensity: string,
     *     appearanceCookieState: string
     * }
     */
    public function viewData(): array
    {
        return [
            'appearance' => $this->appearance,
            'uiTheme' => $this->style,
            'themeBrand' => $this->brand,
            'themeNeutral' => $this->neutral,
            'themeRadius' => $this->radius,
            'themeDensity' => $this->density,
            'appearanceCookieState' => $this->appearanceCookiePresent ? 'present' : 'missing',
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function allowedCookie(
        Request $request,
        string $name,
        array $allowed,
        string $default,
    ): string {
        $value = $request->cookie($name);

        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $default;
    }

    private static function brandCookie(Request $request): string
    {
        $value = $request->cookie('theme_brand');

        if (! is_string($value) || preg_match('/\A[0-9A-Fa-f]{6}\z/D', $value) !== 1) {
            return self::DEFAULT_BRAND;
        }

        return Str::upper($value);
    }
}
