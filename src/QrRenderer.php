<?php

namespace QrStudio;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Matrix\MatrixInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\Result\ResultInterface;
use InvalidArgumentException;

class QrRenderer
{
    public function result(string $payload, string $format, int|DesignOptions|null $options = null): ResultInterface
    {
        $options = $this->normalizeOptions($options);
        $matrix = $this->matrix($payload, $options);

        return match ($format) {
            'svg' => new RenderResult($matrix, $this->svg($matrix, $options), 'image/svg+xml'),
            'png' => new RenderResult($matrix, $this->png($matrix, $options), 'image/png'),
            default => throw new InvalidArgumentException('Unsupported QR format.'),
        };
    }

    public function dataUri(string $payload, string $format = 'svg', int|DesignOptions|null $options = null): string
    {
        return $this->result($payload, $format, $options)->getDataUri();
    }

    private function normalizeOptions(int|DesignOptions|null $options): DesignOptions
    {
        if ($options instanceof DesignOptions) {
            return $options;
        }

        $values = DesignOptions::defaults();

        if (is_int($options)) {
            $values['qr_size'] = $options;
        }

        return DesignOptions::fromArray($values);
    }

    private function matrix(string $payload, DesignOptions $options): MatrixInterface
    {
        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $options->errorCorrectionEnum(),
            size: $options->size,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Shrink,
        );

        return (new MatrixFactory)->create($qrCode);
    }

    private function svg(MatrixInterface $matrix, DesignOptions $options): string
    {
        $geometry = $this->geometry($matrix, $options);
        $parts = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<svg xmlns="http://www.w3.org/2000/svg" width="'.$geometry['width'].'" height="'.$geometry['height'].'" viewBox="0 0 '.$geometry['width'].' '.$geometry['height'].'" role="img" aria-label="QR code">',
        ];

        $parts[] = $this->svgBackground($geometry, $options);
        $parts[] = $this->svgModules($matrix, $geometry, $options);
        $parts[] = $this->svgEyes($matrix, $geometry, $options);
        $parts[] = $this->svgLogo($geometry, $options);
        $parts[] = $this->svgLabel($geometry, $options);
        $parts[] = '</svg>';

        return implode('', array_filter($parts));
    }

    private function svgBackground(array $geometry, DesignOptions $options): string
    {
        $background = $this->escape($options->backgroundColor);
        $border = '#d7e3ea';

        if ($options->frameStyle === DesignOptions::FRAME_ROUNDED_CARD) {
            $fill = $options->transparentBackground ? 'none' : $background;

            return '<rect x="1" y="1" width="'.($geometry['width'] - 2).'" height="'.($geometry['height'] - 2).'" rx="28" fill="'.$fill.'" stroke="'.$border.'" stroke-width="2"/>';
        }

        $svg = '';

        if (! $options->transparentBackground) {
            $svg .= '<rect width="100%" height="100%" fill="'.$background.'"/>';
        }

        if ($options->frameStyle === DesignOptions::FRAME_SIMPLE_BORDER) {
            $svg .= '<rect x="1" y="1" width="'.($geometry['width'] - 2).'" height="'.($geometry['height'] - 2).'" fill="none" stroke="'.$border.'" stroke-width="2"/>';
        }

        return $svg;
    }

    private function svgModules(MatrixInterface $matrix, array $geometry, DesignOptions $options): string
    {
        $svg = '<g fill="'.$this->escape($options->foregroundColor).'">';
        $count = $matrix->getBlockCount();

        for ($row = 0; $row < $count; $row++) {
            for ($column = 0; $column < $count; $column++) {
                if ($matrix->getBlockValue($row, $column) !== 1 || $this->isFinderCell($row, $column, $count)) {
                    continue;
                }

                $x = $geometry['qrX'] + $column * $geometry['block'];
                $y = $geometry['qrY'] + $row * $geometry['block'];
                $svg .= $this->svgModuleShape($x, $y, $geometry['block'], $options->moduleStyle);
            }
        }

        return $svg.'</g>';
    }

    private function svgModuleShape(float $x, float $y, float $block, string $style): string
    {
        return match ($style) {
            DesignOptions::MODULE_DOTS => '<circle cx="'.$this->format($x + $block / 2).'" cy="'.$this->format($y + $block / 2).'" r="'.$this->format($block * 0.46).'"/>',
            DesignOptions::MODULE_ROUNDED => '<rect x="'.$this->format($x).'" y="'.$this->format($y).'" width="'.$this->format($block).'" height="'.$this->format($block).'" rx="'.$this->format($block * 0.25).'"/>',
            DesignOptions::MODULE_EXTRA_ROUNDED => '<rect x="'.$this->format($x).'" y="'.$this->format($y).'" width="'.$this->format($block).'" height="'.$this->format($block).'" rx="'.$this->format($block * 0.45).'"/>',
            default => '<rect x="'.$this->format($x).'" y="'.$this->format($y).'" width="'.$this->format($block).'" height="'.$this->format($block).'"/>',
        };
    }

    private function svgEyes(MatrixInterface $matrix, array $geometry, DesignOptions $options): string
    {
        $count = $matrix->getBlockCount();
        $positions = [[0, 0], [$count - 7, 0], [0, $count - 7]];
        $svg = '';

        foreach ($positions as [$column, $row]) {
            $x = $geometry['qrX'] + $column * $geometry['block'];
            $y = $geometry['qrY'] + $row * $geometry['block'];
            $svg .= $this->svgEye($x, $y, $geometry['block'], $options);
        }

        return $svg;
    }

    private function svgEye(float $x, float $y, float $block, DesignOptions $options): string
    {
        $color = $this->escape($options->eyeColor);
        $background = $this->escape($options->effectiveBackgroundColor());
        $outer = $block * 7;
        $middle = $block * 5;
        $inner = $block * 3;

        if ($options->eyeStyle === DesignOptions::EYE_CIRCLE) {
            $cx = $x + $outer / 2;
            $cy = $y + $outer / 2;

            return '<g>'
                .'<circle cx="'.$this->format($cx).'" cy="'.$this->format($cy).'" r="'.$this->format($outer / 2).'" fill="'.$color.'"/>'
                .'<circle cx="'.$this->format($cx).'" cy="'.$this->format($cy).'" r="'.$this->format($middle / 2).'" fill="'.$background.'"/>'
                .'<circle cx="'.$this->format($cx).'" cy="'.$this->format($cy).'" r="'.$this->format($inner / 2).'" fill="'.$color.'"/>'
                .'</g>';
        }

        $radius = $options->eyeStyle === DesignOptions::EYE_ROUNDED ? $block * 1.3 : 0;
        $smallRadius = $options->eyeStyle === DesignOptions::EYE_ROUNDED ? $block * 0.8 : 0;

        return '<g>'
            .'<rect x="'.$this->format($x).'" y="'.$this->format($y).'" width="'.$this->format($outer).'" height="'.$this->format($outer).'" rx="'.$this->format($radius).'" fill="'.$color.'"/>'
            .'<rect x="'.$this->format($x + $block).'" y="'.$this->format($y + $block).'" width="'.$this->format($middle).'" height="'.$this->format($middle).'" rx="'.$this->format($smallRadius).'" fill="'.$background.'"/>'
            .'<rect x="'.$this->format($x + $block * 2).'" y="'.$this->format($y + $block * 2).'" width="'.$this->format($inner).'" height="'.$this->format($inner).'" rx="'.$this->format($smallRadius).'" fill="'.$color.'"/>'
            .'</g>';
    }

    private function svgLogo(array $geometry, DesignOptions $options): string
    {
        $path = $options->logoAbsolutePath();

        if (! $path) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $data = file_get_contents($path);

        if ($data === false) {
            return '';
        }

        [$x, $y, $size, $pad] = $this->logoBox($geometry, $options);

        return '<g>'
            .'<rect x="'.$this->format($x - $pad).'" y="'.$this->format($y - $pad).'" width="'.$this->format($size + $pad * 2).'" height="'.$this->format($size + $pad * 2).'" rx="'.$this->format(max(8, $pad)).'" fill="'.$this->escape($options->effectiveBackgroundColor()).'"/>'
            .'<image href="data:'.$this->escape($mime).';base64,'.base64_encode($data).'" x="'.$this->format($x).'" y="'.$this->format($y).'" width="'.$this->format($size).'" height="'.$this->format($size).'" preserveAspectRatio="xMidYMid meet"/>'
            .'</g>';
    }

    private function svgLabel(array $geometry, DesignOptions $options): string
    {
        if (! $options->labelText) {
            return '';
        }

        return '<text x="'.$this->format($geometry['width'] / 2).'" y="'.$this->format($geometry['labelBaseline']).'" text-anchor="middle" font-family="Instrument Sans, Arial, sans-serif" font-size="'.$options->labelFontSize.'" font-weight="700" fill="'.$this->escape($options->labelColor).'">'.$this->escape($options->labelText).'</text>';
    }

    private function png(MatrixInterface $matrix, DesignOptions $options): string
    {
        $geometry = $this->geometry($matrix, $options);
        $image = imagecreatetruecolor($geometry['width'], $geometry['height']);

        if (! $image) {
            throw new InvalidArgumentException('Unable to create QR image.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        if (function_exists('imageantialias')) {
            imageantialias($image, true);
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $geometry['width'], $geometry['height'], $transparent);
        imagealphablending($image, true);

        $this->drawPngBackground($image, $geometry, $options);
        $this->drawPngModules($image, $matrix, $geometry, $options);
        $this->drawPngEyes($image, $matrix, $geometry, $options);
        $this->drawPngLogo($image, $geometry, $options);
        $this->drawPngLabel($image, $geometry, $options);

        ob_start();
        imagepng($image);
        $content = (string) ob_get_clean();
        imagedestroy($image);

        return $content;
    }

    private function drawPngBackground($image, array $geometry, DesignOptions $options): void
    {
        $background = $this->allocate($image, $options->backgroundColor);
        $border = $this->allocate($image, '#d7e3ea');

        if ($options->frameStyle === DesignOptions::FRAME_ROUNDED_CARD) {
            $fill = $options->transparentBackground ? null : $background;
            $this->roundedRectangle($image, 1, 1, $geometry['width'] - 2, $geometry['height'] - 2, 28, $fill, $border);

            return;
        }

        if (! $options->transparentBackground) {
            imagefilledrectangle($image, 0, 0, $geometry['width'], $geometry['height'], $background);
        }

        if ($options->frameStyle === DesignOptions::FRAME_SIMPLE_BORDER) {
            imagesetthickness($image, 2);
            imagerectangle($image, 1, 1, $geometry['width'] - 2, $geometry['height'] - 2, $border);
            imagesetthickness($image, 1);
        }
    }

    private function drawPngModules($image, MatrixInterface $matrix, array $geometry, DesignOptions $options): void
    {
        $color = $this->allocate($image, $options->foregroundColor);
        $count = $matrix->getBlockCount();

        for ($row = 0; $row < $count; $row++) {
            for ($column = 0; $column < $count; $column++) {
                if ($matrix->getBlockValue($row, $column) !== 1 || $this->isFinderCell($row, $column, $count)) {
                    continue;
                }

                $x = $geometry['qrX'] + $column * $geometry['block'];
                $y = $geometry['qrY'] + $row * $geometry['block'];
                $this->drawPngModule($image, $x, $y, $geometry['block'], $options->moduleStyle, $color);
            }
        }
    }

    private function drawPngModule($image, float $x, float $y, float $block, string $style, int $color): void
    {
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $block);
        $y2 = (int) round($y + $block);

        if ($style === DesignOptions::MODULE_DOTS) {
            imagefilledellipse($image, (int) round($x + $block / 2), (int) round($y + $block / 2), (int) round($block * 0.92), (int) round($block * 0.92), $color);

            return;
        }

        if ($style === DesignOptions::MODULE_ROUNDED) {
            $this->roundedRectangle($image, $x1, $y1, $x2, $y2, (int) round($block * 0.25), $color);

            return;
        }

        if ($style === DesignOptions::MODULE_EXTRA_ROUNDED) {
            $this->roundedRectangle($image, $x1, $y1, $x2, $y2, (int) round($block * 0.45), $color);

            return;
        }

        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);
    }

    private function drawPngEyes($image, MatrixInterface $matrix, array $geometry, DesignOptions $options): void
    {
        $count = $matrix->getBlockCount();
        $positions = [[0, 0], [$count - 7, 0], [0, $count - 7]];

        foreach ($positions as [$column, $row]) {
            $x = $geometry['qrX'] + $column * $geometry['block'];
            $y = $geometry['qrY'] + $row * $geometry['block'];
            $this->drawPngEye($image, $x, $y, $geometry['block'], $options);
        }
    }

    private function drawPngEye($image, float $x, float $y, float $block, DesignOptions $options): void
    {
        $color = $this->allocate($image, $options->eyeColor);
        $background = $this->allocate($image, $options->effectiveBackgroundColor());
        $outer = $block * 7;
        $middle = $block * 5;
        $inner = $block * 3;

        if ($options->eyeStyle === DesignOptions::EYE_CIRCLE) {
            $cx = (int) round($x + $outer / 2);
            $cy = (int) round($y + $outer / 2);
            imagefilledellipse($image, $cx, $cy, (int) round($outer), (int) round($outer), $color);
            imagefilledellipse($image, $cx, $cy, (int) round($middle), (int) round($middle), $background);
            imagefilledellipse($image, $cx, $cy, (int) round($inner), (int) round($inner), $color);

            return;
        }

        $radius = $options->eyeStyle === DesignOptions::EYE_ROUNDED ? (int) round($block * 1.3) : 0;
        $smallRadius = $options->eyeStyle === DesignOptions::EYE_ROUNDED ? (int) round($block * 0.8) : 0;

        $this->roundedRectangle($image, (int) round($x), (int) round($y), (int) round($x + $outer), (int) round($y + $outer), $radius, $color);
        $this->roundedRectangle($image, (int) round($x + $block), (int) round($y + $block), (int) round($x + $block + $middle), (int) round($y + $block + $middle), $smallRadius, $background);
        $this->roundedRectangle($image, (int) round($x + $block * 2), (int) round($y + $block * 2), (int) round($x + $block * 2 + $inner), (int) round($y + $block * 2 + $inner), $smallRadius, $color);
    }

    private function drawPngLogo($image, array $geometry, DesignOptions $options): void
    {
        $path = $options->logoAbsolutePath();

        if (! $path) {
            return;
        }

        $logo = $this->logoImage($path);

        if (! $logo) {
            return;
        }

        [$x, $y, $size, $pad] = $this->logoBox($geometry, $options);
        $background = $this->allocate($image, $options->effectiveBackgroundColor());
        $this->roundedRectangle($image, (int) round($x - $pad), (int) round($y - $pad), (int) round($x + $size + $pad), (int) round($y + $size + $pad), (int) round(max(8, $pad)), $background);

        $width = imagesx($logo);
        $height = imagesy($logo);
        $scale = min($size / $width, $size / $height);
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);
        $targetX = (int) round($x + ($size - $targetWidth) / 2);
        $targetY = (int) round($y + ($size - $targetHeight) / 2);

        imagecopyresampled($image, $logo, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($logo);
    }

    private function drawPngLabel($image, array $geometry, DesignOptions $options): void
    {
        if (! $options->labelText) {
            return;
        }

        $color = $this->allocate($image, $options->labelColor);
        $font = base_path('vendor/endroid/qr-code/assets/open_sans.ttf');

        if (function_exists('imagettftext') && is_file($font)) {
            $box = imagettfbbox($options->labelFontSize, 0, $font, $options->labelText);
            $textWidth = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            $x = (int) round(($geometry['width'] - $textWidth) / 2);
            imagettftext($image, $options->labelFontSize, 0, $x, (int) round($geometry['labelBaseline']), $color, $font, $options->labelText);

            return;
        }

        $fontSize = 5;
        $x = (int) round(($geometry['width'] - imagefontwidth($fontSize) * strlen($options->labelText)) / 2);
        imagestring($image, $fontSize, $x, (int) round($geometry['labelBaseline'] - 14), $options->labelText, $color);
    }

    private function geometry(MatrixInterface $matrix, DesignOptions $options): array
    {
        $matrixSize = (int) $matrix->getInnerSize();
        $framePadding = match ($options->frameStyle) {
            DesignOptions::FRAME_ROUNDED_CARD => 28,
            DesignOptions::FRAME_SIMPLE_BORDER => 18,
            default => 0,
        };
        $labelSpace = $options->labelText ? $options->labelFontSize + 28 : 0;
        $width = $matrixSize + $options->margin * 2 + $framePadding * 2;
        $height = $width + $labelSpace;
        $qrX = $framePadding + $options->margin;
        $qrY = $framePadding + $options->margin;

        return [
            'width' => $width,
            'height' => $height,
            'matrixSize' => $matrixSize,
            'block' => $matrix->getBlockSize(),
            'framePadding' => $framePadding,
            'qrX' => $qrX,
            'qrY' => $qrY,
            'labelBaseline' => $qrY + $matrixSize + $options->margin + $options->labelFontSize,
        ];
    }

    private function logoBox(array $geometry, DesignOptions $options): array
    {
        $size = $geometry['matrixSize'] * ($options->logoSizePercentage / 100);
        $size = max($geometry['block'] * 4, min($size, $geometry['matrixSize'] * 0.25));
        $x = $geometry['qrX'] + ($geometry['matrixSize'] - $size) / 2;
        $y = $geometry['qrY'] + ($geometry['matrixSize'] - $size) / 2;
        $pad = max(6, $geometry['block']);

        return [$x, $y, $size, $pad];
    }

    private function isFinderCell(int $row, int $column, int $count): bool
    {
        return ($row < 7 && $column < 7)
            || ($row < 7 && $column >= $count - 7)
            || ($row >= $count - 7 && $column < 7);
    }

    private function roundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, ?int $fill = null, ?int $stroke = null): void
    {
        $radius = max(0, min($radius, (int) floor(($x2 - $x1) / 2), (int) floor(($y2 - $y1) / 2)));

        if ($fill !== null) {
            if ($radius === 0) {
                imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fill);
            } else {
                imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fill);
                imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fill);
                imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fill);
                imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fill);
                imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fill);
                imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fill);
            }
        }

        if ($stroke !== null) {
            if ($radius === 0) {
                imagerectangle($image, $x1, $y1, $x2, $y2, $stroke);

                return;
            }

            imagesetthickness($image, 2);
            imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $stroke);
            imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $stroke);
            imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $stroke);
            imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $stroke);
            imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $stroke);
            imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $stroke);
            imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $stroke);
            imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $stroke);
            imagesetthickness($image, 1);
        }
    }

    private function logoImage(string $path)
    {
        $mime = mime_content_type($path);

        return match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function allocate($image, string $hex): int
    {
        [$r, $g, $b] = ColorContrast::rgb($hex);

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
