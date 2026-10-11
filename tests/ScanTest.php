<?php

declare(strict_types=1);

namespace QrStudio\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use QrStudio\DesignOptions;
use QrStudio\PayloadFactory;
use QrStudio\QrRenderer;
use Zxing\QrReader;

/**
 * Decodes rendered PNGs with an independent QR reader, so a code that looks right
 * but doesn't scan fails the build.
 */
#[RequiresPhpExtension('gd')]
class ScanTest extends TestCase
{
    private const PAYLOAD = 'https://remymazmanian.com/qr-studio/';

    private static ?string $logo = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$logo !== null) {
            @unlink(self::$logo);
        }
    }

    private static function logo(): string
    {
        if (self::$logo === null) {
            $image = imagecreatetruecolor(400, 400);
            imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 60));
            imagefilledellipse($image, 200, 200, 260, 260, imagecolorallocate($image, 255, 255, 255));
            self::$logo = tempnam(sys_get_temp_dir(), 'qrstudio_logo_');
            imagepng($image, self::$logo);
        }

        return self::$logo;
    }

    public static function variants(): iterable
    {
        foreach ([120, 245, 720] as $size) {
            foreach (['square' => 'square', 'rounded' => 'rounded', 'dots' => 'circle', 'extra_rounded' => 'rounded'] as $module => $eye) {
                foreach (['low', 'high'] as $level) {
                    yield "$size px $module $level" => [['qr_size' => $size, 'module_style' => $module, 'eye_style' => $eye, 'error_correction_level' => $level]];
                }
            }
        }
    }

    #[DataProvider('variants')]
    public function test_every_style_scans(array $options): void
    {
        $this->assertScans(self::PAYLOAD, DesignOptions::fromArray($options));
    }

    public static function logoSizes(): array
    {
        return ['18 percent, low' => [18, 'low'], '25 percent, low' => [25, 'low'], '25 percent, high' => [25, 'high']];
    }

    #[DataProvider('logoSizes')]
    public function test_codes_with_a_logo_scan(int $percentage, string $level): void
    {
        $this->assertScans(self::PAYLOAD, DesignOptions::fromArray([
            'qr_size' => 400,
            'error_correction_level' => $level,
            'logo_path' => self::logo(),
            'logo_size_percentage' => $percentage,
        ]));
    }

    public function test_framed_and_captioned_codes_scan(): void
    {
        foreach (DesignOptions::frameStyles() as $frame => $label) {
            $this->assertScans(self::PAYLOAD, DesignOptions::fromArray([
                'qr_size' => 300,
                'frame_style' => $frame,
                'label_text' => 'Scan to visit our website and see everything QR Studio can do',
            ]));
        }
    }

    public function test_payload_types_round_trip(): void
    {
        $factory = new PayloadFactory();
        $payloads = [
            $factory->make(['type' => 'wifi', 'wifi_ssid' => 'Home; Office', 'wifi_password' => 'p:a,ss"w\\d', 'wifi_encryption' => 'WPA2']),
            $factory->make(['type' => 'vcard', 'vcard_name' => 'Ada Lovelace', 'vcard_url' => 'https://example.com/a,b']),
            $factory->make(['type' => 'email', 'email_address' => 'hello@example.com', 'email_subject' => 'Hi there']),
        ];

        foreach ($payloads as $payload) {
            $this->assertScans($payload, DesignOptions::fromArray(['qr_size' => 400]));
        }
    }

    private function assertScans(string $expected, DesignOptions $options): void
    {
        $png = (new QrRenderer())->result($expected, 'png', $options)->getString();

        $this->assertSame($expected, (new QrReader($png, QrReader::SOURCE_TYPE_BLOB))->text());
    }
}
