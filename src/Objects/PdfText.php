<?php
namespace bubach\PdfBuilder\Objects;

use bubach\PdfBuilder\PdfDocument;

class PdfText {

    /**
     * @var PdfDocument
     */
    protected $_pdfBuilder;

    /**
     * @var string
     */
    protected $_fontPath = '';

    protected $_coreFonts = array(
        'courier',
        'helvetica',
        'times',
        'symbol',
        'zapfdingbats'
    );

    /**
     * @param PdfDocument $pdfBuilder
     * @param null $fontpath
     */
    public function __construct(PdfDocument $pdfBuilder, $fontpath = null) {
        $this->_pdfBuilder = $pdfBuilder;

        if ($fontpath) {
            $this->_fontPath = $fontpath;
            if (substr($this->_fontPath, -1) != '/' && substr($this->_fontPath, -1) != '\\') {
                $this->_fontPath .= '/';
            }
        } elseif (is_dir(dirname(__FILE__).'/fonts')) {
            $this->_fontPath = dirname(__FILE__).'/fonts/';
        }
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