<?php
namespace PdfBuilder\Plugins;

use PdfBuilder\PdfDocument;
use PdfBuilder\Exception\PdfException;

class PdfText {

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @var array
     */
    protected $_coreFonts = array(
        'courier',
        'helvetica',
        'times',
        'symbol',
        'zapfdingbats'
    );

    /**
     * @var int Word spacing
     */
    protected $_ws = 0;

    /**
     * @var int Last cell height
     */
    protected $_lastH = 0;

    /**
     * Constructor
     *
     * @param PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument) {
        $this->_pdfDocument = $pdfDocument;

        if ($fontpath = $pdfDocument->getFontPath()) {
            if (substr($fontpath, -1) != '/' && substr($fontpath, -1) != '\\') {
                $pdfDocument->setFontPath($fontpath.'/');
            }
        } elseif (is_dir(dirname(__FILE__).'/Fonts')) {
            $pdfDocument->setFontPath(dirname(__FILE__).'/Fonts/');
        }
    }

    /**
     * Print out text to the PDF
     *
     * @param $x
     * @param $y
     * @param $txt
     */
    public function text($x, $y, $txt)
    {
        $document  = $this->_pdfDocument;
        $pdfOutput = $document->getOutputter();


        if ($this->_pdfDocument->getUnifontSubset()) {
            $txt2 = '('.$pdfOutput->escape($pdfOutput->UTF8ToUTF16BE($txt, false)).')';
            $fontOutputter = $pdfOutput->getFontOutputter();
            $currentFont   = &$fontOutputter->fonts[$document->getCurrentFont()];

            foreach ($pdfOutput->UTF8StringToArray($txt) as $uni) {
                $currentFont['subset'][$uni] = $uni;
            }
        } else {
            $txt2 = '('.$pdfOutput->escape($txt).')';
        }
        $scaleFactor = $document->getScaleFactor();
        $height      = $document->getPage()->getHeight();
        $s = sprintf('BT %.2F %.2F Td %s Tj ET', $x * $scaleFactor,($height - $y) * $scaleFactor, $txt2);

        if ($document->getUnderline() && $txt != '') {
            $s .= ' '.$this->_dounderline($x, $y, $txt);
        }
        if ($document->getColorFlag()) {
            $s = 'q '.$document->getTextColor().' '.$s.' Q';
        }
        $pdfOutput->out($s);
    }

    /**
     * Set color for text,
     * overrides magic setter in document
     *
     * @param $r
     * @param null $g
     * @param null $b
     */
    public function setTextColor($r, $g = null, $b = null)
    {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->_pdfDocument->__set('TextColor', sprintf('%.3F g', $r / 255));
        } else {
            $this->_pdfDocument->__set('TextColor', sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255));
        }
        $this->_pdfDocument->setColorFlag(($this->_pdfDocument->getFillColor() != $this->_pdfDocument->getTextColor()));
    }

    /**
     * Get width of a string in the current font
     *
     * @param $s
     * @return float
     */
    public function getStringWidth($s)
    {
        $document     = $this->_pdfDocument;
        $output       = $document->getOutputter();
        $selectedFont = $document->getCurrentFont();
        $currentFont  = &$output->getFontOutputter()->fonts[$selectedFont];

        $s  = (string)$s;
        $cw = $currentFont['cw'];
        $w  = 0;

        if ($document->getUnifontSubset()) {
            $unicode = $output->UTF8StringToArray($s);

            foreach ($unicode as $char) {
                if (isset($cw[$char])) {
                    $w += (ord($cw[2 * $char]) << 8) + ord($cw[2 * $char + 1]);
                } else if ($char > 0 && $char < 128 && isset($cw[chr($char)])) {
                    $w += $cw[chr($char)];
                } else if (isset($currentFont['desc']['MissingWidth'])) {
                    $w += $currentFont['desc']['MissingWidth'];
                } else if (isset($currentFont['MissingWidth'])) {
                    $w += $currentFont['MissingWidth'];
                } else {
                    $w += 500;
                }
            }
        } else {
            $l = strlen($s);
            for ($i = 0; $i < $l; $i++) {
                $w += $cw[$s[$i]];
            }
        }
        return $w * $document->getFontSize() / 1000;
    }

    /**
     * Output a cell
     *
     * @param $w
     * @param int $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    function cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $document = $this->_pdfDocument;
        $output   = $document->getOutputter();
        $page     = $document->getPage();
        $k        = $document->getScaleFactor();

        $selectedFont = $document->getCurrentFont();
        $currentFont  = &$output->getFontOutputter()->fonts[$selectedFont];

        // Automatic page break
        if ($page->getY() + $h > $page->getPageBreakTrigger() && !$document->getInHeaderOrFooter() && $page->acceptPageBreak()) {
            $x  = $page->getX();
            $ws = $this->_ws;
            if ($ws > 0) {
                $this->_ws = 0;
                $document->out('0 Tw');
            }
            $page = $document->addPage($document->getPage()->getOrientation(), $document->getPage()->getCurPageSize());
            $page->setX($x);
            if ($ws > 0) {
                $this->_ws = $ws;
                $document->out(sprintf('%.3F Tw',$ws*$k));
            }
        }

        if ($w == 0) {
            $w = $page->getWidth() - $page->getRightMargin() - $document->getScaleFactor();
        }
        $s = '';

        if ($fill || $border == 1) {
            if ($fill) {
                $op = ($border==1) ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$page->getX() * $k, ($page->getHeight() - $page->getY()) * $k , $w * $k, -$h * $k, $op);
        }

        if (is_string($border)) {
            $x = $page->getX();
            $y = $page->getY();
            if (strpos($border,'L') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x * $k, ($page->getHeight() - $y) * $k, $x * $k, ($page->getHeight()- ($y + $h)) * $k);
            }
            if (strpos($border,'T') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x * $k,($page->getHeight() - $y) * $k, ($x + $w) * $k,($page->getHeight() - $y) * $k);
            }
            if (strpos($border,'R') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k,($page->getHeight() - $y) * $k, ($x + $w) * $k,($page->getHeight() - ($y + $h)) * $k);
            }
            if (strpos($border,'B') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k,($page->getHeight() - ($y+$h)) * $k, ($x+$w) * $k, ($page->getHeight()-($y+$h)) * $k);
            }
        }

        if ($txt !== '') {
            if ($align == 'R') {
                $dx = $w - $page->getCellMargin() - $this->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->getStringWidth($txt)) / 2;
            } else {
                $dx = $page->getCellMargin();
            }

            if ($document->getColorFlag()) {
                $s .= 'q '.$document->getTextColor().' ';
            }

            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->_ws && $document->getUnifontSubset()) {

                foreach ($output->UTF8StringToArray($txt) as $uni) {
                    $currentFont['subset'][$uni] = $uni;
                }
                $space = $output->escape($output->UTF8ToUTF16BE(' ', false));
                $s .= sprintf('BT 0 Tw %.2F %.2F Td [',
                    ($page->getX() + $dx) * $k,
                    ($page->getHeight() - ($page->getY() + .5 * $h + .3 * $document->getFontSize())) * $k);
                $t    = explode(' ',$txt);
                $numt = count($t);

                for ($i =0; $i < $numt; $i++) {
                    $tx = $t[$i];
                    $tx = '('.$output->escape($output->UTF8ToUTF16BE($tx, false)).')';
                    $s .= sprintf('%s ',$tx);
                    if (($i + 1) < $numt) {
                        $adj = -($this->_ws * $k) * 1000 / $document->getFontSizePt();
                        $s  .= sprintf('%d(%s) ', $adj, $space);
                    }
                }
                $s .= '] TJ';
                $s .= ' ET';
            } else {
                if ($document->getUnifontSubset()) {
                    $txt2 = '('.$output->escape($output->UTF8ToUTF16BE($txt, false)).')';
                    foreach ($output->UTF8StringToArray($txt) as $uni) {
                        $currentFont['subset'][$uni] = $uni;
                    }
                } else {
                    $txt2 = '('.str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$txt))).')';
                }
                $s .= sprintf('BT %.2F %.2F Td %s Tj ET',
                    ($page->getX() + $dx) * $k,
                    ($page->getHeight() - ($page->getY() + .5 * $h + .3 * $document->getFontSize())) * $k,
                    $txt2);
            }
            if ($document->getUnderline()) {
                $s .= ' '.$this->_dounderline($page->getX() + $dx, $page->getY() + .5 * $h + .3 * $document->getFontSize(), $txt);
            }
            if ($document->getColorFlag()) {
                $s .= ' Q';
            }
            if ($link) {
                $page->link($page->getX() + $dx,
                    $page->getY() + .5 * $h - .5 * $document->getFontSize(),
                    $this->getStringWidth($txt),
                    $document->getFontSize(),
                    $link);
            }
        }

        if ($s) {
            $document->out($s);
        }
        $this->_lastH = $h;
        if ($ln > 0) {
            $page->setY($page->getY() + $h);
            if ($ln == 1) {
                $page->setX($page->getLeftMargin());
            }
        } else {
            $page->setX($page->getX() + $w);
        }
    }

    /**
     * Output text with automatic or explicit line breaks
     *
     * @param $w
     * @param $h
     * @param $txt
     * @param int $border
     * @param string $align
     * @param bool $fill
     */
    function multiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
    {
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin);
        $s = str_replace("\r",'',$txt);
        if ($this->unifontSubset) {
            $nb=mb_strlen($s, 'utf-8');
            while($nb>0 && mb_substr($s,$nb-1,1,'utf-8')=="\n")	$nb--;
        }
        else {
            $nb = strlen($s);
            if($nb>0 && $s[$nb-1]=="\n")
                $nb--;
        }
        $b = 0;
        if($border)
        {
            if($border==1)
            {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            }
            else
            {
                $b2 = '';
                if(strpos($border,'L')!==false)
                    $b2 .= 'L';
                if(strpos($border,'R')!==false)
                    $b2 .= 'R';
                $b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while($i<$nb)
        {
            // Get next character
            if ($this->unifontSubset) {
                $c = mb_substr($s,$i,1,'UTF-8');
            }
            else {
                $c=$s[$i];
            }
            if($c=="\n")
            {
                // Explicit line break
                if($this->ws>0)
                {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                if ($this->unifontSubset) {
                    $this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
                }
                else {
                    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                }
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if($border && $nl==2)
                    $b = $b2;
                continue;
            }
            if($c==' ')
            {
                $sep = $i;
                $ls = $l;
                $ns++;
            }

            if ($this->unifontSubset) { $l += $this->GetStringWidth($c); }
            else { $l += $cw[$c]*$this->FontSize/1000; }

            if($l>$wmax)
            {
                // Automatic line break
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                    if($this->ws>0)
                    {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }
                    if ($this->unifontSubset) {
                        $this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
                    }
                    else {
                        $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                    }
                }
                else
                {
                    if($align=='J')
                    {
                        $this->ws = ($ns>1) ? ($wmax-$ls)/($ns-1) : 0;
                        $this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
                    }
                    if ($this->unifontSubset) {
                        $this->Cell($w,$h,mb_substr($s,$j,$sep-$j,'UTF-8'),$b,2,$align,$fill);
                    }
                    else {
                        $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                    }
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if($border && $nl==2)
                    $b = $b2;
            }
            else
                $i++;
        }
        // Last chunk
        if($this->ws>0)
        {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        if($border && strpos($border,'B')!==false)
            $b .= 'B';
        if ($this->unifontSubset) {
            $this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),$b,2,$align,$fill);
        }
        else {
            $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
        }
        $this->x = $this->lMargin;
    }

    function Write($h, $txt, $link='')
    {
        // Output text in flowing mode
        $cw = &$this->CurrentFont['cw'];
        $w = $this->w-$this->rMargin-$this->x;

        $wmax = ($w-2*$this->cMargin);
        $s = str_replace("\r",'',$txt);
        if ($this->unifontSubset) {
            $nb = mb_strlen($s, 'UTF-8');
            if($nb==1 && $s==" ") {
                $this->x += $this->GetStringWidth($s);
                return;
            }
        }
        else {
            $nb = strlen($s);
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb)
        {
            // Get next character
            if ($this->unifontSubset) {
                $c = mb_substr($s,$i,1,'UTF-8');
            }
            else {
                $c = $s[$i];
            }
            if($c=="\n")
            {
                // Explicit line break
                if ($this->unifontSubset) {
                    $this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,2,'',0,$link);
                }
                else {
                    $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',0,$link);
                }
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                if($nl==1)
                {
                    $this->x = $this->lMargin;
                    $w = $this->w-$this->rMargin-$this->x;
                    $wmax = ($w-2*$this->cMargin);
                }
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;

            if ($this->unifontSubset) { $l += $this->GetStringWidth($c); }
            else { $l += $cw[$c]*$this->FontSize/1000; }

            if($l>$wmax)
            {
                // Automatic line break
                if($sep==-1)
                {
                    if($this->x>$this->lMargin)
                    {
                        // Move to next line
                        $this->x = $this->lMargin;
                        $this->y += $h;
                        $w = $this->w-$this->rMargin-$this->x;
                        $wmax = ($w-2*$this->cMargin);
                        $i++;
                        $nl++;
                        continue;
                    }
                    if($i==$j)
                        $i++;
                    if ($this->unifontSubset) {
                        $this->Cell($w,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,2,'',0,$link);
                    }
                    else {
                        $this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',0,$link);
                    }
                }
                else
                {
                    if ($this->unifontSubset) {
                        $this->Cell($w,$h,mb_substr($s,$j,$sep-$j,'UTF-8'),0,2,'',0,$link);
                    }
                    else {
                        $this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',0,$link);
                    }
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                if($nl==1)
                {
                    $this->x = $this->lMargin;
                    $w = $this->w-$this->rMargin-$this->x;
                    $wmax = ($w-2*$this->cMargin);
                }
                $nl++;
            }
            else
                $i++;
        }
        // Last chunk
        if($i!=$j) {
            if ($this->unifontSubset) {
                $this->Cell($l,$h,mb_substr($s,$j,$i-$j,'UTF-8'),0,0,'',0,$link);
            }
            else {
                $this->Cell($l,$h,substr($s,$j),0,0,'',0,$link);
            }
        }
    }

    /**
     * Line feed; default value is last cell height
     *
     * @param null $h
     */
    function ln($h=null)
    {
        $this->x = $this->lMargin;

        if ($h === null) {
            $this->y += $this->lasth;
        } else {
            $this->y += $h;
        }
    }

    /**
     * Underline text
     *
     * @param $x
     * @param $y
     * @param $txt
     * @return string
     */
    protected function _dounderline($x, $y, $txt)
    {
        $fontOutput = $this->_pdfDocument->getOutputter()->getFontOutputter();

        $up = $fontOutput->fonts[$this->_pdfDocument->getCurrentFont()]['up'];
        $ut = $fontOutput->fonts[$this->_pdfDocument->getCurrentFont()]['ut'];
        $w  = $this->getStringWidth($txt) + $this->_ws * substr_count($txt,' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
    }

    /**
     * Select a font; size given in points
     *
     * @param  $family
     * @param  string $style
     * @param  int $size
     * @return \PdfBuilder\PdfDocument
     * @throws PdfException
     */
    public function setFont($family, $style = '', $size = 0)
    {
        $document   = $this->_pdfDocument;
        $fontOutput = $document->getOutputter()->getFontOutputter();

        if ($family == '') {
            $family = $document->getFontFamily();
        } else {
            $family = strtolower($family);
        }

        $style = strtoupper($style);

        if (strpos($style, 'U') !== false) {
            $document->setUnderline(true);
            $style = str_replace('U', '', $style);
        } else {
            $document->setUnderline(false);
        }

        if ($style == 'IB') {
            $style = 'BI';
        }
        if ($size == 0) {
            $size = $document->getFontSizePt();
        }

        if ($document->getFontFamily() == $family && $document->getFontStyle() == $style && $document->getFontSizePt() == $size) {
            return;
        }

        $fontkey = $family.$style;

        if (!isset($fontOutput->fonts[$fontkey])) {
            if ($family == 'arial') {
                $family = 'helvetica';
            }

            if (in_array($family, $this->_coreFonts)) {
                if ($family == 'symbol' || $family == 'zapfdingbats') {
                    $style = '';
                }
                $fontkey = $family.$style;

                if (!isset($fontOutput->fonts[$fontkey])) {
                    $fontOutput->addFont($family, $style);
                }
            } else {
                throw new PdfException("Undefined font: ".$family." ".$style);
            }
        }
        $document->setFontFamily($family);
        $document->setFontStyle($style);
        $document->setFontSizePt($size);
        $document->setFontSize($size / $document->getScaleFactor());
        $document->setCurrentFont($fontkey);

        if ($fontOutput->fonts[$fontkey]['type'] == 'TTF') {
            $document->setUnifontSubset(true);
        } else {
            $document->setUnifontSubset(false);
        }

        if ($document->getCurPageNo() > 0) {
            $document->out(sprintf('BT /F%d %.2F Tf ET', $fontOutput->fonts[$fontkey]['i'], $document->getFontSizePt()));
        }

        return $document;
    }

    /**
     * Set font size in points
     *
     * @param $size
     */
    public function setFontSize($size)
    {
        $document   = $this->_pdfDocument;
        $fontOutput = $document->getOutputter()->getFontOutputter();

        if ($document->getFontSizePt() == $size) {
            return;
        }
        $document->setFontSizePt($size);
        $document->setFontSize($size / $document->getScaleFactor());
        $currentFont = $document->getCurrentFont();

        if ($document->getCurPageNo() > 0) {
            $document->out(sprintf('BT /F%d %.2F Tf ET', $fontOutput->fonts[$currentFont]['i'], $document->getFontSizePt()));
        }
    }

}