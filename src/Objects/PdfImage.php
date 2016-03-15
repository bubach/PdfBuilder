<?php
namespace bubach\PdfBuilder\Objects;

use bubach\PdfBuilder\PdfDocument;

class PdfImage {

    /**
     * @var PdfDocument
     */
    private $_pdfBuilder;

    /**
     * @param PdfDocument $pdfBuilder
     */
    public function __construct(PdfDocument $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }

}