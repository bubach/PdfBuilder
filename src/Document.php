<?php
namespace PdfBuilder;

use PdfBuilder\Exception\PdfException;
use PdfBuilder\Pdf\CatalogDictionary;
use PdfBuilder\Pdf\PagesDictionary;
use PdfBuilder\Pdf\CosObject;
use PdfBuilder\Pdf\Stream;
use ArrayIterator;

class Document extends PagesDictionary
{
    /**
     * Constant for library version
     */
    const VERSION = '1.0.0';

    /**
     * @var array Options
     */
    protected $options;

    /**
     * @var CosObject[]
     */
    protected $objects = [];

    /**
     * @var array Page[]
     */
    protected $pages = [];

    /**
     * @var CatalogDictionary The Pdf catalog
     */
    protected $catalog;

    /**
     * @var CosObject The Pdf meta-information object
     */
    protected $information;

    /**
     * @var array Cross references for the Xref-table.
     */
    protected $crossReferences = [];

    /**
     * @var float PDF format version
     */
    public $pdfVersion = 1.3;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        parent::__construct();
        $this->catalog = new CatalogDictionary();
        $this->information = new CosObject();

        $this->add($this->catalog);
        $this->add($this);
        $this->add($this->information);

        $this->setDefaultInfoValues();

        $this->catalog->setValue('Pages', $this->getReference());
        $this->options = $options;

        $this->addPage();
    }

    /**
     * Add a new or existing page to the document
     *
     * @param  null $page
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
     * Set PDF meta information
     *
     * @param $key
     * @param $value
     */
    public function setInfo($key, $value)
    {
        $this->information->setString($key, $value);
    }

    /**
     * Set default PDF meta info values
     *
     * @return $this
     */
    public function setDefaultInfoValues()
    {
        $info = array(
            'Producer'     => 'PdfBuilder ' . self::VERSION,
            'CreationDate' => 'D:' . @date('YmdHis'),
        );

        foreach ($info as $key => $value) {
            $this->setInfo($key, $value);
        }
        return $this;
    }

    /**
     * Add an object
     *
     * @param  $object
     * @return CosObject
     */
    protected function add($object)
    {
        if (is_string($object)) {
            $object = new CosObject($object);
        }
        $count = count($this->objects) + 1;

        $this->objects[$count] = $object;
        $object->objectId = $count;

        if ($object instanceof Page) {
            $this->addPageEntry($object);

            $this->pages[] = $object;
            $object->pageNo = count($this->pages);
        }

        return $object;
    }

    /**
     * Add a reference to the Xref table
     *
     * @param $objectId
     * @param $offset
     */
    protected function addXref($objectId, $offset)
    {
        $this->crossReferences[$objectId] = $offset;
    }

    /**
     * Get array of streams to output
     *
     * @param  null     $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        $this->crossReferences = [];

        $streams[] = $header = new Stream();
        $header->writeString(sprintf("%%PDF-%.1F\n%s", $this->pdfVersion, "%\xe2\xe3\xcf\xd3"));
        $offset = $header->getSize();

        $lazyObjectIterator = new ArrayIterator($this->objects);

        /** @var $object CosObject */
        foreach ($lazyObjectIterator as $objectId => $object) {
            $oStreams = (($object === $this) ? parent::getStreams($this) : $object->getStreams($this));

            if (isset($object->objects) && ($object !== $this)) {
                /** @var $lazyObject CosObject */
                foreach ($object->objects as $lazyObject) {
                    $lazyObjectIterator[$lazyObject->objectId] = $lazyObject;
                }
            }

            $this->addXRef($objectId, $offset);
            $streams = array_merge($streams, $oStreams);

            foreach ($oStreams as $objectStream) {
                $offset += $objectStream->getSize();
            }
        }

        $streams[] = $this->getXrefStream($offset);
        return $streams;
    }

    /**
     * Get Xref table stream
     *
     * @param  $tableOffset
     * @return Stream
     */
    protected function getXrefStream($tableOffset)
    {
        $stream = new Stream();
        $stream->writeString(sprintf("\nxref\n0 %s\n0000000000 65535 f \n", (count($this->objects) + 1)));

        foreach ($this->crossReferences as $offset) {
            $stream->writeString(sprintf("%010d 00000 n \n", $offset + 1));
        }

        $stream->writeString("trailer\n<<");
        $stream->writeString(sprintf("\n/Size %s\n/Root %s", count($this->objects) + 1, $this->catalog->getReference()));

        if (isset($this->information)) {
            $stream->writeString(sprintf("\n/Info %s", $this->information->getReference()));
        }
        if (isset($this->encryptionObject)) {
            $stream->writeString(sprintf("\n/Encrypt %s", $this->encryptionObject->getReference()));
        }

        $stream->writeString("\n>>\n");
        $stream->writeString(sprintf("startxref\n%s\n%%%%EOF\n", ($tableOffset + 1)));

        return $stream;
    }

    /**
     * Get the PDF output, as file, forced download, inline
     * browser viewing or raw string.
     *
     * @param  string $filename
     * @param  string $destination
     * @return string
     * @throws Exception\PdfException
     */
    public function output($filename = 'file.pdf', $destination = 'I')
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
            header('Content-Type: application/pdf');
            exit;
        }

        if ($destination == 'S') {
            $string = '';
            foreach ($this->getStreams() as $pdfStream) {
                $pdfStream->seek(0);
                $string .= stream_get_contents($pdfStream->getStream());
            }
            return $string;
        }

        if ($destination == 'F') {
            if (!$f = fopen($filename, 'wb')) {
                throw new PdfException('Unable to create output file: '.$filename);
            }
            foreach ($this->getStreams() as $pdfStream) {
                $pdfStream->seek(0);
                stream_copy_to_stream($pdfStream->getStream(), $f);
            }
            fclose($f);
            exit;
        }

        if ($destination == 'I') {
                header("Content-Type: application/pdf");
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header("Content-Length: " . $this->getSize());
        } else if ($destination == 'D') {
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
        }

        foreach ($this->getStreams() as $pdfStream) {
            $pdfStream->seek(0);
            fpassthru($pdfStream->getStream());
        }
    }
}
