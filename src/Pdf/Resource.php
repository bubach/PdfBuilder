<?php
namespace PdfBuilder\Pdf;

class Resource extends CosObject
{

    /**
     * Resources object constructor
     *
     * @return self
     */
    public function __construct()
    {
        $this->setArrayName('ProcSet', ['PDF', 'TEXT', 'ImageB', 'ImageC', 'ImageI']);
        $this->setObjectValue('Font', null);
        $this->setObjectValue('XObject', null);
    }
}
