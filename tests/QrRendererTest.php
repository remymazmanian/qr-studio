<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QrStudio\DesignOptions;
use QrStudio\QrRenderer;

class QrRendererTest extends TestCase
{
    private QrRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new QrRenderer();
    }

    public function test_it_renders_svg(): void
    {
        $result = $this->renderer->result('https://example.com', 'svg');

        $this->assertSame('image/svg+xml', $result->getMimeType());
        $this->assertStringStartsWith('<?xml', $result->getString());
        $this->assertStringContainsString('<svg', $result->getString());
    }

    public function test_it_renders_png(): void
    {
        $result = $this->renderer->result('https://example.com', 'png');

        $this->assertSame('image/png', $result->getMimeType());
        $this->assertSame("\x89PNG", substr($result->getString(), 0, 4));
    }

    public function test_it_rejects_an_unsupported_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->renderer->result('https://example.com', 'gif');
    }

    public function test_it_returns_a_data_uri(): void
    {
        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $this->renderer->dataUri('https://example.com')
        );
    }

    /**
     * Different payloads must produce different matrices - a renderer that quietly
     * ignored its input would still emit valid-looking SVG.
     */
    public function test_different_payloads_produce_different_output(): void
    {
        $a = $this->renderer->result('https://example.com/a', 'svg')->getString();
        $b = $this->renderer->result('https://example.com/b', 'svg')->getString();

        $this->assertNotSame($a, $b);
    }

    #[DataProvider('moduleStyles')]
    public function test_it_renders_every_module_style(string $style): void
    {
        $options = DesignOptions::fromArray(['module_style' => $style]);
        $svg = $this->renderer->result('https://example.com', 'svg', $options)->getString();

        $this->assertStringContainsString('<svg', $svg);
    }

    public static function moduleStyles(): array
    {
        return array_map(fn (string $key): array => [$key], array_keys(DesignOptions::moduleStyles()));
    }

    #[DataProvider('eyeStyles')]
    public function test_it_renders_every_eye_style(string $style): void
    {
        $options = DesignOptions::fromArray(['eye_style' => $style]);
        $svg = $this->renderer->result('https://example.com', 'svg', $options)->getString();

        $this->assertStringContainsString('<svg', $svg);
    }

    public static function eyeStyles(): array
    {
        return array_map(fn (string $key): array => [$key], array_keys(DesignOptions::eyeStyles()));
    }

    #[DataProvider('frameStyles')]
    public function test_it_renders_every_frame_style(string $style): void
    {
        $options = DesignOptions::fromArray(['frame_style' => $style]);
        $svg = $this->renderer->result('https://example.com', 'svg', $options)->getString();

        $this->assertStringContainsString('<svg', $svg);
    }

    public static function frameStyles(): array
    {
        return array_map(fn (string $key): array => [$key], array_keys(DesignOptions::frameStyles()));
    }

    public function test_transparent_background_omits_the_background_rect(): void
    {
        $opaque = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'background_color' => '#ff0000',
        ]))->getString();

        $transparent = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'background_color' => '#ff0000',
            'transparent_background' => true,
        ]))->getString();

        $this->assertStringContainsString('#ff0000', $opaque);
        $this->assertStringNotContainsString('#ff0000', $transparent);
    }

    public function test_a_caption_is_rendered_and_escaped(): void
    {
        $svg = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'label_text' => 'Scan & go <here>',
        ]))->getString();

        $this->assertStringContainsString('Scan &amp; go &lt;here&gt;', $svg);
        $this->assertStringNotContainsString('<here>', $svg);
    }

    public function test_size_and_margin_affect_the_canvas(): void
    {
        $small = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'qr_size' => 320, 'margin' => 8,
        ]))->getString();

        $large = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'qr_size' => 1024, 'margin' => 64,
        ]))->getString();

        preg_match('/width="(\d+)"/', $small, $smallMatch);
        preg_match('/width="(\d+)"/', $large, $largeMatch);

        $this->assertGreaterThan((int) $smallMatch[1], (int) $largeMatch[1]);
    }
}
