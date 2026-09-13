<?php

namespace Tests\Feature\ErrorPages;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageRenderingTest extends TestCase
{
    #[DataProvider('errorPageProvider')]
    public function test_exception_pipeline_renders_the_aureon_error_page(
        int $status,
        string $title,
        string $illustration,
    ): void {
        config(['app.debug' => false]);
        Route::get("/_testing/error/{$status}", static fn () => abort($status));

        $this->get("/_testing/error/{$status}")
            ->assertStatus($status)
            ->assertSee("Error {$status}")
            ->assertSee($title)
            ->assertSee("build/img/server-status/{$illustration}", false)
            ->assertSee('build/css/aureon-errors.css', false);
    }

    /** @return array<string, array{int, string, string}> */
    public static function errorPageProvider(): array
    {
        return [
            'unauthorized' => [401, 'Authentication required', '401-unauthorized.png'],
            'forbidden' => [403, 'Access is restricted', '403-forbidden.png'],
            'not found' => [404, 'We could not find that page', '404-not-found.png'],
            'page expired' => [419, 'Your secure session expired', '419-page-expired.png'],
            'server error' => [500, 'Something went wrong', '500-server-error.png'],
            'service unavailable' => [503, 'Service is temporarily unavailable', '503-maintenance.gif'],
        ];
    }

    public function test_page_expiry_response_forces_a_fresh_form_session(): void
    {
        config(['app.debug' => false]);
        Route::get('/_testing/error/419-recovery', static fn () => abort(419));

        $this->get('/_testing/error/419-recovery')
            ->assertStatus(419)
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')
            ->assertHeader('Clear-Site-Data', '"cache"')
            ->assertSee('data-session-recovery="'.route('login').'"', false)
            ->assertSee('window.sessionStorage.clear()', false)
            ->assertSee('window.caches.keys()', false)
            ->assertSee("target.searchParams.set('_fresh'", false);
    }

    public function test_all_documented_error_artwork_is_present(): void
    {
        foreach (array_column(self::errorPageProvider(), 2) as $illustration) {
            $this->assertFileExists(resource_path("img/server-status/{$illustration}"));
        }

        $this->assertFileExists(resource_path('img/server-status/503-service-unavailable.png'));
        $this->assertFileExists(resource_path('img/server-status/README.md'));
    }
}
