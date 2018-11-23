<?php
include './vendor/autoload.php';

$doc = new PdfBuilder\Document();
$p1 = new PdfBuilder\Page();

$doc->addPage($p1);
$doc->addPage();

$p1->setFont('Helvetica-Bold', 92);
$p1->addText(20, 100, 'Hello World!');

$p1->setFont('Times-Roman', 18);
$p1->addText(20, 120, 'This is my first PDF with working text-output.');

$doc->getPage(2)
    ->setFont('Courier-Bold', 36)
    ->addText(20, 80, "And here is some more")
    ->addText(20, 110, "text on page 2.");

$doc->output('f.pdf', 'F');
copy('f.pdf', 'f.pdf.txt');