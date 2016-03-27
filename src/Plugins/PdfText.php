<?php
namespace bubach\PdfBuilder\Plugins;

use bubach\PdfBuilder\PdfDocument;

class PdfText {

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    protected $_coreFonts = array(
        'courier',
        'helvetica',
        'times',
        'symbol',
        'zapfdingbats'
    );

    /**
     * Constructor
     *
     * @param PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument) {
        $this->_pdfBuilder = $pdfDocument;

        if ($fontpath = $pdfDocument->getFontPath()) {
            if (substr($fontpath, -1) != '/' && substr($fontpath, -1) != '\\') {
                $pdfDocument->setFontPath($fontpath.'/');
            }
        } elseif (is_dir(dirname(__FILE__).'/fonts')) {
            $pdfDocument->setFontPath(dirname(__FILE__).'/fonts/');
        }
    }

}