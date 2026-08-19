<?php

namespace QrStudio;

use Endroid\QrCode\Matrix\MatrixInterface;
use Endroid\QrCode\Writer\Result\ResultInterface;

class RenderResult implements ResultInterface
{
    public function __construct(
        private readonly MatrixInterface $matrix,
        private readonly string $content,
        private readonly string $mimeType,
    ) {
    }

    public function getMatrix(): MatrixInterface
    {
        return $this->matrix;
    }

    public function getString(): string
    {
        return $this->content;
    }

    public function getDataUri(): string
    {
        return 'data:'.$this->mimeType.';base64,'.base64_encode($this->content);
    }

    public function saveToFile(string $path): void
    {
        file_put_contents($path, $this->content);
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
