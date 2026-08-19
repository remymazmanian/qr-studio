<?php

namespace QrStudio;

use Endroid\QrCode\ErrorCorrectionLevel;

class DesignOptions
{
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

    public static function fromArray(array $data): self
    {
        $data = array_replace(self::defaults(), $data);

        return new self(
            foregroundColor: (string) $data['foreground_color'],
            backgroundColor: (string) $data['background_color'],
            transparentBackground: (bool) $data['transparent_background'],
            size: (int) $data['qr_size'],
            margin: (int) $data['margin'],
            errorCorrectionLevel: (string) $data['error_correction_level'],
            logoPath: self::filled($data['logo_path'] ?? null) ? (string) $data['logo_path'] : null,
            logoSizePercentage: (int) $data['logo_size_percentage'],
            moduleStyle: (string) $data['module_style'],
            eyeStyle: (string) $data['eye_style'],
            eyeColor: (string) $data['eye_color'],
            frameStyle: (string) $data['frame_style'],
            labelText: self::filled($data['label_text'] ?? null) ? (string) $data['label_text'] : null,
            labelFontSize: (int) $data['label_font_size'],
            labelColor: (string) $data['label_color'],
            designPreset: self::filled($data['design_preset'] ?? null) ? (string) $data['design_preset'] : null,
        );
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

    public function effectiveBackgroundColor(): string
    {
        return $this->transparentBackground ? '#ffffff' : $this->backgroundColor;
    }
}
