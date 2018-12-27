<?php
namespace PdfBuilder;

use ArrayIterator;
use PdfBuilder\Pdf\ObjectStructure;
use PdfBuilder\Pdf\CrossReferences;
use PdfBuilder\Stream\Stream;

class Document
{
    /**
     * Constant for library version
     */
    const VERSION = '1.0.0';

    /**
     * @var ObjectStructure The PDF Catalog directory.
     */
    protected $catalogObject;

    /**
     * @var ObjectStructure The PDF Catalog directory.
     */
    protected $pagesObject;

    /**
     * @var CrossReferences The Pdf builder instance.
     */
    protected $xrefObject;

    /**
     * @var integer The current object offset for xref.
     */
    protected $offset;

    /**
     * @var array Page[] Array of Page instances
     */
    protected $pages = [];

    /**
     * @var ObjectStructure[] Document objects.
     */
    protected $indirectObjects = [];

    /**
     * @var float PDF format version
     */
    public $pdfVersion = 1.7;

    /**
     * @var string Random hexadecimal document Id for meta-data
     */
    public $documentId;

    /**
     * Constructor. This represents the PDF object 'Pages'.
     *
     * @param array $options Bounding box options.
     */
    public function __construct($options = [])
    {
        $this->documentId    = uniqid().uniqid();
        $this->catalogObject = new ObjectStructure('Catalog');
        $this->pagesObject   = new ObjectStructure('Pages');

        $this->add($this->catalogObject);
        $this->add($this->pagesObject);

        $this->catalogObject->setValue('Pages', $this->pagesObject->getReference());
        $this->xrefObject = new CrossReferences($this);
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
            $page = new Page($this->pagesObject);
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
     * Get the number of objects we're building.
     *
     * @return int
     */
    public function getObjectCount()
    {
        return count($this->indirectObjects);
    }

    /**
     * Add an object to the document, supports creating from
     * string & some special handling for Page instances.
     *
     * @param  $object
     * @return ObjectStructure
     */
    public function add($object)
    {
        if (is_string($object)) {
            $object = new ObjectStructure($object);
        }

        if ($object instanceof Page) {
            $this->pages[] = $object;
            $object->pageNo = count($this->pages);
            $object->setParent($this->pagesObject);

            $this->pagesObject->setArrayValue('Kids', $object->getReference(), true);
            $this->pagesObject->setValue('Count', $this->pagesObject->getCount('Kids'));
        }

        $object->objectId = $this->getObjectCount() + 1;
        $this->indirectObjects[$object->objectId] = $object;

        return $object;
    }

    /**
     * Get the Pages Dictionary
     *
     * @return ObjectStructure
     */
    public function getPagesDictionary()
    {
        return $this->pagesObject;
    }

    /**
     * Get Catalog Dictionary
     *
     * @return ObjectStructure
     */
    public function getCatalogDictionary()
    {
        return $this->catalogObject;
    }

    /**
     * Get the Xref object
     *
     * @return CrossReferences
     */
    public function getCrossReferenceTable()
    {
        return $this->xrefObject;
    }

    /**
     * Get a trailer object instance by name
     *
     * @param  $name
     * @return ObjectStructure
     */
    public function getTrailerObject($name)
    {
        return $this->getCrossReferenceTable()->getTrailerObject($name);
    }

    /**
     * Set PDF meta information
     *
     * @param $key
     * @param $value
     */
    public function setTrailerInfoString($key, $value)
    {
        $this->getTrailerObject('Info')->setString($key, $value);
    }

    /**
     * Set Catalog preference value
     *
     * @param $value
     */
    public function addCatalogViewerPreference($value)
    {
        $this->catalogObject->setDictionaryName('ViewerPreferences', $value, true);
    }

    /**
     * Flush the PDF output to provided stream,
     * STDOUT is default output stream.
     *
     * @param  resource|null $IOResource
     * @return Stream
     */
    public function output($IOResource = null)
    {
        if (!is_resource($IOResource)) {
            $IOResource = STDOUT;
        }

        $destination = new Stream($IOResource);
        $destination->write(sprintf("%%PDF-%.1F\n%s", $this->pdfVersion, "%\xe2\xe3\xcf\xd3"));
        $this->offset = $destination->getSize();

        /** @var $lazyObjectIterator ObjectStructure[] */
        $lazyObjectIterator = new ArrayIterator($this->indirectObjects);

        foreach ($lazyObjectIterator as $objectId => $object) {
            $oStreams = $object->getStreams($this);

            if (isset($object->indirectObjects)) {
                foreach ($object->indirectObjects as $lazyObject) {
                    $lazyObjectIterator[$lazyObject->objectId] = $lazyObject;
                }
            }
            $this->getCrossReferenceTable()->addXRef($objectId, $this->offset);

            foreach ($oStreams as $objectStream) {
                $this->offset += $objectStream->getSize();
                stream_copy_to_stream($objectStream->getResource(), $destination->getResource());
            }
        }

        $this->getCrossReferenceTable()->setTableOffset($this->offset);
        stream_copy_to_stream($this->getCrossReferenceTable()->getXrefStream()->getResource(), $destination->getResource());
        $this->offset += $this->getCrossReferenceTable()->getXrefStream()->getSize();

        return $destination;
    }
}
