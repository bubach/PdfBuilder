<?php
$startMem = (memory_get_peak_usage() / 1024);

include './vendor/autoload.php';

$doc = new PdfBuilder\Document();
$p1 = new PdfBuilder\Page();

$doc->addPage($p1);

$doc->addPage();

$p1->setFont('FreeSerif', 48);
$p1->addText(20, 100, 'Hello World! ÅÄÖ!!');

$p1->setFont('FreeSerif', 14);
$p1->addText(20, 120, 'This is my first PDF with support for latin UTF8 characters.');

$p1->addText(20, 160, 'This is a subsetted TrueType font, with åäöÅÄÖ€$£Ü.');
echo round((memory_get_peak_usage() / 1024) - $startMem, 2) . "\n\n";

$doc->getPage(2)
    ->setFont('FreeSerif', 36)
    ->addText(20, 80, "And here is some more")
    ->addText(20, 110, "text on page 2.");

for ($i = 0; $i < 198; $i++) {
    $page = new PdfBuilder\Page();

    if ($i % 2) {
        $page->setFont('Helvetica', 14);
        $page->addText(20, 120, 'This is my first PDF with support for latin UTF8 characters.');

        $page->setFont('FreeSerif', 14);
        $page->addText(20, 160, 'This is a subsetted TrueType font, with åäöÅÄÖ€$£Ü.');
    } else {
        $page->setFont('Courier-Bold', 36)
            ->addText(20, 80, "And here is some more")
            ->addText(20, 110, sprintf("text on page %s.", $i + 3));
    }

    $doc->addPage($page);

    if ($i == 100) {
        echo round((memory_get_peak_usage() / 1024) - $startMem, 2) . "\n\n";
    }
}

$doc->output(fopen('f.pdf', 'w+'));
copy('f.pdf', 'f.pdf.txt');

echo round((memory_get_peak_usage() / 1024) - $startMem, 2) . "\n\n";