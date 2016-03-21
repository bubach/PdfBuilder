<?php
namespace bubach\PdfBuilder\Plugins;

use bubach\PdfBuilder\PdfDocument;

class PdfShape {

    /**
     * @var PdfDocument
     */
    protected $_pdfBuilder;

    /**
     * @param PdfDocument $pdfBuilder
     */
    public function __construct(PdfDocument $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }

}