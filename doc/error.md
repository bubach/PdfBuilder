# Error

`Error(**string** msg)`

## Description

This method is automatically called in case of a fatal error; it simply throws an exception with the provided message.  
An inherited class may override it to customize the error handling but the method should never return, otherwise the resulting document would probably be invalid.

## Parameters

<dl class="param">

<dt>`msg`</dt>

<dd>The error message.</dd>

</dl>

* * *

<div style="text-align:center">[Index](index.htm)</div>
