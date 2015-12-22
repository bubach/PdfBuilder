<?php

use bubach\PdfBuilder\PdfBuilder;

class PdfBuilderTest extends PHPUnit_Framework_TestCase {

    public function testPdfBuilderConstruct()
    {
        $pdfBuilder = new PdfBuilder();
        $this->assertEquals('', $pdfBuilder->output());
    }

}