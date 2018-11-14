<?php
namespace PdfBuilder\Pdf;

class CatalogDictionary extends CosObject
{
    /**
     * @var array
     */
    protected $preferences = [];

    /**
     * Set type.
     *
     * @return self
     */
    public function __construct()
    {
        parent::__construct('Catalog');
    }

    /**
     * Set Catalog preference value
     *
     * @param $value
     */
    public function addViewerPreference($value)
    {
        $this->setObjectName('ViewerPreferences', $value, true);
    }
}
