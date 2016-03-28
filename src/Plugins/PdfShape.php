<?php
namespace PdfBuilder\Plugins;

use PdfBuilder\PdfDocument;

class PdfShape {

    /**
     * @var PdfDocument
     */
    protected $_pdfBuilder;

    /**
     * @param PdfDocument $pdfBuilder
     */
    public function __construct(PdfDocument $pdfBuilder) {
        $this->_pdfBuilder = $pdfBuilder;
    }


    function SetDrawColor($r, $g=null, $b=null)
    {
        // Set color for all stroking operations
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->DrawColor = sprintf('%.3F G',$r/255);
        else
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
        if($this->page>0)
            $this->_out($this->DrawColor);
    }

    function SetFillColor($r, $g=null, $b=null)
    {
        // Set color for all filling operations
        if(($r==0 && $g==0 && $b==0) || $g===null)
            $this->FillColor = sprintf('%.3F g',$r/255);
        else
            $this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        $this->ColorFlag = ($this->FillColor!=$this->TextColor);
        if($this->page>0)
            $this->_out($this->FillColor);
    }

    function SetLineWidth($width)
    {
        // Set line width
        $this->LineWidth = $width;
        if($this->page>0)
            $this->_out(sprintf('%.2F w',$width*$this->k));
    }


    function Line($x1, $y1, $x2, $y2)
    {
        // Draw a line
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
    }

    function Rect($x, $y, $w, $h, $style='')
    {
        // Draw a rectangle
        if($style=='F')
            $op = 'f';
        elseif($style=='FD' || $style=='DF')
            $op = 'B';
        else
            $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
    }

}