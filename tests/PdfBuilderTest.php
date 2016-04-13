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

    /**
     * Testing simple cell output
     */
    public function testCellOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->setFont('Arial','B',16);
        $pdfBuilder->cell(40,10,'Hello World !',1);
        $pdfBuilder->cell(80,10,'Powered by PdfBuilder.',0,1,'C');
        $this->assertEquals('', $pdfBuilder->output("test5.pdf", "F"));
    }

    /**
     * Testing to output a PNG image!
     */
    public function testPngOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        //addImage($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
        $pdfBuilder->addImage("tests/burn.png");
        $this->assertEquals('', $pdfBuilder->output("test6.pdf", "F"));
    }

}