<?php
namespace PdfBuilder\Plugins;

use PdfBuilder\PdfDocument;

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
     * Constructor
     *
     * @param PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument) {
        $this->_pdfBuilder = $pdfDocument;

        if ($fontpath = $pdfDocument->getFontPath()) {
            if (substr($fontpath, -1) != '/' && substr($fontpath, -1) != '\\') {
                $pdfDocument->setFontPath($fontpath.'/');
            }
        } elseif (is_dir(dirname(__FILE__).'/fonts')) {
            $pdfDocument->setFontPath(dirname(__FILE__).'/fonts/');
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
        $pdfOutput = $this->_pdfDocument->getOutputter();

        if ($this->_pdfDocument->getUnifontSubset()) {
            $txt2 = '('.$pdfOutput->escape($pdfOutput->UTF8ToUTF16BE($txt, false)).')';
            foreach($pdfOutput->UTF8StringToArray($txt) as $uni) {
                $this->CurrentFont['subset'][$uni] = $uni;
            }
        } else {
            $txt2 = '('.$pdfOutput->escape($txt).')';
        }
        $s = sprintf('BT %.2F %.2F Td %s Tj ET',$x*$this->k,($this->h-$y)*$this->k,$txt2);

        if ($this->underline && $txt!='') {
            $s .= ' '.$this->_dounderline($x,$y,$txt);
        }
        if ($this->ColorFlag) {
            $s = 'q '.$this->TextColor.' '.$s.' Q';
        }
        $this->_out($s);
    }

    function SetTextColor($r, $g=null, $b=null)
    {
        // Set color for text
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->TextColor = sprintf('%.3F g',$r/255);
        else
            $this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        $this->ColorFlag = ($this->FillColor!=$this->TextColor);
    }

    function GetStringWidth($s)
    {
        // Get width of a string in the current font
        $s = (string)$s;
        $cw = &$this->CurrentFont['cw'];
        $w=0;
        if ($this->unifontSubset) {
            $unicode = $this->UTF8StringToArray($s);
            foreach($unicode as $char) {
                if (isset($cw[$char])) { $w += (ord($cw[2*$char])<<8) + ord($cw[2*$char+1]); }
                else if($char>0 && $char<128 && isset($cw[chr($char)])) { $w += $cw[chr($char)]; }
                else if(isset($this->CurrentFont['desc']['MissingWidth'])) { $w += $this->CurrentFont['desc']['MissingWidth']; }
                else if(isset($this->CurrentFont['MissingWidth'])) { $w += $this->CurrentFont['MissingWidth']; }
                else { $w += 500; }
            }
        }
        else {
            $l = strlen($s);
            for($i=0;$i<$l;$i++)
                $w += $cw[$s[$i]];
        }
        return $w*$this->FontSize/1000;
    }


    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        // Output a cell
        $k = $this->k;
        if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
        {
            // Automatic page break
            $x = $this->x;
            $ws = $this->ws;
            if($ws>0)
            {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation,$this->CurPageSize);
            $this->x = $x;
            if($ws>0)
            {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw',$ws*$k));
            }
        }
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $s = '';
        if($fill || $border==1)
        {
            if($fill)
                $op = ($border==1) ? 'B' : 'f';
            else
                $op = 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
        }
        if(is_string($border))
        {
            $x = $this->x;
            $y = $this->y;
            if(strpos($border,'L')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'T')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
            if(strpos($border,'R')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'B')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        }
        if($txt!=='')
        {
            if($align=='R')
                $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
            elseif($align=='C')
                $dx = ($w-$this->GetStringWidth($txt))/2;
            else
                $dx = $this->cMargin;
            if($this->ColorFlag)
                $s .= 'q '.$this->TextColor.' ';

            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->ws && $this->unifontSubset) {
                foreach($this->UTF8StringToArray($txt) as $uni)
                    $this->CurrentFont['subset'][$uni] = $uni;
                $space = $this->_escape($this->UTF8ToUTF16BE(' ', false));
                $s .= sprintf('BT 0 Tw %.2F %.2F Td [',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k);
                $t = explode(' ',$txt);
                $numt = count($t);
                for($i=0;$i<$numt;$i++) {
                    $tx = $t[$i];
                    $tx = '('.$this->_escape($this->UTF8ToUTF16BE($tx, false)).')';
                    $s .= sprintf('%s ',$tx);
                    if (($i+1)<$numt) {
                        $adj = -($this->ws*$this->k)*1000/$this->FontSizePt;
                        $s .= sprintf('%d(%s) ',$adj,$space);
                    }
                }
                $s .= '] TJ';
                $s .= ' ET';
            }
            else {
                if ($this->unifontSubset)
                {
                    $txt2 = '('.$this->_escape($this->UTF8ToUTF16BE($txt, false)).')';
                    foreach($this->UTF8StringToArray($txt) as $uni)
                        $this->CurrentFont['subset'][$uni] = $uni;
                }
                else
                    $txt2='('.str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$txt))).')';
                $s .= sprintf('BT %.2F %.2F Td %s Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$txt2);
            }
            if($this->underline)
                $s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
            if($this->ColorFlag)
                $s .= ' Q';
            if($link)
                $this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
        }
        if($s)
            $this->_out($s);
        $this->lasth = $h;
        if($ln>0)
        {
            // Go to next line
            $this->y += $h;
            if($ln==1)
                $this->x = $this->lMargin;
        }
        else
            $this->x += $w;
    }

    function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
    {
        // Output text with automatic or explicit line breaks
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

    function Ln($h=null)
    {
        // Line feed; default value is last cell height
        $this->x = $this->lMargin;
        if($h===null)
            $this->y += $this->lasth;
        else
            $this->y += $h;
    }

    function _dounderline($x, $y, $txt)
    {
        // Underline text
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
        return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
    }

    function _loadfont($font)
    {
        // Load a font definition file from the font directory
        include($this->fontpath.$font);
        $a = get_defined_vars();
        if(!isset($a['name']))
            $this->Error('Could not include font definition file');
        return $a;
    }

}