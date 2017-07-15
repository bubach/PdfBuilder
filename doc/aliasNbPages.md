# AliasNbPages

`AliasNbPages([**string** alias])`

## Description

Defines an alias for the total number of pages. It will be substituted as the document is closed.

## Parameters

<dl class="param">

<dt>`alias`</dt>

<dd>The alias. Default value: `{nb}`.</dd>

</dl>

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
        // Print current and total page numbers
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();

</div>

## See also

[PageNo](pageno.htm), [Footer](footer.htm)

* * *

<div style="text-align:center">[Index](index.htm)</div>
