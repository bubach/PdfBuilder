# addHeader

`addHeader(Callable $callback)`

## Description

The callback method is used to render the page header and will get a pdfDocument reference as parameter. It is automatically called by addPage() and should not be called directly by the application.

## Example

    $pdfDocument->addHeader(function($pdfDocument) {
        $pdfDocument->setFont('Arial', 'B', 15);
        $pdfDocument->addCell(80);
        $pdfDocument->addCell(30, 10, 'Title', 1, 0, 'C');
        $pdfDocument->ln(20);
    });

## See also

[addFooter](addFooter.md)