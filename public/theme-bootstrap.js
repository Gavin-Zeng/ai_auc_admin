(function () {
    var root = document.documentElement;
    var appearances = ['light', 'dark', 'system'];
    var styles = ['default', 'custom'];
    var neutrals = ['graphite', 'neutral', 'warm'];
    var radii = ['2', '4', '6'];
    var densities = ['compact', 'standard'];
    var brandPattern = /^[0-9A-F]{6}$/;

    function includes(values, value, fallback) {
        return values.indexOf(value) === -1 ? fallback : value;
    }

    function setCookie(name, value) {
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';

        document.cookie =
            name +
            '=' +
            encodeURIComponent(value) +
            '; Path=/; Max-Age=31536000; SameSite=Lax' +
            secure;
    }

    var appearance = includes(appearances, root.dataset.appearance, 'system');

    if (root.dataset.appearanceCookie === 'missing') {
        try {
            var legacyAppearance = window.localStorage.getItem('appearance');

            if (appearances.indexOf(legacyAppearance) !== -1) {
                appearance = legacyAppearance;
                setCookie('appearance', legacyAppearance);
            }

            window.localStorage.removeItem('appearance');
        } catch (_error) {
            // Storage can be unavailable in privacy-restricted browsing modes.
        }
    }

    var themeStyle = includes(styles, root.dataset.uiTheme, 'default');
    var themeNeutral = includes(
        neutrals,
        root.dataset.themeNeutral,
        'graphite',
    );
    var themeRadius = includes(radii, root.dataset.themeRadius, '4');
    var themeDensity = includes(
        densities,
        root.dataset.themeDensity,
        'standard',
    );
    var themeBrand = brandPattern.test(root.dataset.themeBrand || '')
        ? root.dataset.themeBrand
        : '0F766E';
    var isDark =
        appearance === 'dark' ||
        (appearance === 'system' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);

    root.dataset.appearance = appearance;
    root.dataset.uiTheme = themeStyle;
    root.dataset.themeBrand = themeBrand;
    root.dataset.themeNeutral = themeNeutral;
    root.dataset.themeRadius = themeRadius;
    root.dataset.themeDensity = themeDensity;
    root.classList.toggle('dark', isDark);

    if (themeStyle === 'custom') {
        var red = parseInt(themeBrand.slice(0, 2), 16);
        var green = parseInt(themeBrand.slice(2, 4), 16);
        var blue = parseInt(themeBrand.slice(4, 6), 16);

        root.style.setProperty('--app-theme-primary', '#' + themeBrand);
        root.style.setProperty(
            '--app-theme-primary-rgb',
            red + ', ' + green + ', ' + blue,
        );
    } else {
        root.style.removeProperty('--app-theme-primary');
        root.style.removeProperty('--app-theme-primary-rgb');
    }
})();
