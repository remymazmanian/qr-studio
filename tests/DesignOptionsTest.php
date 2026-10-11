<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QrStudio\DesignOptions;

class DesignOptionsTest extends TestCase
{
    public static function invalidOptions(): array
    {
        return [
            'typo in module style' => [['module_style' => 'dot'], 'module_style must be one of'],
            'unknown eye style' => [['eye_style' => 'star'], 'eye_style must be one of'],
            'unknown frame' => [['frame_style' => 'polaroid'], 'frame_style must be one of'],
            'unknown error correction' => [['error_correction_level' => 'max'], 'error_correction_level must be one of'],
            'named colour' => [['foreground_color' => 'red'], 'foreground_color must be a hex colour'],
            'css function as colour' => [['background_color' => 'url(https://example.com/x)'], 'background_color must be a hex colour'],
            'huge size' => [['qr_size' => 12000], 'qr_size must be a whole number from 64 to 4096'],
            'tiny size' => [['qr_size' => 10], 'qr_size must be a whole number'],
            'negative margin' => [['margin' => -50], 'margin must be a whole number from 0'],
            'huge caption' => [['label_font_size' => 1000], 'label_font_size must be a whole number'],
            'logo over 100 percent' => [['logo_size_percentage' => 150], 'logo_size_percentage must be a whole number'],
            'stream wrapper logo' => [['logo_path' => 'phar:///tmp/x.phar/logo.png'], 'logo_path must be a path on the local filesystem'],
            'remote logo' => [['logo_path' => 'https://example.com/logo.png'], 'logo_path must be a path on the local filesystem'],
        ];
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_options_are_rejected_with_a_clear_message(array $options, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        DesignOptions::fromArray($options);
    }

    public function test_colours_are_normalized(): void
    {
        $options = DesignOptions::fromArray(['foreground_color' => '#1F3D7A', 'background_color' => '#fff']);

        $this->assertSame('#1f3d7a', $options->foregroundColor);
        $this->assertSame('#ffffff', $options->backgroundColor);
    }

    public function test_captions_drop_control_characters_and_line_breaks(): void
    {
        $options = DesignOptions::fromArray(['label_text' => "Scan\x0Bme\r\nnow\x00"]);

        $this->assertSame('Scanme now', $options->labelText);
    }

    public function test_a_caption_of_only_control_characters_counts_as_no_caption(): void
    {
        $this->assertNull(DesignOptions::fromArray(['label_text' => "\x01\x02"])->labelText);
    }
}
