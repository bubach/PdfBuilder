<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Exception\PdfException;
use PdfBuilder\Stream\Stream;
use PdfBuilder\Document;
use ArrayIterator;

class Builder
{

    /**
     * @var CosStructure[] Document objects.
     */
    public $indirectObjects = [];

    /**
     * @var CrossReferences The PDF xref table.
     */
    protected $xref;

    /**
     * @var integer The current object offset for xref.
     */
    protected $offset;

    /**
     * @var CosStructure The PDF Catalog directory.
     */
    protected $catalog;

    /**
     * @var CosStructure The PDF Pages dictionary.
     */
    protected $pages;

    /**
     * Constructor.
     *
     * @param  Document $pages
     * @return self
     */
    public function __construct($pages)
    {
        $this->catalog = new CosStructure('Catalog');
        $this->add($this->catalog);

        $this->pages = $pages;
        $this->add($pages);

        $this->xref = new CrossReferences($this);

        $this->setInfoString('Producer', 'PdfBuilder ' . $pages->getVersion());
        $this->setInfoString('CreationDate', 'D:' . @date('YmdHis'));

        $this->catalog->setValue('Pages', $pages->getReference());
    }

    /**
     * Get the Catalog directory
     *
     * @return CosStructure
     */
    public function getCatalog()
    {
        return $this->catalog;
    }

    /**
     * Get PDF cross references table.
     *
     * @return CrossReferences
     */
    public function getCrossReferences()
    {
        return $this->xref;
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
     * Add an object to be outputted
     *
     * @param  $object
     * @return CosStructure
     */
    public function add(CosStructure $object)
    {
        $count = $this->getObjectCount() + 1;
        $this->indirectObjects[$count] = $object;
        $object->objectId = $count;

        return $object;
    }

    /**
     * Get reference to trailer objects
     *
     * @param  $name
     * @return CosStructure
     */
    public function getTrailerObject($name)
    {
        return $this->xref->getTrailerObject($name);
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
     * Get all PDF streams and combine to one. Supports lazy add
     * of objects to the document tree while looping.
     *
     * @param  string|Stream $destination
     * @throws PdfException
     * @return Stream
     */
    public function pipeStreams($destination)
    {
        if (is_string($destination)) {
            if (!$f = fopen($destination, 'wb')) {
                throw new PdfException('Unable to create output file: ' . $destination);
            }
            $destination = new Stream($f);
        }

        $destination->writeString(sprintf("%%PDF-%.1F\n%s", $this->pages->getVersion(), "%\xe2\xe3\xcf\xd3"));
        $this->offset = $destination->getSize();

        /** @var $lazyObjectIterator CosStructure[] */
        $lazyObjectIterator = new ArrayIterator($this->indirectObjects);

        foreach ($lazyObjectIterator as $objectId => $object) {
            $oStreams = $object->getStreams($this->pages);

            if (isset($object->indirectObjects) && ($object !== $this)) {
                foreach ($object->indirectObjects as $lazyObject) {
                    $lazyObjectIterator[$lazyObject->objectId] = $lazyObject;
                }
            }
            $this->xref->addXRef($objectId, $this->offset);

            foreach ($oStreams as $objectStream) {
                $this->offset += $objectStream->getSize();
                stream_copy_to_stream($objectStream->getStream(), $destination->getStream());
            }
        }

        $this->xref->setTableOffset($this->offset);
        $xref = $this->xref->getStreams();
        $xref = reset($xref);
        stream_copy_to_stream($xref->getStream(), $destination->getStream());
        $this->offset += $xref->getSize();

        return $destination;
    }

    /**
     * Get the total size of all streams after
     * calling pipeStreams()
     *
     * @return integer Size in bytes
     */
    public function getSize()
    {
        return $this->offset;
    }

    /**
     * Get the PDF output, as file, forced download, inline
     * browser viewing or raw string.
     *
     * @param  string $filename
     * @param  string $destination
     * @return string
     * @throws PdfException
     */
    public function output($filename = 'file.pdf', $destination = 'I')
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
            header('Content-Type: application/pdf');
            exit;
        }

        switch ($destination) {
            case 'S':
                ob_start();
                $this->pipeStreams('php://output');
                return ob_get_clean();
                break;
            case 'I':
                header("Content-Type: application/pdf");
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header("Content-Length: " . $this->getSize());

                $this->pipeStreams('php://output');
                break;
            case 'D':
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header("Content-Length: " . $this->getSize());

                $this->pipeStreams('php://output');
                break;
            default:
                $this->pipeStreams($filename);
                break;
        }
    }
}
