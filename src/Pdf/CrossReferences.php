<?php
namespace PdfBuilder\Pdf;

use PdfBuilder\Document;
use PdfBuilder\Stream\Stream;

class CrossReferences
{
    /**
     * @var Document The PDF Pages dictionary.
     */
    protected $document;

    /**
     * @var ObjectStructure[] Trailer objects to output.
     */
    protected $trailerObjects = [];

    /**
     * @var array Cross references for the Xref-table.
     */
    protected $crossReferences = [];

    /**
     * @var int The xref table's offset inside the PDF.
     */
    protected $tableOffset = 0;

    /**
     * @var Stream The Stream object containing the table
     */
    protected $xRefTableStream;

    /**
     * Constructor.
     *
     * @param Document $document
     */
    public function __construct($document)
    {
        $this->document = $document;

        $info = $this->getTrailerObject('Info');
        $info->setString('Producer', 'PdfBuilder ' . $document::VERSION);
        $info->setValue('CreationDate', 'D:' . @date('YmdHis'));

        $outputIntent = new IccProfile();
        $this->document->add($outputIntent);

        $catalog = $document->getCatalogDictionary();
        $catalog->setArrayValue('OutputIntents', $outputIntent->getOutputIntentsArrayValue());

        $meta = $this->getMetaDataObject();
        $catalog->add($meta);
        $catalog->setValue('Metadata', $meta->getReference());
    }

    /**
     * Get trailer object
     *
     * @param  $name
     * @return ObjectStructure
     */
    public function getTrailerObject($name)
    {
        if (!isset($this->trailerObjects[$name])) {
            $this->trailerObjects[$name] = $this->document->add($name);
        }
        return $this->trailerObjects[$name];
    }

    /**
     * Basic placeholder Meta XMP
     *
     * @return ObjectStructure
     */
    protected function getMetaDataObject()
    {
        $obj = new ObjectStructure('Metadata');
        $obj->setName('Subtype', 'XML');
        $ver = $this->document;
        $ver = $ver::VERSION;

        $stream = $obj->getDataStream(false);

        $stream->write('<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>');
        $stream->write('<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Adobe XMP Core 4.2.1-c043 52.372728, 2009/01/18-15:08:04">');
        $stream->write('<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">');
        $stream->write('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">');
        $stream->write('<dc:format>application/pdf</dc:format>');
        $stream->write('<dc:creator><rdf:Seq><rdf:li>PdfBuilder ' . $ver .'</rdf:li></rdf:Seq></dc:creator></rdf:Description>');
        $stream->write('<rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/">');
        $stream->write('<xmp:CreateDate>D:' . @date('YmdHis') .'</xmp:CreateDate>');
        $stream->write('</rdf:Description><rdf:Description rdf:about="" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">');
        $stream->write('<pdf:Producer>PdfBuilder ' . $ver .'</pdf:Producer></rdf:Description></rdf:RDF>');
        $stream->write('</x:xmpmeta><?xpacket end="w"?>');

        $obj->setValue('Length', $stream->getSize());
        return $obj;
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
        unset($this->xRefTableStream);

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
     * @return Stream
     */
    public function getXrefStream()
    {
        if (!$this->xRefTableStream instanceof Stream) {
            $this->xRefTableStream = new Stream();
            $objectCount = $this->document->getObjectCount() + 1;

            $this->xRefTableStream->write(sprintf("\nxref\n0 %s\n0000000000 65535 f \n", $objectCount));

            foreach ($this->crossReferences as $offset) {
                $this->xRefTableStream->write(sprintf("%010d 00000 n \n", $offset + 1));
            }

            $this->xRefTableStream->write("trailer\n<<");

            $catalogRef = $this->document->getCatalogDictionary()->getReference();
            $documentId = $this->document->documentId;

            $this->xRefTableStream->write(sprintf("\n    /Size %s\n    /Root %s", $objectCount, $catalogRef));
            $this->xRefTableStream->write(sprintf("\n    /ID [<%s> <%s>]", $documentId, $documentId));

            foreach ($this->trailerObjects as $name => $object) {
                $this->xRefTableStream->write(sprintf("\n    /%s %s", $name, $object->getReference()));
            }

            $this->xRefTableStream->write("\n>>\n");
            $this->xRefTableStream->write(sprintf("startxref\n%s\n%%%%EOF\n", ($this->tableOffset + 1)));
            $this->xRefTableStream->seek(0);
        }
        return $this->xRefTableStream;
    }
}
