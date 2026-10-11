<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use QrStudio\DesignOptions;
use QrStudio\QrRenderer;
use RuntimeException;

class RenderAccuracyTest extends TestCase
{
    private QrRenderer $renderer;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->renderer = new QrRenderer();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qrstudio_test_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    #[RequiresPhpExtension('gd')]
    public function test_png_squares_cover_exactly_their_modules(): void
    {
        foreach ([120, 245, 720] as $size) {
            $result = $this->renderer->result('https://example.com', 'png', DesignOptions::fromArray(['qr_size' => $size]));
            $matrix = $result->getMatrix();
            $darkModules = 0;
            for ($row = 0; $row < $matrix->getBlockCount(); $row++) {
                for ($column = 0; $column < $matrix->getBlockCount(); $column++) {
                    $darkModules += $matrix->getBlockValue($row, $column);
                }
            }

            $image = imagecreatefromstring($result->getString());
            $darkPixels = 0;
            for ($y = 0; $y < imagesy($image); $y++) {
                for ($x = 0; $x < imagesx($image); $x++) {
                    $darkPixels += (imagecolorat($image, $x, $y) & 0xFF) < 128 ? 1 : 0;
                }
            }

            $this->assertSame($darkModules * (int) $matrix->getBlockSize() ** 2, $darkPixels, "dark pixels at qr_size $size");
        }
    }

    public function test_the_quiet_zone_is_at_least_four_modules(): void
    {
        $result = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray(['qr_size' => 300, 'margin' => 0]));
        $block = $result->getMatrix()->getBlockSize();
        preg_match('/<svg[^>]*width="(\d+)"/', $result->getString(), $width);

        $this->assertSame((int) $result->getMatrix()->getInnerSize() + 8 * (int) $block, (int) $width[1]);
    }

    public function test_a_logo_raises_low_error_correction_to_quartile(): void
    {
        $logo = $this->tempFile(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/iZk9HQAAAABJRU5ErkJggg=='));
        $payload = 'https://remymazmanian.com/qr-studio/';
        $withLogo = $this->renderer->result($payload, 'svg', DesignOptions::fromArray(['error_correction_level' => 'low', 'logo_path' => $logo]));
        $quartile = $this->renderer->result($payload, 'svg', DesignOptions::fromArray(['error_correction_level' => 'quartile']));
        $low = $this->renderer->result($payload, 'svg', DesignOptions::fromArray(['error_correction_level' => 'low']));

        $this->assertSame($quartile->getMatrix()->getBlockCount(), $withLogo->getMatrix()->getBlockCount());
        $this->assertLessThan($quartile->getMatrix()->getBlockCount(), $low->getMatrix()->getBlockCount());
    }

    public function test_a_logo_with_huge_declared_dimensions_is_refused_before_decoding(): void
    {
        // A valid PNG header claiming 20000 x 20000 pixels: tiny on disk, gigabytes once decoded.
        $ihdr = 'IHDR'.pack('NNCCCCC', 20000, 20000, 8, 6, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
        $options = DesignOptions::fromArray(['logo_path' => $this->tempFile($png)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logo is 20000 x 20000 pixels');

        $this->renderer->result('https://example.com', 'svg', $options);
    }

    public function test_svg_with_a_tricky_caption_is_well_formed_xml(): void
    {
        $svg = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'label_text' => "Tom & Jerry's <b>\x0B café",
        ]))->getString();

        $document = new DOMDocument();
        $this->assertTrue(@$document->loadXML($svg), 'SVG should parse as XML');
        $this->assertSame("Tom & Jerry's <b> café", $document->getElementsByTagName('text')->item(0)->textContent);
    }

    public function test_long_captions_shrink_to_fit_the_canvas(): void
    {
        $svg = $this->renderer->result('https://example.com', 'svg', DesignOptions::fromArray([
            'qr_size' => 300,
            'label_font_size' => 28,
            'label_text' => 'Scan to visit our website and see the whole QR Studio feature list',
        ]))->getString();

        preg_match('/<text[^>]*font-size="(\d+)"/', $svg, $size);
        $this->assertLessThan(28, (int) $size[1]);
    }

    public function test_png_without_gd_explains_what_is_missing(): void
    {
        if (extension_loaded('gd')) {
            $this->markTestSkipped('GD is installed; this checks the message shown without it.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PNG output needs the PHP GD extension');

        $this->renderer->result('https://example.com', 'png');
    }

    public function test_svg_works_without_gd(): void
    {
        $svg = $this->renderer->result('https://example.com', 'svg')->getString();

        $this->assertStringStartsWith('<?xml', $svg);
    }
}
