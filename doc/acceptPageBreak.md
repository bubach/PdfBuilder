# acceptPageBreak

**boolean** `acceptPageBreak()`

## Description

Whenever a page break condition is met, the method is called, and the break is issued or not depending on the returned value. The default implementation returns a value according to the mode selected by setAutoPageBreak().
This method is called automatically and should not be called directly by the application.

## Example

The method is overriden in an inherited class in order to obtain a 3 column layout:

```
class PDF extends PdfBuilder
{
    var $col = 0;

    function setCol($col)
    {
        // Move position to a column
        $this->col = $col;
        $x = 10+$col*65;
        $this->SetLeftMargin($x);
        $this->SetX($x);
    }

    function acceptPageBreak()
    {
        if ($this->col < 2) {
            // Go to next column
            $this->setCol($this->col + 1);
            $this->setY(10);
            return false;
        } else {
            // Go back to first column and issue page break
            $this->setCol(0);
            return true;
        }
    }
}

$pdf = new PDF();
$pdf->addPage();
$pdf->setFont('Arial', '', 12);
for ($i = 1; $i <= 300; $i++) {
    $pdf->cell(0, 5, "Line $i", 0, 1);
}
$pdf->output();
```

## See also

[setAutoPageBreak](setAutoPageBreak.md)

* * *

[Index](README.md)
