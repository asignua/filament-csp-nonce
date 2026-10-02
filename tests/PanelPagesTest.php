<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\Http\Middleware\CspNonce;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;

class PanelPagesTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function pages(): array
    {
        return [
            'login (guest)' => ['/admin/login', true],
            'list' => ['/admin/users', false],
            'create' => ['/admin/users/create', false],
            'edit' => ['/admin/users/1/edit', false],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_script_and_style_tag_carries_the_nonce(string $url, bool $guest): void
    {
        if ($guest) {
            Auth::logout();
        }

        $response = $this->get($url)->assertOk();
        $html = (string) $response->getContent();

        $header = (string) $response->headers->get(CspNonce::ENFORCE);
        preg_match("/'nonce-([^']+)'/", $header, $matches);
        $nonce = $matches[1] ?? null;

        $this->assertNotNull($nonce, 'The header carries a nonce source.');

        $tags = [];
        preg_match_all('~<(script|style)\b[^>]*>~i', $html, $tags);

        $this->assertGreaterThan(5, count($tags[0]));

        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag, 'Tag without the request nonce: '.$tag);
        }
    }

    public function test_no_inline_event_handlers_or_javascript_urls_are_rendered(): void
    {
        $html = (string) $this->get('/admin/users/create')->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+="/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_the_nonce_changes_on_every_request(): void
    {
        $first = (string) $this->get('/admin/users')->headers->get(CspNonce::ENFORCE);
        $second = (string) $this->get('/admin/users')->headers->get(CspNonce::ENFORCE);

        $this->assertNotSame($first, $second);
    }

    public function test_the_header_is_sent_without_inline_script_sources(): void
    {
        $header = (string) $this->get('/admin/users')->headers->get(CspNonce::ENFORCE);

        $scriptSrc = collect(explode(';', $header))->first(fn (string $d): bool => str_starts_with(trim($d), 'script-src'));

        $this->assertStringContainsString("'strict-dynamic'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringContainsString("'unsafe-eval'", $scriptSrc, 'Documented limitation: Alpine needs it.');
    }
}
