<?php

namespace App\Contracts;

interface DocxToPdfConverterInterface
{
    public function convert(string $docxPath, string $pdfPath): void;
}
