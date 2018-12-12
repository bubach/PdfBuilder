<?php
namespace PdfBuilder;

use PdfBuilder\Pdf\CosStructure;
use PdfBuilder\Stream\Stream;

class Page extends CosStructure
{

    /**
     * @var int The current page number
     */
    public $pageNo = 0;

    /**
     * @var array Contents object
     */
    protected $contents;

    /**
     * @var CosStructure Resources object
     */
    protected $resources;

    /**
     * @var array List of page objects, possibly without object ID's until rendering.
     */
    public $indirectObjects = [];

    /**
     * @var array Default page margins
     */
    protected  $defaultMargins = [
        "top" => 72,
        "left" => 72,
        "bottom" => 72,
        "right" => 72
    ];

    /**
     * @var string The default page-size
     */
    public static $defaultSize = "A4";

    /**
     * @var array Size measurements
     */
    public static $sizes = [
        "4A0" => [4767.87, 6740.79], "2A0" => [3370.39, 4767.87], "A0" => [2383.94, 3370.39],
        "A1" => [1683.78, 2383.94], "A2" => [1190.55, 1683.78], "A3" => [841.89, 1190.55],
        "A4" => [595.28, 841.89], "A5" => [419.53, 595.28], "A6" => [297.64, 419.53],
        "A7" => [209.76, 297.64], "A8" => [147.40, 209.76], "A9" => [104.88, 147.40],
        "A10" => [73.70, 104.88], "B0" => [2834.65, 4008.19], "B1" => [2004.09, 2834.65],
        "B2" => [1417.32, 2004.09], "B3" => [1000.63, 1417.32], "B4" => [708.66, 1000.63],
        "B5" => [498.90, 708.66], "B6" => [354.33, 498.90], "B7" => [249.45, 354.33],
        "B8" => [175.75, 249.45], "B9" => [124.72, 175.75], "B10" => [87.87, 124.72],
        "C0" => [2599.37, 3676.54], "C1" => [1836.85, 2599.37], "C2" => [1298.27, 1836.85],
        "C3" => [918.43, 1298.27], "C4" => [649.13, 918.43], "C5" => [459.21, 649.13],
        "C6" => [323.15, 459.21], "C7" => [229.61, 323.15], "C8" => [161.57, 229.61],
        "C9" => [113.39, 161.57], "C10" => [79.37, 113.39], "RA0" => [2437.80, 3458.27],
        "RA1" => [1729.13, 2437.80], "RA2" => [1218.90, 1729.13], "RA3" => [864.57, 1218.90],
        "RA4" => [609.45, 864.57], "SRA0" => [2551.18, 3628.35], "SRA1" => [1814.17, 2551.18],
        "SRA2" => [1275.59, 1814.17], "SRA3" => [907.09, 1275.59], "SRA4" => [637.80, 907.09],
        "EXECUTIVE" => [521.86, 756.00], "FOLIO" => [612.00, 936.00], "LEGAL" => [612.00, 1008.00],
        "LETTER" => [612.00, 792.00], "TABLOID" => [792.00, 1224.00]
    ];

    /**
     * Constructor allows for orphan pages.
     *
     * @param null  $parent
     */
    public function __construct($parent = null)
    {
        parent::__construct('Page', (($parent) ? $parent : $this));

        $this->contents = new CosStructure();
        $this->resources = new CosStructure('Resources');

        $this->resources->setArrayName('ProcSet', ['PDF', 'TEXT', 'ImageB', 'ImageC', 'ImageI']);
        $this->resources->setObjectValue('Font', null);
        $this->resources->setObjectValue('XObject', null);

        $this->add($this->contents);
        $this->add($this->resources);
    }

    /**
     * Set page parent
     *
     * @param $parent
     */
    public function setParent(CosStructure $parent)
    {
        $this->setValue('Parent', $parent->getLazyReference());
    }

    /**
     * Keep internal list of objects, so that a page
     * can be attached to a document at later time.
     *
     * @param  CosStructure $object
     * @return $this
     */
    public function add($object)
    {
        return $this->indirectObjects[] = $object;
    }

    /**
     * Set page bounding box
     *
     * @param string $type
     * @param array  $dimensions Left, bottom, right, top.
     */
    public function setBoundingBox($type = 'MediaBox', $dimensions)
    {
        if (!is_array($dimensions) || count($dimensions) !== 4) {
            $dimensions = [0, 0];
            array_push($dimensions, Page::$sizes[Page::$defaultSize][0]);
            array_push($dimensions, Page::$sizes[Page::$defaultSize][1]);
        }
        $this->setArrayValue($type, $dimensions);
    }

    /**
     * For now, hardcoded AF
     *
     * @param         $fontName
     * @param  int    $fontSize
     * @param  string $style
     * @return        $this
     */
    public function setFont($fontName, $fontSize = 10, $style = '')
    {
        /** @var $fonts CosStructure[] */
        static $fonts = [];

        $fontNumber = 0;
        $font = new CosStructure('Font');
        $font->setName('BaseFont', $fontName);
        $font->setName('Subtype', 'Type1');
        $font->setName('Encoding', 'WinAnsiEncoding');

        if (isset($fonts[$fontName])) {
            foreach ($fonts as $font) {
                $fontNumber++;

                if ($font->get('/BaseFont') === $fontName) {
                    break;
                }
            }
        } else {
            $this->add($font);
            $fontNumber++;
        }

        $this->resources->setObjectValue(
            'Font',
            [sprintf('/F%d', $fontNumber), $font->getLazyReference()]
        );

        $this->contents->addStreamData(
            sprintf("BT /F%d %d Tf ET\n", $fontNumber, $fontSize)
        );

        return $this;
    }

    /**
     * Print out text to the PDF, low level routine with no
     * bounding box overflow checks made.
     *
     * @param  $x
     * @param  $y
     * @param  $text
     * @return $this
     */
    public function addText($x, $y, $text)
    {
        $text = '(' . $this->escapeValue($text) . ')';
        $scaleFactor = 1.0;
        $height      = Page::$sizes[Page::$defaultSize][1];

        $y = ($height - $y) * $scaleFactor;
        $x = $x * $scaleFactor;
        $rgbColor = 0;

        $this->contents->addStreamData(
            //sprintf("q %.3F g BT /F1 %d Tf %.2F %.2F Td %s Tj ET Q\n", $rgbColor, $fontSizePt, $x, $y, $text)
            sprintf("BT %.2F %.2F Td %s Tj ET\n", $x, $y, $text)
        );

        return $this;
    }

    /**
     * Debug breakpoint access
     *
     * @param  null     $document
     * @return Stream[]
     */
    public function getStreams($document = null)
    {
        if (!$this->has('MediaBox')) {
            $size = Page::$sizes[Page::$defaultSize];
            $this->setBoundingBox('MediaBox', 0, 0, $size[0], $size[1]);
        }

        if (!$this->contents->isEmpty()) {
            $this->setValue('Contents', $this->contents->getLazyReference());
        }

        if (!$this->resources->isEmpty()) {
            $this->setValue('Resources', $this->resources->getLazyReference());
        }

        return parent::getStreams($document);
    }
}
