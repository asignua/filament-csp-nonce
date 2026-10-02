<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce\Tests;

use Asignua\FilamentCspNonce\TiptapStyle;

class TiptapStyleTest extends TestCase
{
    public function test_the_bundled_css_matches_the_one_in_filament_forms(): void
    {
        $bundle = (string) file_get_contents(base_path('../../../filament/forms/dist/components/rich-editor.js'));

        $this->assertSame(1, preg_match('/`(\.ProseMirror \{\n  position: relative;.*?)`/s', $bundle, $m), 'Tiptap core CSS not found in the bundle.');

        $this->assertSame(str_replace("\\'", "'", $m[1]), TiptapStyle::css(), 'Filament changed the Tiptap CSS: refresh resources/css/tiptap-core.css.');
    }

    public function test_the_style_is_printed_in_the_panel_head_with_the_nonce(): void
    {
        $html = (string) $this->get('/admin/users/create')->getContent();

        $this->assertMatchesRegularExpression('~<style nonce="[^"]+" data-tiptap-style>\.ProseMirror~', $html);
    }

    public function test_nothing_is_printed_without_a_nonce(): void
    {
        $this->app->forgetInstance(\Illuminate\Foundation\Vite::class);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();

        $this->assertSame('', TiptapStyle::html());
    }
}
