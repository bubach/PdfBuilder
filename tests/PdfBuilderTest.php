<?php

use PdfBuilder\PdfDocument;

class PdfBuilderTest extends PHPUnit_Framework_TestCase
{

    /**
     * Just basic construct and output, will auto-add a page
     */
    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfDocument();
        $this->assertEquals("", $pdfBuilder->output("tests/test1.pdf", "F"));
        $this->assertEquals(702, strlen($pdfBuilder));
    }

    /**
     * Page orientation test
     */
    public function testPdfBuilderPages()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage()->addPage("L", "A3");
        $this->assertEquals("", $pdfBuilder->output("tests/test2.pdf", "F"));
        $this->assertEquals(945, strlen($pdfBuilder));
    }

    /**
     * Simple hello world test
     */
    public function testFontAndTextOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->setFont("Arial", "B", 16);
        $pdfBuilder->addText(10, 5, "Hello World!");
        $this->assertEquals("", $pdfBuilder->output("tests/test3.pdf", "F"));
        $this->assertEquals(888, strlen($pdfBuilder));
    }

    /**
     * Simple hello world test in non-core font!
     */
    public function testCustomFontAndTextOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->addFont("BabelStone Han", "", "BabelStoneHan.ttf", true);
        $pdfBuilder->setFont("BabelStone Han", "", 16);
        $pdfBuilder->addText(10, 5, "На берегу пустынных волн - 宋体/明體");
        $this->assertEquals("", $pdfBuilder->output("tests/test4.pdf", "F"));
        $this->assertEquals(15049, strlen($pdfBuilder));
    }

    /**
     * Testing simple cell output
     */
    public function testCellOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->setFont("Arial", "B", 16);
        $pdfBuilder->addCell(40, 10, "Hello World !", 1);
        $pdfBuilder->addCell(80, 10, "Powered by PdfBuilder.", 0, 1, "C");
        $this->assertEquals("", $pdfBuilder->output("tests/test5.pdf", "F"));
        $this->assertEquals(945, strlen($pdfBuilder));
    }

    /**
     * Testing to output a PNG image!
     */
    public function testPngOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->addImage("tests/burn.png");
        $this->assertEquals("", $pdfBuilder->output("tests/test6.pdf", "F"));
        $this->assertEquals(253474, strlen($pdfBuilder));
    }

    /**
     * Testing to output a line!
     */
    public function testLineOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->line(10, 10, 50, 50);
        $this->assertEquals("", $pdfBuilder->output("tests/test7.pdf", "F"));
        $this->assertEquals(735, strlen($pdfBuilder));
    }

    /**
     * Testing to output a rectangle!
     */
    public function testRectOutput()
    {
        $pdfBuilder = new PdfDocument();
        $pdfBuilder->addPage();
        $pdfBuilder->rect(10, 10, 30, 30, 'F');
        $this->assertEquals("", $pdfBuilder->output("tests/test8.pdf", "F"));
        $this->assertEquals(728, strlen($pdfBuilder));
    }
}
