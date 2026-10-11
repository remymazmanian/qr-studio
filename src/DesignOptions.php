<?php

namespace QrStudio;

use Endroid\QrCode\ErrorCorrectionLevel;
use InvalidArgumentException;

class DesignOptions
{
    public const MIN_SIZE = 64;
    public const MAX_SIZE = 4096;
    public const MAX_MARGIN = 1024;
    public const MIN_LABEL_FONT_SIZE = 8;
    public const MAX_LABEL_FONT_SIZE = 200;
    // The QR spec's quiet zone: light space around the code, in modules.
    public const QUIET_ZONE_MODULES = 4;

    public const MODULE_SQUARE = 'square';
    public const MODULE_ROUNDED = 'rounded';
    public const MODULE_DOTS = 'dots';
    public const MODULE_EXTRA_ROUNDED = 'extra_rounded';

    public const EYE_SQUARE = 'square';
    public const EYE_ROUNDED = 'rounded';
    public const EYE_CIRCLE = 'circle';

    public const FRAME_NONE = 'none';
    public const FRAME_SIMPLE_BORDER = 'simple_border';
    public const FRAME_ROUNDED_CARD = 'rounded_card';

    public function __construct(
        public readonly string $foregroundColor,
        public readonly string $backgroundColor,
        public readonly bool $transparentBackground,
        public readonly int $size,
        public readonly int $margin,
        public readonly string $errorCorrectionLevel,
        public readonly ?string $logoPath,
        public readonly int $logoSizePercentage,
        public readonly string $moduleStyle,
        public readonly string $eyeStyle,
        public readonly string $eyeColor,
        public readonly string $frameStyle,
        public readonly ?string $labelText,
        public readonly int $labelFontSize,
        public readonly string $labelColor,
        public readonly ?string $designPreset = null,
        public readonly ?string $labelFontPath = null,
    ) {
    }

    public static function defaults(): array
    {
        return [
            'design_preset' => 'generic',
            'foreground_color' => '#000000',
            'background_color' => '#ffffff',
            'transparent_background' => false,
            'qr_size' => 720,
            'margin' => 32,
            'error_correction_level' => 'high',
            'logo_path' => null,
            'logo_size_percentage' => 18,
            'module_style' => self::MODULE_SQUARE,
            'eye_style' => self::EYE_SQUARE,
            'eye_color' => '#000000',
            'frame_style' => self::FRAME_NONE,
            'label_text' => null,
            'label_font_size' => 28,
            'label_color' => '#000000',
            'label_font_path' => null,
        ];
    }

    public static function presets(): array
    {
        return [
            'generic' => [
                'label' => 'Generic',
                'description' => 'Clean square QR treatment for everyday use.',
                'values' => [
                    'foreground_color' => '#000000',
                    'background_color' => '#ffffff',
                    'transparent_background' => false,
                    'module_style' => self::MODULE_SQUARE,
                    'eye_style' => self::EYE_SQUARE,
                    'eye_color' => '#000000',
                    'frame_style' => self::FRAME_NONE,
                    'label_color' => '#000000',
                ],
            ],
            'rounded_square' => [
                'label' => 'Rounded Square',
                'description' => 'Soft rounded modules while keeping a square QR structure.',
                'values' => [
                    'foreground_color' => '#000000',
                    'background_color' => '#ffffff',
                    'transparent_background' => false,
                    'module_style' => self::MODULE_ROUNDED,
                    'eye_style' => self::EYE_ROUNDED,
                    'eye_color' => '#000000',
                    'frame_style' => self::FRAME_NONE,
                    'label_color' => '#000000',
                ],
            ],
            'circle' => [
                'label' => 'Circle',
                'description' => 'Circular module style for a softer QR look.',
                'values' => [
                    'foreground_color' => '#000000',
                    'background_color' => '#ffffff',
                    'transparent_background' => false,
                    'module_style' => self::MODULE_DOTS,
                    'eye_style' => self::EYE_CIRCLE,
                    'eye_color' => '#000000',
                    'frame_style' => self::FRAME_NONE,
                    'label_color' => '#000000',
                ],
            ],
        ];
    }

    public static function fieldNames(): array
    {
        return array_keys(self::defaults());
    }

    public static function moduleStyles(): array
    {
        return [
            self::MODULE_SQUARE => 'Square',
            self::MODULE_ROUNDED => 'Rounded',
            self::MODULE_DOTS => 'Dots / circles',
            self::MODULE_EXTRA_ROUNDED => 'Extra-rounded',
        ];
    }

    public static function eyeStyles(): array
    {
        return [
            self::EYE_SQUARE => 'Square',
            self::EYE_ROUNDED => 'Rounded',
            self::EYE_CIRCLE => 'Circle',
        ];
    }

    public static function frameStyles(): array
    {
        return [
            self::FRAME_NONE => 'None',
            self::FRAME_SIMPLE_BORDER => 'Simple border',
            self::FRAME_ROUNDED_CARD => 'Rounded card',
        ];
    }

    public static function errorCorrectionLevels(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'quartile' => 'Quartile',
            'high' => 'High',
        ];
    }

    /**
     * Builds options from an array of snake_case keys. Unknown styles, colours that
     * aren't #rgb or #rrggbb, and out-of-range sizes throw InvalidArgumentException
     * instead of silently rendering something else.
     */
    public static function fromArray(array $data): self
    {
        $data = array_replace(self::defaults(), $data);

        return new self(
            foregroundColor: self::color($data, 'foreground_color'),
            backgroundColor: self::color($data, 'background_color'),
            transparentBackground: (bool) $data['transparent_background'],
            size: self::integer($data, 'qr_size', self::MIN_SIZE, self::MAX_SIZE),
            margin: self::integer($data, 'margin', 0, self::MAX_MARGIN),
            errorCorrectionLevel: self::choice($data, 'error_correction_level', self::errorCorrectionLevels()),
            logoPath: self::localPath($data, 'logo_path'),
            logoSizePercentage: self::integer($data, 'logo_size_percentage', 0, 100),
            moduleStyle: self::choice($data, 'module_style', self::moduleStyles()),
            eyeStyle: self::choice($data, 'eye_style', self::eyeStyles()),
            eyeColor: self::color($data, 'eye_color'),
            frameStyle: self::choice($data, 'frame_style', self::frameStyles()),
            labelText: self::label($data['label_text'] ?? null),
            labelFontSize: self::integer($data, 'label_font_size', self::MIN_LABEL_FONT_SIZE, self::MAX_LABEL_FONT_SIZE),
            labelColor: self::color($data, 'label_color'),
            designPreset: self::filled($data['design_preset'] ?? null) ? (string) $data['design_preset'] : null,
            labelFontPath: self::localPath($data, 'label_font_path'),
        );
    }

    private static function color(array $data, string $key): string
    {
        return ColorContrast::normalizeHex($data[$key])
            ?? throw new InvalidArgumentException($key.' must be a hex colour such as #1f3d7a or #fff.');
    }

    private static function integer(array $data, string $key, int $min, int $max): int
    {
        $value = filter_var($data[$key], FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            throw new InvalidArgumentException($key.' must be a whole number from '.$min.' to '.$max.'.');
        }

        return $value;
    }

    private static function choice(array $data, string $key, array $allowed): string
    {
        $value = (string) $data[$key];

        if (! array_key_exists($value, $allowed)) {
            throw new InvalidArgumentException($key.' must be one of: '.implode(', ', array_keys($allowed)).'.');
        }

        return $value;
    }

    // Paths must be plain local files: stream wrappers such as phar:// or ftp:// are refused.
    private static function localPath(array $data, string $key): ?string
    {
        if (! self::filled($data[$key] ?? null)) {
            return null;
        }

        $path = (string) $data[$key];

        if (str_contains($path, '://') || str_contains($path, "\0")) {
            throw new InvalidArgumentException($key.' must be a path on the local filesystem.');
        }

        return realpath($path) ?: $path;
    }

    // Captions are one line: control characters are dropped and line breaks become spaces.
    private static function label(mixed $value): ?string
    {
        if (! self::filled($value)) {
            return null;
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';
        $text = trim(preg_replace('/\s*[\r\n]+\s*/u', ' ', $text) ?? '');

        return $text === '' ? null : $text;
    }

    public function toArray(): array
    {
        return [
            'design_preset' => $this->designPreset,
            'foreground_color' => $this->foregroundColor,
            'background_color' => $this->backgroundColor,
            'transparent_background' => $this->transparentBackground,
            'qr_size' => $this->size,
            'margin' => $this->margin,
            'error_correction_level' => $this->errorCorrectionLevel,
            'logo_path' => $this->logoPath,
            'logo_size_percentage' => $this->logoSizePercentage,
            'module_style' => $this->moduleStyle,
            'eye_style' => $this->eyeStyle,
            'eye_color' => $this->eyeColor,
            'frame_style' => $this->frameStyle,
            'label_text' => $this->labelText,
            'label_font_size' => $this->labelFontSize,
            'label_color' => $this->labelColor,
            'label_font_path' => $this->labelFontPath,
        ];
    }

    public function errorCorrectionEnum(): ErrorCorrectionLevel
    {
        return match ($this->errorCorrectionLevel) {
            'low' => ErrorCorrectionLevel::Low,
            'medium' => ErrorCorrectionLevel::Medium,
            'quartile' => ErrorCorrectionLevel::Quartile,
            default => ErrorCorrectionLevel::High,
        };
    }

    /**
     * The level actually used. A centre logo covers modules the code needs, so low
     * and medium are raised to quartile when a logo is drawn.
     */
    public function effectiveErrorCorrection(): ErrorCorrectionLevel
    {
        if ($this->hasLogo() && in_array($this->errorCorrectionLevel, ['low', 'medium'], true)) {
            return ErrorCorrectionLevel::Quartile;
        }

        return $this->errorCorrectionEnum();
    }

    /**
     * $logoPath is an absolute path on the local filesystem. The library deliberately
     * does not know about storage disks or upload directories - resolving a user
     * upload to a real path is the calling application's job, which keeps this
     * package free of any framework or filesystem convention.
     */
    public function hasLogo(): bool
    {
        return $this->logoPath !== null
            && $this->logoSizePercentage > 0
            && is_file($this->logoPath);
    }

    public function logoAbsolutePath(): ?string
    {
        return $this->hasLogo() ? $this->logoPath : null;
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * Colour for the light parts drawn on top of the code: the gap inside each
     * finder pattern and the panel behind a logo. With a transparent background
     * these stay white, since the code needs them to scan.
     */
    public function effectiveBackgroundColor(): string
    {
        return $this->transparentBackground ? '#ffffff' : $this->backgroundColor;
    }
}
