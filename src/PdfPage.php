<?php
namespace bubach\PdfBuilder;

class PdfPage {

    /**
     * @var PdfBuilder
     */
    private $_pdfBuilder;

    /**
     * @param PdfBuilder $pdfBuilder
     */
    public function __construct(PdfBuilder $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }

}