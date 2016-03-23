<?php
namespace bubach\PdfBuilder\Core;

use bubach\PdfBuilder\PdfDocument;

class PdfOutput {

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @var string PDF output buffer
     */
    public $outBuffer = '';

    /**
     * Construct output instance
     *
     * @param  PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument)
    {
        $this->_pdfDocument = $pdfDocument;
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
     * Output stream
     *
     * @param $s
     */
    function _putstream($s)
    {
        $this->_pdfDocument->_out('stream');
        $this->_pdfDocument->_out($s);
        $this->_pdfDocument->_out('endstream');
    }

    /**
     * Output x object dict
     */
    protected function _putXObjectDict()
    {
        $images = array(); // TODO: placeholder for $this->images

        foreach ($images as $image) {
            $this->_pdfDocument->_out('/I'.$image['i'].' '.$image['n'].' 0 R');
        }
    }

    /**
     * Output resource dict
     */
    protected function _putResourceDict()
    {
        $this->_pdfDocument->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_pdfDocument->_out('/Font <<');

        $fonts = array();// TODO: placeholder for $this->fonts

        foreach ($fonts as $font) {
            $this->_pdfDocument->_out('/F'.$font['i'].' '.$font['n'].' 0 R');
        }

        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('/XObject <<');
        $this->_putXObjectDict();
        $this->_pdfDocument->_out('>>');
    }

    /**
     * Output Pdf resources
     */
    protected function _putResources()
    {
        //$this->_putfonts();
        //$this->_putimages();

        $this->_pdfDocument->setPdfObjectOffset(2, strlen($this->outBuffer));
        $this->_pdfDocument->_out('2 0 obj');
        $this->_pdfDocument->_out('<<');
        $this->_putResourceDict();
        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('endobj');
    }

    /**
     * Output page data
     */
    protected function _putPages()
    {
        $nb = $this->_pdfDocument->getCurPageNo();

        if (!empty($this->_aliasNbPages)) {
            $alias = $this->UTF8ToUTF16BE($this->_aliasNbPages, false);
            $r     = $this->UTF8ToUTF16BE("$nb", false);

            for ($n = 1; $n <= $nb; $n++) {
                $this->_pdfDocument->getPage($n)->pageBuffer = str_replace($alias, $r, $this->_pdfDocument->getPage($n)->pageBuffer);
            }
            for ($n = 1; $n <= $nb; $n++) {
                $this->_pdfDocument->getPage($n)->pageBuffer = str_replace($this->_aliasNbPages, $nb, $this->_pdfDocument->getPage($n)->pageBuffer);
            }
        }

        $defPageSize = $this->_pdfDocument->getDefPageSize();
        if ($this->_pdfDocument->getDefOrientation() == 'P') {
            $wPt = $defPageSize[0] * $this->_pdfDocument->getScaleFactor();
            $hPt = $defPageSize[1] * $this->_pdfDocument->getScaleFactor();
        } else {
            $wPt = $defPageSize[1] * $this->_pdfDocument->getScaleFactor();
            $hPt = $defPageSize[0] * $this->_pdfDocument->getScaleFactor();
        }

        $filter = empty($this->_compress) ? '/Filter /FlateDecode ' : '';

        for ($n = 1; $n <= $nb; $n++) {
            $this->_pdfDocument->_newobj();
            $this->_pdfDocument->_out('<</Type /Page');
            $this->_pdfDocument->_out('/Parent 1 0 R');

            if ($pageSize = $this->_pdfDocument->getPageSize($n)) {
                $this->_pdfDocument->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $pageSize[0], $pageSize[1]));
            }

            $this->_pdfDocument->_out('/Resources 2 0 R');

            if (!empty($this->_pdfDocument->getPage($n)->pageLinks)) {
                $annots = '/Annots [';

                foreach($this->_pdfDocument->getPage($n)->pageLinks as $pl) {
                    $rect    = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';

                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
                    } else {
                        $l        = $this->_pdfDocument->internalLinks[$pl[4]];
                        $pageSize = $this->_pdfDocument->getPageSize($l[0]);
                        $h        = !empty($pageSize) ? $pageSize[1] : $hPt;
                        $annots  .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $l[0], $h - $l[1] * $this->_pdfDocument->getScaleFactor());
                    }
                }
                $this->_pdfDocument->_out($annots.']');
            }

            if ($this->_pdfDocument->pdfVersion > '1.3') {
                $this->_pdfDocument->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->_pdfDocument->_out('/Contents '.($this->_pdfDocument->getPdfObjects() + 1).' 0 R>>');
            $this->_pdfDocument->_out('endobj');

            $p = empty($this->_compress) ? gzcompress($this->_pdfDocument->getPage($n)->pageBuffer) : $this->_pdfDocument->getPage($n)->pageBuffer;
            $this->_pdfDocument->_newobj();
            $this->_pdfDocument->_out('<<'.$filter.'/Length '.strlen($p).'>>');
            $this->_putstream($p);
            $this->_pdfDocument->_out('endobj');
        }

        $this->_pdfDocument->setPdfObjectOffset(1, strlen($this->outBuffer));
        $this->_pdfDocument->_out('1 0 obj');
        $this->_pdfDocument->_out('<</Type /Pages');
        $kids = '/Kids [';

        for ($i = 0; $i < $nb; $i++) {
            $kids .= ( 3 + 2 * $i).' 0 R ';
        }
        $this->_pdfDocument->_out($kids.']');
        $this->_pdfDocument->_out('/Count '.$nb);
        $this->_pdfDocument->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('endobj');
    }

    /**
     * Output cross-references
     */
    protected function _putXRef()
    {
        $this->_pdfDocument->_out('xref');
        $this->_pdfDocument->_out('0 '.($this->_pdfDocument->getPdfObjects() + 1));
        $this->_pdfDocument->_out('0000000000 65535 f ');

        for ($i = 1; $i <= $this->_pdfDocument->getPdfObjects(); $i++) {
            $this->_pdfDocument->_out(sprintf('%010d 00000 n ', $this->_pdfDocument->getPdfObjectOffset($i)));
        }
    }

    /**
     * Output PDF header
     */
    protected function _putHeader()
    {
        $this->_pdfDocument->_out('%PDF-'.$this->_pdfDocument->pdfVersion);
    }

    /**
     * Output PDF trailer
     *
     * @param $o
     */
    protected function _putTrailer($o)
    {
        $this->_pdfDocument->_out('trailer');
        $this->_pdfDocument->_out('<<');
        $this->_pdfDocument->_out('/Size '.($this->_pdfDocument->getPdfObjects() + 1));
        $this->_pdfDocument->_out('/Root '.$this->_pdfDocument->getPdfObjects().' 0 R');
        $this->_pdfDocument->_out('/Info '.($this->_pdfDocument->getPdfObjects() - 1).' 0 R');
        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('startxref');
        $this->_pdfDocument->_out($o);
        $this->_pdfDocument->_out('%%EOF');
    }

    /**
     * Output document presentation information
     */
    protected function _putCatalog()
    {
        $this->_pdfDocument->_newobj();
        $this->_pdfDocument->_out('<<');
        $this->_pdfDocument->_out('/Type /Catalog');
        $this->_pdfDocument->_out('/Pages 1 0 R');

        if ($this->_pdfDocument->getZoomMode() == 'fullpage') {
            $this->_pdfDocument->_out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->_pdfDocument->getZoomMode() == 'fullwidth') {
            $this->_pdfDocument->_out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->_pdfDocument->getZoomMode() == 'real') {
            $this->_pdfDocument->_out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->_pdfDocument->getZoomMode())) {
            $this->_pdfDocument->_out('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F', $this->_pdfDocument->getZoomMode() / 100).']');
        }

        if ($this->_pdfDocument->getLayoutMode() == 'single') {
            $this->_pdfDocument->_out('/PageLayout /SinglePage');
        } elseif ($this->_pdfDocument->getLayoutMode() == 'continuous') {
            $this->_pdfDocument->_out('/PageLayout /OneColumn');
        } elseif ($this->_pdfDocument->getLayoutMode() == 'two') {
            $this->_pdfDocument->_out('/PageLayout /TwoColumnLeft');
        }

        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('endobj');
    }

    /**
     * Output PDF document meta-information
     */
    protected function _putInfo()
    {
        $this->_pdfDocument->_newobj();
        $this->_pdfDocument->_out('<<');

        $this->_pdfDocument->_out('/Producer '.$this->_textstring('PdfBuilder '.PDFBUILDER_VERSION));
        if (!empty($this->title)) {
            $this->_pdfDocument->_out('/Title '.$this->_textstring($this->title));
        }
        if (!empty($this->subject)) {
            $this->_pdfDocument->_out('/Subject '.$this->_textstring($this->subject));
        }
        if (!empty($this->author)) {
            $this->_pdfDocument->_out('/Author '.$this->_textstring($this->author));
        }
        if (!empty($this->keywords)) {
            $this->_pdfDocument->_out('/Keywords '.$this->_textstring($this->keywords));
        }
        if (!empty($this->creator)) {
            $this->_pdfDocument->_out('/Creator '.$this->_textstring($this->creator));
        }
        $this->_pdfDocument->_out('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));

        $this->_pdfDocument->_out('>>');
        $this->_pdfDocument->_out('endobj');
    }

    /**
     * Format a text string
     *
     * @param  $s
     * @return string
     */
    protected function _textstring($s)
    {
        return '('.$this->_escape($s).')';
    }

    /**
     * Escape special characters in strings
     *
     * @param  $s
     * @return string
     */
    protected function _escape($s)
    {
        $s = str_replace('\\','\\\\',$s);
        $s = str_replace('(','\\(',$s);
        $s = str_replace(')','\\)',$s);
        $s = str_replace("\r",'\\r',$s);
        return $s;
    }

} 