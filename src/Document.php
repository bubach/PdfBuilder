<?php
namespace PdfBuilder;

use PdfBuilder\Pdf\CosStructure;
use PdfBuilder\Pdf\Builder;

class Document extends CosStructure
{
    /**
     * Constant for library version
     */
    const VERSION = '1.0.0';

    /**
     * @var Builder The Pdf builder instance.
     */
    protected $builder;

    /**
     * @var array Page[] Array of Page instances
     */
    protected $pages = [];

    /**
     * @var float PDF format version
     */
    public $pdfVersion = 1.7;

    /**
     * Constructor.
     *
     * @param array $options Bounding box options.
     * @param null  $builder
     */
    public function __construct($options = [], $builder = null)
    {
        parent::__construct('Pages');
        $this->builder = ($builder instanceof Builder) ? $builder : new Builder($this);
    }

    /**
     * Set page bounding box
     *
     * @param string $type
     * @param array  $dimensions Left, bottom, right, top.
     */
    public function setBoundingBox($type = 'MediaBox', $dimensions)
    {
        $this->getPage()->setArrayValue($type, $dimensions);
    }

    /**
     * Add a new or existing page to the document
     *
     * @param  null|Page $page
     * @return Page
     */
    public function addPage($page = null)
    {
        if (!$page instanceof Page) {
            $page = new Page($this);
        }
        return $this->add($page);
    }

    /**
     * Get a page or newest if none specified,
     * create one if none exists.
     *
     * @param  null|int $pageNo
     * @return Page
     */
    public function getPage($pageNo = null)
    {
        if (empty($this->pages)) {
            return $this->addPage();
        } elseif (empty($pageNo) || !isset($this->pages[$pageNo - 1])) {
            return end($this->pages);
        }
        return $this->pages[$pageNo - 1];
    }

    /**
     * Add an object to the document, supports
     * creating from string & some special handling for
     * Page instances
     *
     * @param  $object
     * @return CosStructure
     */
    public function add($object)
    {
        if (is_string($object)) {
            $object = new CosStructure($object);
        }

        if ($object instanceof Page) {
            $this->pages[] = $object;
            $object->pageNo = count($this->pages);
            $object->setParent($this);

            $this->setArrayValue('Kids', $object->getLazyReference(), true);
            $this->setValue('Count', $this->getCount('Kids'));
        }

        return $this->builder->add($object);
    }

    /**
     * Get the library version.
     *
     * @return string PdfBuilder version
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Get reference to trailer objects
     *
     * @param  $name
     * @return CosStructure
     */
    public function getTrailerObject($name)
    {
        return $this->builder->getCrossReferences()->getTrailerObject($name);
    }

    /**
     * Set PDF meta information
     *
     * @param $key
     * @param $value
     */
    public function setInfoString($key, $value)
    {
        $this->getTrailerObject('Info')->setString($key, $value);
    }

    /**
     * Set Catalog preference value
     *
     * @param $value
     */
    public function addViewerPreference($value)
    {
        $this->builder->getCatalog()->setObjectName('ViewerPreferences', $value, true);
    }

    /**
     * Get th PDF output, as string 'S', download 'D', browser inline 'I' or file 'F'.
     *
     * @param string $filename
     * @param string $destination
     * @return mixed
     */
    public function output($filename = 'document.pdf', $destination = 'I')
    {
        return $this->builder->output($filename, $destination);
    }
}
