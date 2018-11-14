<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Page;

class PagesDictionary extends CosObject
{

    /**
     * Constructor
     *
     * @return self
     */
    public function __construct()
    {
        parent::__construct('Pages');
        $this->setMediaBox(0, 0, Page::$sizes[Page::$defaultSize][0], Page::$sizes[Page::$defaultSize][1]);
    }

    /**
     * Add page reference in Kids-entry
     *
     * @param Page $page
     */
    public function addPageEntry(Page $page)
    {
        $this->setArrayValue('Kids', $page->getLazyReference(), true);
        $this->setValue('Count', $this->getCount('Kids'));
    }

    /**
     * Set page MediaBox
     *
     * @param $left
     * @param $bottom
     * @param $right
     * @param $top
     */
    public function setMediaBox($left, $bottom, $right, $top)
    {
        $this->setArrayValue('MediaBox', [$left, $bottom, $right, $top]);
    }
}
