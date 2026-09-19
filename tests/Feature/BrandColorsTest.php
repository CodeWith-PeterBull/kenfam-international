<?php

/**
 * Verifies that server-rendered surfaces read the configured brand colours.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * The theme controllers live in the browser, so PDF reports, mail, and the
 * default palette handed to those controllers all read `kenfam.colors`
 * instead of a literal; changing the configuration must change all of them.
 */
final class BrandColorsTest extends TestCase
{
    /** The defaults are the olive brand set. */
    public function test_brand_colours_default_to_the_olive_palette(): void
    {
        $this->assertSame('#6a753d', config('kenfam.colors.primary'));
        $this->assertSame('#70233a', config('kenfam.colors.secondary'));
        $this->assertSame('#b28a4b', config('kenfam.colors.accent'));
    }

    /** Mail action buttons, report styles, and the dashboard palette hand-off follow a configuration change. */
    public function test_documents_and_theme_defaults_follow_the_configured_primary(): void
    {
        config(['kenfam.colors.primary' => '#123456', 'kenfam.colors.primary_dark' => '#0a1c2e']);

        $mail = (string) (new MailMessage)->line('Hello')->action('Open it', 'https://example.test')->render();
        preg_match('/<a[^>]*button-primary[^>]*>/', $mail, $button);
        $this->assertStringContainsString('background-color: #123456', $button[0] ?? '');
        $this->assertStringNotContainsString('#70233a', $mail);

        $report = view('reports.partials.styles', ['orientation' => 'portrait'])->render();
        $this->assertSame(4, substr_count($report, '#123456'));
        $this->assertStringNotContainsString('#70233a', $report);

        $settings = view('layouts.partials.theme-settings')->render();
        $this->assertStringContainsString('primary: "#123456"', $settings);
        $this->assertStringContainsString('primaryDark: "#0a1c2e"', $settings);
        $this->assertStringContainsString("palette: 'olive'", $settings);

        $tokens = view('layouts.partials.brand-theme-tokens')->render();
        $this->assertStringContainsString('--brand-primary: #123456;', $tokens);
    }
}
