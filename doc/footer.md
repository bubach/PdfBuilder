# Footer

`Footer()`

## Description

This method is used to render the page footer. It is automatically called by AddPage() and Close() and should not be called directly by the application. The implementation in FPDF is empty, so you have to subclass it and override the method if you want a specific processing.

## Example

<div class="doc-source">

    class PDF extends FPDF
    {
    function Footer()
    {
        // Go to 1.5 cm from bottom
        $this->SetY(-15);
        // Select Arial italic 8
        $this->SetFont('Arial','I',8);
        // Print centered page number
        $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
    }
    }

</div>

## See also

[Header](header.htm)

* * *

<div style="text-align:center">[Index](index.htm)</div>
