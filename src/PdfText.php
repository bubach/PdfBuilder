<?php
namespace bubach\PdfBuilder;

class PdfText {

    /**
     * @var PdfBuilder
     */
    private $_pdfBuilder;

    /**
     * @var string
     */
    private $_fontPath = '';

    /**
     * @param PdfBuilder $pdfBuilder
     */
    public function __construct(PdfBuilder $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }

    /**
     * @return string
     */
    public function getFontPath() {
        return $this->_fontPath;
    }

    /**
     * @param $fontPath
     */
    public function setFontPath($fontPath) {
        $this->_fontPath = $fontPath;
    }

}