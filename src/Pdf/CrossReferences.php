<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Stream\Stream;

class CrossReferences extends CosStructure
{

    /**
     * @var CosStructure[] Trailer objects to output.
     */
    protected $trailerObjects = [];

    /**
     * @var array Cross references for the Xref-table.
     */
    protected $crossReferences = [];

    /**
     * @var Builder Reference to the builder instance.
     */
    protected $builder;

    /**
     * @var int The xref table's offset inside the PDF.
     */
    protected $tableOffset = 0;

    /**
     * Constructor.
     *
     * @param  Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
        $this->getTrailerObject('Info');
    }

    /**
     * Get trailer object
     *
     * @param  $name
     * @return CosStructure
     */
    public function getTrailerObject($name)
    {
        if (!isset($this->trailerObjects[$name])) {
            $this->trailerObjects[$name] = new CosStructure();
            $this->builder->add($this->trailerObjects[$name]);
        }
        return $this->trailerObjects[$name];
    }

    /**
     * Add a reference to the Xref table
     *
     * @param $objectId
     * @param $offset
     */
    public function addXref($objectId, $offset)
    {
        $this->crossReferences[$objectId] = $offset;
    }

    /**
     * Empty all references.
     *
     * @return $this
     */
    public function emptyXrefTable()
    {
        $this->crossReferences = [];
        return $this;
    }

    /**
     * Set output location for the xref table.
     *
     * @param int $offset Offset for table output.
     */
    public function setTableOffset($offset)
    {
        $this->tableOffset = $offset;
    }

    /**
     * Get Xref table stream
     *
     * @param  null     $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        $stream = new Stream();
        $objectCount = $this->builder->getObjectCount() + 1;

        $stream->writeString(sprintf("\nxref\n0 %s\n0000000000 65535 f \n", $objectCount));

        foreach ($this->crossReferences as $offset) {
            $stream->writeString(sprintf("%010d 00000 n \n", $offset + 1));
        }

        $stream->writeString("trailer\n<<");

        $catalogRef = $this->builder->getCatalog()->getReference();
        $stream->writeString(sprintf("\n/Size %s\n/Root %s", $objectCount, $catalogRef));

        foreach ($this->trailerObjects as $name => $object) {
            $stream->writeString(sprintf("\n/%s %s", $name, $object->getReference()));
        }

        $stream->writeString("\n>>\n");
        $stream->writeString(sprintf("startxref\n%s\n%%%%EOF\n", ($this->tableOffset + 1)));
        $stream->seek(0);

        return [$stream];
    }
}
