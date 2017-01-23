<?php
namespace PdfBuilder\Plugins;

use PdfBuilder\PdfDocument;

class PdfShape
{

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @param PdfDocument $pdfDocument
     */
    public function __construct(PdfDocument $pdfDocument) {
        $this->_pdfDocument = $pdfDocument;
    }

    /**
     * Set color for all stroking operations
     *
     * @param      $r
     * @param null $g
     * @param null $b
     * @return PdfDocument
     */
    public function setDrawColor($r, $g = null, $b = null)
    {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->_pdfDocument->data['DrawColor'] = sprintf('%.3F G', $r / 255);
        } else {
            $this->_pdfDocument->data['DrawColor'] = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }

        if ($this->_pdfDocument->getPageCount() > 0) {
            $this->_pdfDocument->out($this->_pdfDocument->data['DrawColor']);
        }

        return $this->_pdfDocument;
    }

    /**
     * Set color for all filling operations
     *
     * @param      $r
     * @param null $g
     * @param null $b
     * @return PdfDocument
     */
    function setFillColor($r, $g = null, $b = null)
    {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->_pdfDocument->data['FillColor'] = sprintf('%.3F g', $r / 255);
        } else {
            $this->_pdfDocument->data['FillColor'] = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        }

        $this->_pdfDocument->data['ColorFlag'] =
            ($this->_pdfDocument->data['FillColor'] != $this->_pdfDocument->data['TextColor']);

        if ($this->_pdfDocument->getPageCount() > 0) {
            $this->_pdfDocument->out($this->_pdfDocument->data['FillColor']);
        }

        return $this->_pdfDocument;
    }

    /**
     * Set line width
     *
     * @param $width
     * @return PdfDocument
     */
    function setLineWidth($width)
    {
        $this->_pdfDocument->data['LineWidth'] = $width;

        if ($this->_pdfDocument->getPageCount() > 0) {
            $this->_pdfDocument->out(sprintf('%.2F w', $width * $this->_pdfDocument->getScaleFactor()));
        }

        return $this->_pdfDocument;
    }

    /**
     * Draw a line
     *
     * @param $x1
     * @param $y1
     * @param $x2
     * @param $y2
     * @return PdfDocument
     */
    function line($x1, $y1, $x2, $y2)
    {
        $k = $this->_pdfDocument->getScaleFactor();
        $h = $this->_pdfDocument->getHeight();

        $this->_pdfDocument->out(
            sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $k,($h - $y1) * $k, $x2 * $k,($h - $y2) * $k)
        );

        return $this->_pdfDocument;
    }

    /**
     * Draw a rectangle
     *
     * @param        $x
     * @param        $y
     * @param        $w
     * @param        $h
     * @param string $style
     * @return PdfDocument
     */
    function rect($x, $y, $w, $h, $style='')
    {
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $k  = $this->_pdfDocument->getScaleFactor();
        $dh = $this->_pdfDocument->getHeight();

        $this->_pdfDocument->out(
            sprintf('%.2F %.2F %.2F %.2F re %s', $x * $k, ($dh - $y) * $k, $w * $k, - $h * $k, $op)
        );

        return $this->_pdfDocument;
    }
}
