<?php

namespace App\Data;

final readonly class RenderedPdf
{
    public function __construct(
        public string $contents,
        public string $filename,
        public int $pageCount,
    ) {}
}
