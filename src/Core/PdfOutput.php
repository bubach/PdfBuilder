<?php
namespace PdfBuilder\Core;

use PdfBuilder\PdfDocument;
use PdfBuilder\Core\PdfFonts;
use PdfBuilder\Core\PdfImages;
use PdfBuilder\Exception\PdfException;

class PdfOutput
{

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @var PdfFonts
     */
    protected $_pdfFonts;

    /**
     * @var PdfImages
     */
    protected $_pdfImages;

    /**
     * @var string PDF output buffer
     */
    public $outBuffer = '';

    /**
     * @var int Global number of pdf objects
     */
    protected $_pdfObjects = 2;

    /**
     * @var array Object offsets in output buffer
     */
    protected $_objectOffsets = array();

    /**
     * Construct output instance
     *
     * @param  PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument)
    {
        $this->_pdfDocument = $pdfDocument;
        $this->_pdfFonts    = new PdfFonts($this);
        $this->_pdfImages   = new PdfImages($this);
    }

    /**
     * Output document resources and close document
     */
    public function endDoc()
    {
        $this->_putHeader();
        $this->_putPages();
        $this->_putResources();

        $this->_putInfo();
        $this->_putCatalog();

        $o = strlen($this->outBuffer);
        $this->_putXRef();
        $this->_putTrailer($o);

        $pdfDocument = $this->_pdfDocument;
        $pdfDocument->setState($pdfDocument::STATE_END_DOC);
    }

    /**
     * @return int
     */
    public function getPdfObjects()
    {
        return $this->_pdfObjects;
    }

    /**
     * @param  $number
     * @return bool
     */
    public function getPdfObjectOffset($number)
    {
        return isset($this->_objectOffsets[$number]) ? $this->_objectOffsets[$number] : false;
    }

    /**
     * @param  $number
     * @param  $value
     * @return $this
     */
    public function setPdfObjectOffset($number, $value)
    {
        $this->_objectOffsets[$number] = $value;
        return $this;
    }

    /**
     * @return PdfImages
     */
    public function getImageOutputter()
    {
        return $this->_pdfImages;
    }

    /**
     * @return PdfFonts
     */
    public function getFontOutputter()
    {
        return $this->_pdfFonts;
    }

    /**
     * @return PdfDocument
     */
    public function getDocument()
    {
        return $this->_pdfDocument;
    }

    /**
     * Output to buffer(s)
     *
     * @param $s
     */
    public function out($s)
    {
        $pdfDocument = $this->getDocument();

        if ($pdfDocument->getState() == $pdfDocument::STATE_NEW_PAGE && $pdfDocument->getPageCount() > 0) {
            $pdfDocument->getPage()->pageBuffer .= $s."\n";
        } else {
            $this->outBuffer .= $s."\n";
        }
    }

    /**
     * Begin a new object
     */
    public function newObj()
    {
        $this->_pdfObjects++;
        $this->_objectOffsets[$this->_pdfObjects] = strlen($this->outBuffer);
        $this->out($this->_pdfObjects.' 0 obj');
    }

    /**
     * Output stream
     *
     * @param $s
     */
    public function putStream($s)
    {
        $this->out('stream');
        $this->out($s);
        $this->out('endstream');
    }

    /**
     * Output x object dict
     */
    protected function _putXObjectDict()
    {
        foreach ($this->getImageOutputter()->images as $image) {
            $this->out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    /**
     * Output resource dict
     */
    protected function _putResourceDict()
    {
        $this->out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->out('/Font <<');

        foreach ($this->getFontOutputter()->fonts as $font) {
            $this->out('/F'.$font['i'].' '.$font['n'].' 0 R');
        }

        $this->out('>>');
        $this->out('/XObject <<');
        $this->_putXObjectDict();
        $this->out('>>');
    }

    /**
     * Output Pdf resources
     */
    protected function _putResources()
    {
        $this->getFontOutputter()->putFonts();
        $this->getImageOutputter()->putImages();

        $this->setPdfObjectOffset(2, strlen($this->outBuffer));
        $this->out('2 0 obj');
        $this->out('<<');
        $this->_putResourceDict();
        $this->out('>>');
        $this->out('endobj');
    }

    /**
     * Output page data
     */
    protected function _putPages()
    {
        $document = $this->_pdfDocument;
        $nb       = $document->getPageCount();
        $aliasNb  = $document->getAliasNbPages();

        if (!empty($aliasNb)) {
            $alias = $this->UTF8ToUTF16BE($aliasNb, false);
            $r     = $this->UTF8ToUTF16BE("$nb", false);

            for ($n = 1; $n <= $nb; $n++) {
                $document->getPage($n)->pageBuffer = str_replace($alias, $r, $document->getPage($n)->pageBuffer);
            }
            for ($n = 1; $n <= $nb; $n++) {
                $document->getPage($n)->pageBuffer = str_replace($aliasNb, $nb, $document->getPage($n)->pageBuffer);
            }
        }

        $defPageSize = $document->getDefPageSize();
        if ($document->getDefOrientation() == 'P') {
            $wPt = $defPageSize[0] * $document->getScaleFactor();
            $hPt = $defPageSize[1] * $document->getScaleFactor();
        } else {
            $wPt = $defPageSize[1] * $document->getScaleFactor();
            $hPt = $defPageSize[0] * $document->getScaleFactor();
        }

        $filter = $document->getDoCompress() ? '/Filter /FlateDecode ' : '';
        $this->_loopOutPages($nb, $filter, $hPt);

        $this->setPdfObjectOffset(1, strlen($this->outBuffer));
        $this->out('1 0 obj');
        $this->out('<</Type /Pages');
        $kids = '/Kids [';

        for ($i = 0; $i < $nb; $i++) {
            $kids .= ( 3 + 2 * $i).' 0 R ';
        }
        $this->out($kids.']');
        $this->out('/Count '.$nb);
        $this->out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->out('>>');
        $this->out('endobj');
    }

    /**
     * Loop to output each page
     *
     * @param $nb     int     Number of pages
     * @param $filter string  Filter
     * @param $hPt    float   Height
     */
    protected function _loopOutPages($nb, $filter, $hPt)
    {
        $document = $this->_pdfDocument;

        for ($n = 1; $n <= $nb; $n++) {
            $this->newObj();
            $this->out('<</Type /Page');
            $this->out('/Parent 1 0 R');

            if ($pageSize = $document->getPageSize($n)) {
                $this->out(sprintf('/MediaBox [0 0 %.2F %.2F]', $pageSize[0], $pageSize[1]));
            }

            $this->out('/Resources 2 0 R');

            if (!empty($document->getPage($n)->pageLinks)) {
                $annots = '/Annots [';

                foreach ($document->getPage($n)->pageLinks as $pl) {
                    $rect    = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';

                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI '.$this->textstring($pl[4]).'>>>>';
                    } else {
                        $l        = $document->internalLinks[$pl[4]];
                        $pageSize = $document->getPageSize($l[0]);
                        $h        = !empty($pageSize) ? $pageSize[1] : $hPt;
                        $annots  .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $l[0], $h - $l[1] * $document->getScaleFactor());
                    }
                }
                $this->out($annots.']');
            }

            if ($document->pdfVersion > '1.3') {
                $this->out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->out('/Contents '.($this->getPdfObjects() + 1).' 0 R>>');
            $this->out('endobj');

            $p = $document->getDoCompress() ? gzcompress($document->getPage($n)->pageBuffer) : $document->getPage($n)->pageBuffer;
            $this->newObj();
            $this->out('<<'.$filter.'/Length '.strlen($p).'>>');
            $this->putStream($p);
            $this->out('endobj');
        }
    }

    /**
     * Output cross-references
     */
    protected function _putXRef()
    {
        $this->out('xref');
        $this->out('0 '.($this->getPdfObjects() + 1));
        $this->out('0000000000 65535 f ');

        for ($i = 1; $i <= $this->getPdfObjects(); $i++) {
            $this->out(sprintf('%010d 00000 n ', $this->getPdfObjectOffset($i)));
        }
    }

    /**
     * Output PDF header
     */
    protected function _putHeader()
    {
        $this->out('%PDF-'.$this->_pdfDocument->pdfVersion);
    }

    /**
     * Output PDF trailer
     *
     * @param $o
     */
    protected function _putTrailer($o)
    {
        $this->out('trailer');
        $this->out('<<');
        $this->out('/Size '.($this->getPdfObjects() + 1));
        $this->out('/Root '.$this->getPdfObjects().' 0 R');
        $this->out('/Info '.($this->getPdfObjects() - 1).' 0 R');
        $this->out('>>');
        $this->out('startxref');
        $this->out($o);
        $this->out('%%EOF');
    }

    /**
     * Output document presentation information
     */
    protected function _putCatalog()
    {
        $this->newObj();
        $this->out('<<');
        $this->out('/Type /Catalog');

        // TODO: check supporting Print scaling off
        //   $this->out('/ViewerPreferences [/PrintScaling/None]');
        // OR
        //  $this->out('/ViewerPreferences<</Duplex/Simplex/Enforce[/PrintScaling]/PrintScaling/None>>');

        $this->out('/Pages 1 0 R');

        if ($this->_pdfDocument->getZoomMode() == 'fullpage') {
            $this->out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->_pdfDocument->getZoomMode() == 'fullwidth') {
            $this->out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->_pdfDocument->getZoomMode() == 'real') {
            $this->out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->_pdfDocument->getZoomMode())) {
            $this->out('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F', $this->_pdfDocument->getZoomMode() / 100).']');
        }

        if ($this->_pdfDocument->getLayoutMode() == 'single') {
            $this->out('/PageLayout /SinglePage');
        } elseif ($this->_pdfDocument->getLayoutMode() == 'continuous') {
            $this->out('/PageLayout /OneColumn');
        } elseif ($this->_pdfDocument->getLayoutMode() == 'two') {
            $this->out('/PageLayout /TwoColumnLeft');
        }

        $this->out('>>');
        $this->out('endobj');
    }

    /**
     * Output PDF document meta-information
     */
    protected function _putInfo()
    {
        $this->newObj();
        $this->out('<<');

        $this->out('/Producer '.$this->textstring('PdfBuilder '.PDFBUILDER_VERSION));

        $title    = $this->_pdfDocument->getTitle();
        $subject  = $this->_pdfDocument->getSubject();
        $author   = $this->_pdfDocument->getAuthor();
        $keywords = $this->_pdfDocument->getKeywords();
        $creator  = $this->_pdfDocument->getCreator();

        if (!empty($title)) {
            $this->out('/Title '.$this->textstring($title));
        }
        if (!empty($subject)) {
            $this->out('/Subject '.$this->textstring($subject));
        }
        if (!empty($author)) {
            $this->out('/Author '.$this->textstring($author));
        }
        if (!empty($keywords)) {
            $this->out('/Keywords '.$this->textstring($keywords));
        }
        if (!empty($creator)) {
            $this->out('/Creator '.$this->textstring($creator));
        }
        $this->out('/CreationDate '.$this->textstring('D:'.@date('YmdHis')));

        $this->out('>>');
        $this->out('endobj');
    }

    /**
     * Utf8 to Utf16
     *
     * @param  $str
     * @param  bool $setBom
     * @return string
     */
    public function UTF8ToUTF16BE($str, $setBom = true)
    {
        if ($setBom) {
            return "\xFE\xFF".mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
        }
        return mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
    }

    /**
     * Convert UTF-8 to UTF-16BE with BOM
     *    the if's check for 3, 2 and single byte chars.
     *
     * @param  $s
     * @return string
     */
    function _UTF8toUTF16($s)
    {
        $res = "\xFE\xFF";
        $nb  = strlen($s);
        $i  = 0;

        while ($i < $nb) {
            $c1 = ord($s[$i++]);

            if ($c1 >= 224) {
                $c2   = ord($s[$i++]);
                $c3   = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C)>>2));
                $res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));
            } elseif ($c1 >= 192) {
                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C) >> 2);
                $res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));
            } else {
                $res .= "\0".chr($c1);
            }
        }
        return $res;
    }

    /**
     * Converts UTF-8 strings to codepoints array
     *
     * @param  $str
     * @return array
     */
    public function UTF8StringToArray($str)
    {
        $out = array();
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $uni = -1;
            $h   = ord($str[$i]);
            if ($h <= 0x7F) {
                $uni = $h;
            } elseif ( $h >= 0xC2 ) {
                if (($h <= 0xDF) && ($i < $len -1)) {
                    $uni = ($h & 0x1F) << 6 | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xEF) && ($i < $len -2)) {
                    $uni = ($h & 0x0F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xF4) && ($i < $len -3)) {
                    $uni = ($h & 0x0F) << 18 | (ord($str[++$i]) & 0x3F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
                }
            }
            if ($uni >= 0) {
                $out[] = $uni;
            }
        }
        return $out;
    }

    /**
     * Format a text string
     *
     * @param  $s
     * @return string
     */
    public function textstring($s)
    {
        return '('.$this->escape($s).')';
    }

    /**
     * Escape special characters in strings
     *
     * @param  $s
     * @return string
     */
    public function escape($s)
    {
        $s = str_replace('\\','\\\\',$s);
        $s = str_replace('(','\\(',$s);
        $s = str_replace(')','\\)',$s);
        $s = str_replace("\r",'\\r',$s);
        return $s;
    }
}
