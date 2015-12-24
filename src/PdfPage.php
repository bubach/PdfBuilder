<?php
namespace bubach\PdfBuilder;

use bubach\PdfBuilder\PdfException;

class PdfPage {

    /**
     * @var PdfBuilder
     */
    private $_pdfBuilder;

    /**
     * @var array Page sizes
     */
    private $_stdPageSizes = array(
        'a3'     => array(841.89, 1190.55),
        'a4'     => array(595.28, 841.89),
        'a5'     => array(420.94, 595.28),
        'letter' => array(612, 792),
        'legal'  => array(612, 1008)
    );

    /**
     * @var float
     */
    private $_scaleFactor = 1;

    /**
     * @var array|string
     */
    private $_defPageSize = '';

    /**
     * @var array|string
     */
    private $_curPageSize = '';

    /**
     * @var string
     */
    private $_defOrientation = '';

    /**
     * @var float Width
     */
    private $_w;

    /**
     * @var float Height
     */
    private $_h;

    /**
     * @var float Width in points
     */
    private $_wPt;

    /**
     * @var float Height in points
     */
    private $_hPt;

    /**
     * @param PdfBuilder $pdfBuilder
     * @param string $orientation
     * @param string $unit
     * @param string $size
     * @throws PdfException
     */
    public function __construct(PdfBuilder $pdfBuilder, $orientation = 'P', $unit = 'mm', $size = 'A4') {
        $this->_pdfBuilder = $pdfBuilder;

        switch ($unit) {
            case 'pt':
                $this->_scaleFactor = 1;
            case 'mm':
                $this->_scaleFactor = 72/25.4;
            case 'cm':
                $this->_scaleFactor = 72/2.54;
            case 'in':
                $this->_scaleFactor = 72;
            default:
                throw PdfException('Incorrect unit: '.$unit);;
        }

        $size = $this->_getPageSize($size);
        $this->_defPageSize = $size;
        $this->_curPageSize = $size;

        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->_defOrientation = 'P';
            $this->_w = $size[0];
            $this->_h = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->_defOrientation = 'L';
            $this->_w = $size[1];
            $this->_h = $size[0];
        } else {
            throw PdfException('Incorrect orientation: '.$orientation);
        }

        $this->_curOrientation = $this->_defOrientation;
        $this->_wPt = $this->_w * $this->_scaleFactor;
        $this->_hPt = $this->_h * $this->_scaleFactor;

        $margin = 28.35 / $this->_scaleFactor;
        $this->setMargins($margin, $margin);

        $this->_cMargin = $margin / 10;
        $this->_lineWidth = 0.567 / $this->_scaleFactor;

        $this->setAutoPageBreak(true, 2 * $margin);
        $this->setDisplayMode('default');
    }

    /**
     * Get page size
     *
     * @param $size
     * @return array|string
     * @throws PdfException
     */
    private function _getPageSize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->_stdPageSizes[$size])) {
                throw PdfException('Unknown page size: '.$size);
            }
            $a = $this->_stdPageSizes[$size];

            return array(
                $a[0] / $this->_scaleFactor,
                $a[1] / $this->_scaleFactor
            );
        } elseif ($size[0] > $size[1]) {
            return array($size[1], $size[0]);
        }
        return $size;
    }

    /**
     * @param $orientation
     * @param $size
     */
    private function _beginPage($orientation, $size)
    {
        $this->_page++;
        $this->_pages[$this->_page] = '';

        $this->_pdfBuilder->setState(2);

        $this->_x = $this->_lMargin;
        $this->_y = $this->_tMargin;

        $this->FontFamily = '';
        // Check page size and orientation
        if($orientation=='')
            $orientation = $this->DefOrientation;
        else
            $orientation = strtoupper($orientation[0]);
        if($size=='')
            $size = $this->DefPageSize;
        else
            $size = $this->_getpagesize($size);
        if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
        {
            // New size or orientation
            if($orientation=='P')
            {
                $this->w = $size[0];
                $this->h = $size[1];
            }
            else
            {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w*$this->k;
            $this->hPt = $this->h*$this->k;
            $this->PageBreakTrigger = $this->h-$this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }
        if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
            $this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
    }

    /**
     *
     */
    function _endPage()
    {
        $this->_pdfBuilder->setState(1);
    }

}