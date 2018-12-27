<?php
namespace PdfBuilder\Font;

use PdfBuilder\Pdf\ObjectStructure;
use PdfBuilder\Exception\PdfException;
use PdfBuilder\Stream\Stream;

class Font extends ObjectStructure
{

    /**
     * @var Stream
     */
    protected $definitionFile;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var string
     */
    protected $fontType;

    /**
     * @var array The font information
     */
    protected $fontMetrics = [];

    /**
     * @var integer Font stream size in bytes before compression
     */
    protected $fontSize = null;

    /**
     * @var ObjectStructure $fontStream The PDF object containing the TTF binary stream
     */
    protected $fontStream;

    /**
     * @var ObjectStructure $fontDescriptor The PDF font descriptor object
     */
    protected $fontDescriptor;

    /**
     * Constructor, loads font by name - either a TTF or custom DAT format.
     *
     * @param  string       $fontname
     * @param  null         $fontpath
     * @param  bool         $subset
     * @param  string       $encoding
     * @throws PdfException
     */
    public function __construct($fontname, $subset = true, $encoding = 'cp1252', $fontpath = null)
    {
        parent::__construct('Font');

        $fontpath = (empty($fontpath) ? __DIR__ . '/../../fonts/' : $fontpath);

        if (in_array(strtolower($fontname), ['helvetica', 'helvetica-bold', 'courier', 'courier-bold'])) {
            $this->fontType = 'core';
            $this->fontMetrics = ['FontName' => $fontname];
            return;
        }

        if (file_exists($fontpath . $fontname . '.dat') && $this->loadFontData($fontpath . $fontname)) {
            return;
        } elseif (file_exists($fontpath . $fontname . '.ttf')) {
            $this->loadTrueTypeFont($fontpath . $fontname, $subset, $encoding);
            return;
        }
        throw new PdfException('No TTF or DAT file found for font:' . $fontname);
    }

    /**
     * Get font name with style suffix
     *
     * @return string
     */
    public function getNameStyle()
    {
        $italic = empty($this->fontMetrics['ItalicAngle']) ? '' : 'I';
        $bold = empty($this->fontMetrics['Bold']) ? '' : 'B';
        return $this->fontMetrics['FontName'] . $italic . $bold;
    }

    /**
     * Get font type
     *
     * @return string
     */
    public function getFontType()
    {
        return $this->fontType;
    }

    /**
     * Load a TTF file, subset it for optimal file size, and
     * read out metrics for internal array.
     *
     * @param        $filename
     * @param bool   $subset
     * @param string $encoding
     */
    public function loadTrueTypeFont($filename, $subset = true, $encoding = 'cp1252')
    {
        $this->fontType = 'ttf';
        $this->parser = new Parser($filename . '.ttf');
        $this->fontStream = new ObjectStructure();

        if ($subset) {
            $font = $this->parser->subsetFont($encoding);
            $this->fontSize = $font->getSize();

            stream_copy_to_stream(
                $font->getResource(true),
                $this->fontStream->getDataStream()->getResource()
            );
        } else {
            $this->fontSize = $this->parser->getSize();

            stream_copy_to_stream(
                $this->parser->getResource(true),
                $this->fontStream->getDataStream()->getResource()
            );
        }

        $this->fontStream->setValue('Length1', $this->fontSize);

        $this->indirectObjects[] =  $this->fontStream;
        $this->fontMetrics       =& $this->parser->fontInfo;

        $this->saveData($filename);
    }

    /**
     * Build PDF CMap for CID-keyed fonts...
     *
     * @return array
     */
    public function getCidToUnicodeArray()
    {
        return $this->fontMetrics['ToUnicode'];
    }

    /**
     * Get raw TTF data (unless it's a core-font) and metrics from
     * custom DAT-file format.
     *
     * @param $filename
     * @return bool
     */
    public function loadFontData($filename)
    {
        $this->definitionFile = new Stream(fopen($filename . '.dat', 'rb'));
        $totalSize = $this->definitionFile->getSize();

        if ($totalSize < 16) {
            return false;
        }
        $metricsLength     = $this->definitionFile->readULong();
        $this->fontSize    = $this->definitionFile->readULong();
        $this->fontMetrics = json_decode(base64_decode($this->definitionFile->read($metricsLength)), true);

        if ($totalSize > $metricsLength + 8) {
            $this->fontStream = new ObjectStructure();

            stream_copy_to_stream(
                $this->definitionFile->getResource(),
                $this->fontStream->getDataStream(false)->getResource()
            );

            $this->fontStream->setValue('Length', $this->fontStream->getDataStream()->getSize());
            $this->fontStream->setArrayName('Filter', 'FlateDecode');
            $this->fontStream->setValue('Length1', $this->fontSize);

            $this->indirectObjects[] = $this->fontStream;
        }
        return true;
    }

    /**
     * Save metrics and any subsetted TTF data to custom
     * DAT format for quicker initialization next tim.
     *
     * @param string $filename
     */
    public function saveData($filename)
    {
        $file = new Stream(fopen($filename . '.dat', 'wb'));
        $metrics = base64_encode(json_encode($this->fontMetrics));

        $file->write(pack('N', strlen($metrics)));
        $file->write(pack('N', $this->fontSize));
        $file->write($metrics);

        stream_copy_to_stream(
            $this->fontStream->getDataStream()->getResource(true),
            $file->getResource()
        );
        $file->close();
    }

    /**
     * Get the TTF data as PDF object
     *
     * @return ObjectStructure
     */
    public function getFontStreamObject()
    {
        return $this->fontStream;
    }

    /**
     * Get the font descriptor PDF object
     *
     * @return ObjectStructure
     */
    public function getFontDescriptorObject()
    {
        if (empty($this->fontDescriptor)) {
            $obj = new ObjectStructure('FontDescriptor');
            $obj->setValue('FontName', $this->fontMetrics['FontName']);
            $obj->setValue('Ascent', $this->fontMetrics['Ascent']);
            $obj->setValue('Descent', $this->fontMetrics['Descent']);
            $obj->setValue('CapHeight', $this->fontMetrics['CapHeight']);
            $obj->setValue('Flags', 32);//$this->fontMetrics['Flags']);
            $obj->setArrayValue('FontBBox', $this->fontMetrics['FontBBox']);
            $obj->setValue('ItalicAngle', $this->fontMetrics['ItalicAngle']);
            $obj->setValue('StemV', $this->fontMetrics['StemV']);
            $obj->setValue('FontFile2', $this->getFontStreamObject()->getReference());

            $this->indirectObjects[] = $obj;
            $this->fontDescriptor    = $obj;
        }
        return $this->fontDescriptor;
    }

    /**
     * Get PDF ready streams for the fonts, for now there's no
     * support for CID-keyed fonts - the TTF should be cp1252.
     *
     * @param  null     $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        if (!$this->has('Subtype')) {
            if ($this->getFontType() == 'ttf') {
                $this->setName('Subtype', 'TrueType');
                $this->setName('BaseFont', $this->fontMetrics['FontName']);
                $this->setValue('FirstChar', 32);
                $this->setValue('LastChar', 255);
                $this->setValue('FontDescriptor', $this->getFontDescriptorObject()->getReference());

                for ($i = 32; $i <= 255; $i++) {
                    $this->setArrayValue('Widths', $this->fontMetrics['CharacterWidths'][$i], true);
                }
            } else {
                $this->setName('Subtype', 'Type1');
                $this->setName('BaseFont', $this->fontMetrics['FontName']);
            }
            if (!in_array($this->fontMetrics['FontName'],  ['Symbol', 'ZapfDingbats'])) {
                $this->setName('Encoding', 'WinAnsiEncoding');
            }
        }
        return parent::getStreams($document);
    }
}
