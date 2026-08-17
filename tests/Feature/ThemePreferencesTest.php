<?php

test('renders canonical default theme preferences without inline initialization', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSeeHtml('data-appearance="system"')
        ->assertSeeHtml('data-appearance-cookie="missing"')
        ->assertSeeHtml('data-ui-theme="default"')
        ->assertSeeHtml('data-theme-brand="0F766E"')
        ->assertSeeHtml('data-theme-neutral="graphite"')
        ->assertSeeHtml('data-theme-radius="4"')
        ->assertSeeHtml('data-theme-density="standard"')
        ->assertDontSee('const appearance', false);

    $content = $response->getContent();
    $bootstrapPosition = strpos($content, 'theme-bootstrap.js');
    $vitePosition = strpos($content, 'type="module"');

    expect($bootstrapPosition)->not->toBeFalse()
        ->and($vitePosition)->not->toBeFalse()
        ->and($bootstrapPosition)->toBeLessThan($vitePosition);
});

test('renders and normalizes a complete custom dark theme', function () {
    $response = $this->withUnencryptedCookies([
        'appearance' => 'dark',
        'ui_theme' => 'custom',
        'theme_brand' => '1d4ed8',
        'theme_neutral' => 'warm',
        'theme_radius' => '6',
        'theme_density' => 'compact',
    ])->get(route('home'));

    $response->assertOk()
        ->assertSeeHtml('data-appearance="dark"')
        ->assertSeeHtml('data-appearance-cookie="present"')
        ->assertSeeHtml('data-ui-theme="custom"')
        ->assertSeeHtml('data-theme-brand="1D4ED8"')
        ->assertSeeHtml('data-theme-neutral="warm"')
        ->assertSeeHtml('data-theme-radius="6"')
        ->assertSeeHtml('data-theme-density="compact"')
        ->assertSeeHtml('class="dark"');
});

test('accepts allowlisted theme cookie values', function (
    string $cookie,
    string $value,
    string $attribute,
) {
    $this->withUnencryptedCookie($cookie, $value)
        ->get(route('home'))
        ->assertOk()
        ->assertSeeHtml($attribute);
})->with([
    'light appearance' => ['appearance', 'light', 'data-appearance="light"'],
    'dark appearance' => ['appearance', 'dark', 'data-appearance="dark"'],
    'system appearance' => ['appearance', 'system', 'data-appearance="system"'],
    'default theme' => ['ui_theme', 'default', 'data-ui-theme="default"'],
    'custom theme' => ['ui_theme', 'custom', 'data-ui-theme="custom"'],
    'graphite neutral' => ['theme_neutral', 'graphite', 'data-theme-neutral="graphite"'],
    'neutral neutral' => ['theme_neutral', 'neutral', 'data-theme-neutral="neutral"'],
    'warm neutral' => ['theme_neutral', 'warm', 'data-theme-neutral="warm"'],
    'two pixel radius' => ['theme_radius', '2', 'data-theme-radius="2"'],
    'four pixel radius' => ['theme_radius', '4', 'data-theme-radius="4"'],
    'six pixel radius' => ['theme_radius', '6', 'data-theme-radius="6"'],
    'compact density' => ['theme_density', 'compact', 'data-theme-density="compact"'],
    'standard density' => ['theme_density', 'standard', 'data-theme-density="standard"'],
    'uppercase brand' => ['theme_brand', '9F1239', 'data-theme-brand="9F1239"'],
    'lowercase brand' => ['theme_brand', '0f766e', 'data-theme-brand="0F766E"'],
]);

test('rejects invalid and malicious theme cookie values', function (
    string $cookie,
    string $payload,
    string $fallbackAttribute,
) {
    $response = $this->withUnencryptedCookie($cookie, $payload)->get(route('home'));

    $response->assertOk()->assertSeeHtml($fallbackAttribute);

    if (strlen($payload) >= 12) {
        expect($response->getContent())->not->toContain($payload)
            ->not->toContain(e($payload));
    }
})->with([
    'appearance script injection' => [
        'appearance',
        '"><script>alert(1)</script>',
        'data-appearance="system"',
    ],
    'theme attribute injection' => [
        'ui_theme',
        'custom" onmouseover="alert(1)',
        'data-ui-theme="default"',
    ],
    'brand css injection' => [
        'theme_brand',
        '0F766E;background:url(javascript:alert(1))',
        'data-theme-brand="0F766E"',
    ],
    'brand with hash' => [
        'theme_brand',
        '#0F766E',
        'data-theme-brand="0F766E"',
    ],
    'neutral whitespace' => [
        'theme_neutral',
        ' warm ',
        'data-theme-neutral="graphite"',
    ],
    'radius unknown' => [
        'theme_radius',
        '8',
        'data-theme-radius="4"',
    ],
    'density control character' => [
        'theme_density',
        "compact\ncustom",
        'data-theme-density="standard"',
    ],
    'overlong theme' => [
        'ui_theme',
        str_repeat('a', 4096),
        'data-ui-theme="default"',
    ],
]);
