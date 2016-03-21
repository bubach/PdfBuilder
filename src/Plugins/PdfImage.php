<?php
namespace bubach\PdfBuilder\Plugins;

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

    /**
     * ...
     */
    public function addImage()
    {
        //...
    }

}