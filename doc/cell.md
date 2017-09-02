# addCell

`addCell(float $w [,float $h [,string $txt [,mixed $border [,int $ln [,string $align [,boolean $fill [,mixed $link]]]]]]])`

## Description

Prints a cell (rectangular area) with optional borders, background color and character string. The upper-left corner of the cell corresponds to the current position. The text can be aligned or centered. After the call, the current position moves to the right or to the next line. It is possible to put a link on the text.  
If automatic page breaking is enabled and the cell goes beyond the limit, a page break is done before outputting.

## Parameters

* `w` Cell width. If `0`, the cell extends up to the right margin.

* `h` Cell height. Default value: `0`.

* `txt` String to print. Default value: empty string.

* `border` Indicates if borders must be drawn around the cell. The value can be either a number:

*   `0`: no border
*   `1`: frame

or a string containing some or all of the following characters (in any order):

*   `L`: left
*   `T`: top
*   `R`: right
*   `B`: bottom

Default value: `0`.

<dt>`ln`</dt>

<dd>Indicates where the current position should go after the call. Possible values are:

*   `0`: to the right
*   `1`: to the beginning of the next line
*   `2`: below

Putting `1` is equivalent to putting `0` and calling Ln() just after. Default value: `0`.</dd>

<dt>`align`</dt>

<dd>Allows to center or align the text. Possible values are:

*   `L` or empty string: left align (default value)
*   `C`: center
*   `R`: right align

</dd>

<dt>`fill`</dt>

<dd>Indicates if the cell background must be painted (`true`) or transparent (`false`). Default value: `false`.</dd>

<dt>`link`</dt>

<dd>URL or identifier returned by AddLink().</dd>

</dl>

## Example

<div class="doc-source">

    // Set font
    $pdf->setFont('Arial','B',16);
    // Move to 8 cm to the right
    $pdf->addCell(80);
    // Centered text in a framed 20*10 mm cell and line break
    $pdf->addCell(20,10,'Title',1,1,'C');

</div>

## See also

[SetFont](setfont.htm), [SetDrawColor](setdrawcolor.htm), [SetFillColor](setfillcolor.htm), [SetTextColor](settextcolor.htm), [SetLineWidth](setlinewidth.htm), [AddLink](addlink.htm), [Ln](ln.htm), [MultiCell](multicell.htm), [Write](write.htm), [SetAutoPageBreak](setautopagebreak.htm)

* * *

<div style="text-align:center">[Index](index.htm)</div>
