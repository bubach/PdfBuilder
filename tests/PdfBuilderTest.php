<?php

use PdfBuilder\PdfDocument;

class PdfBuilderTest extends PHPUnit_Framework_TestCase {

    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage()->addPage("L", "A3");
        $this->assertEquals('', $pdfBuilder->output("test.pdf", "F"));
    }

}