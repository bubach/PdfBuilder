# __construct

`__construct($orientation = "P", $unit = "mm" , $size = "A4")`

## Description

This is the class constructor. It allows to set up the page size, the orientation and the unit of measure used in all methods (except for font sizes).

## Parameters

#### `orientation`

Default page orientation. Possible values are (case insensitive):

* `P` or `Portrait`
* `L` or `Landscape`

Default value is `P`.

#### `unit`

User unit. Possible values are:

* `pt`: point
* `mm`: millimeter
* `cm`: centimeter
* `in`: inch

A point equals 1/72 of inch, that is to say about 0.35 mm (an inch being 2.54 cm). This is a very common unit in typography; font sizes are expressed in that unit.

Default value is `mm`.

#### `size`

The size used for pages. It can be either one of the following values (case insensitive):

* `A3`
* `A4`
* `A5`
* `Letter`
* `Legal`

or an array containing the width and the height (expressed in the unit given by `unit`).

Default value is `A4`.


## Example

Example with a custom 100x150 mm page size:

```
$pdf = new FPDF('P','mm',array(100,150));
```

* * *

[Index](readme.md)</div>
