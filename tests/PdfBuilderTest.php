<?php

use bubach\PdfBuilder\PdfDocument;

class PdfBuilderTest extends PHPUnit_Framework_TestCase {

    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfDocument();
        $this->assertEquals('', $pdfBuilder->output("test.pdf", "F"));
    }

}