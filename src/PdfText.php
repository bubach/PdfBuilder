<?php
namespace bubach\PdfBuilder;

class PdfText {

    private $_fontPath = '';

    public function getFontPath() {
        return $this->_fontPath;
    }

    public function setFontPath($fontPath) {
        $this->_fontPath = $fontPath;
    }

}