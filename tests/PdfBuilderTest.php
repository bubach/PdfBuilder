<?php

use PdfBuilder\PdfDocument;

class PdfBuilderTest extends PHPUnit_Framework_TestCase {

    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage()->addPage("L", "A3");
        $this->assertEquals('', $pdfBuilder->output("test.pdf", "F"));
    }

    public function testFontAndCellOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->setFont('Arial', 'B', 16);
        $pdfBuilder->text(10, 5, 'Hello World!');
        $this->assertEquals('', $pdfBuilder->output("test2.pdf", "F"));
    }

}