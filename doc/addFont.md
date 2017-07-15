# addFont

`addFont($family = "anyName", $style = "B", $file = "fontdata.file")`

## Description

Imports a TrueType, OpenType or Type1 font and makes it available. It is necessary to generate a font definition file first with the MakeFont utility.
The definition file (and the font file itself when embedding) must be present in the font directory. If it is not found, the error "Could not include font definition file" is raised.

## Parameters

#### `family`

Font family. The name can be chosen arbitrarily. If it is a standard family name, it will override the corresponding font.

#### `style`

Font style. Possible values are (case insensitive):

* empty string: regular
* `B`: bold
* `I`: italic
* `BI` or `IB`: bold italic

The default value is regular.

#### `file`

The font definition file.
By default, the name is built from the family and style, in lower case with no space.


## Example


```
$pdf->addFont('Comic', 'I');
```

is equivalent to:

```
$pdf->addFont('Comic', 'I', 'comici.php');
```

## See also

[setFont](setFont.md)

* * *

[Index](README.md)
