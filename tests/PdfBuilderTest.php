<?php

use PdfBuilder\PdfDocument;

class PdfBuilderTest extends PHPUnit_Framework_TestCase {

    /**
     * Just basic construct and output, will auto-add a page
     */
    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfDocument();
        $this->assertEquals('', $pdfBuilder->output("test1.pdf", "F"));
    }

    /**
     * Page orientation test
     */
    public function testPdfBuilderPages()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage()->addPage("L", "A3");
        $this->assertEquals('', $pdfBuilder->output("test2.pdf", "F"));
    }

    /**
     * Simple hello world test
     */
    public function testFontAndTextOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->setFont('Arial', 'B', 16);
        $pdfBuilder->text(10, 5, 'Hello World!');
        $this->assertEquals('', $pdfBuilder->output("test3.pdf", "F"));
    }

    /**
     * Simple hello world test in non-core font!
     */
    public function testCustomFontAndTextOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->addFont('BabelStone Han','','BabelStoneHan.ttf', true);
        $pdfBuilder->setFont('BabelStone Han', '', 16);
        $pdfBuilder->text(10, 5, 'На берегу пустынных волн - 宋体/明體');
        $this->assertEquals('', $pdfBuilder->output("test4.pdf", "F"));
    }

}