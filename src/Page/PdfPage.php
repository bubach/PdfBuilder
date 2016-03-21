<?php
namespace bubach\PdfBuilder\Page;

use bubach\PdfBuilder\PdfDocument;
use bubach\PdfBuilder\Plugins\PdfImage as PdfImage;
use bubach\PdfBuilder\Plugins\PdfShape as PdfShape;
use bubach\PdfBuilder\Plugins\PdfText as PdfText;
use bubach\PdfBuilder\Exception\PdfException;

class PdfPage {

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @var array Page sizes
     */
    protected $_stdPageSizes = array(
        'a3'     => array(841.89, 1190.55),
        'a4'     => array(595.28, 841.89),
        'a5'     => array(420.94, 595.28),
        'letter' => array(612, 792),
        'legal'  => array(612, 1008)
    );

    /**
     * @var float
     */
    public $_scaleFactor = 1;

    /**
     * @var array|string
     */
    protected $_defPageSize = '';

    /**
     * @var array|string
     */
    protected $_curPageSize = '';

    /**
     * @var string
     */
    protected $_defOrientation = '';

    /**
     * @var float Width
     */
    protected $_w;

    /**
     * @var float Height
     */
    protected $_h;

    /**
     * @var float Current X position in user unit
     */
    protected $_x;

    /**
     * @var float Current Y position in user unit
     */
    protected $_y;

    /**
     * @var float Left margin
     */
    protected $_lMargin;

    /**
     * @var float Top margin
     */
    protected $_tMargin;

    /**
     * @var float Right margin
     */
    protected $_rMargin;

    /**
     * @var float Page break margin
     */
    protected $_bMargin;

    /**
     * @var float Cell margin
     */
    protected $_cMargin;

    /**
     * @var float Width in points
     */
    protected $_wPt;

    /**
     * @var float Height in points
     */
    protected $_hPt;

    /**
     * @var
     */
    protected $_pageBreakTrigger;

    /**
     * @var
     */
    protected $_autoPageBreak;

    /**
     * @var null Zoom display mode
     */
    protected $_zoomMode;

    /**
     * @var null Layout mode
     */
    protected $_layoutMode;

    /**
     * Page settings, in array for easy plugin usage
     * and copy over to new page.
     *
     * @var array
     */
    protected $_data = array();

    /**
     * Output buffer for page content
     *
     * @var
     */
    public $outBuffer;

    /**
     * New PDF page, constructor
     *
     * @param PdfDocument $pdfDocument
     * @param string      $orientation
     * @param string      $unit
     * @param string      $size
     * @throws PdfException
     */
    public function __construct(PdfDocument $pdfDocument, $orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        $this->_pdfBuilder = $pdfDocument;

        switch ($unit) {
            case 'pt':
                $this->_scaleFactor = 1;
                break;
            case 'mm':
                $this->_scaleFactor = 72/25.4;
                break;
            case 'cm':
                $this->_scaleFactor = 72/2.54;
                break;
            case 'in':
                $this->_scaleFactor = 72;
                break;
            default:
                throw new PdfException('Incorrect unit: '.$unit);;
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
            throw new PdfException('Incorrect orientation: '.$orientation);
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
     * Get main document/builder instance
     *
     * @return PdfDocument
     */
    public function getDocument()
    {
        return $this->_pdfDocument;
    }

    /**
     * Set page data, used by plugins.
     *
     * @param  string|array $key
     * @param  mixed $value
     * @return PdfDocument
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->_data = $key;
        } else {
            $this->_data[$key] = $value;
        }
        return $this;
    }

    /**
     * Get page data from the object.
     *
     * @param  string          $key
     * @return string | array  $data
     */
    public function getData($key = null)
    {
        if ($key) {
            return isset($this->_data[$key]) ? $this->_data[$key] : null;
        } else {
            return $this->_data;
        }
    }

    /**
     * Get page size
     *
     * @param $size
     * @return array|string
     * @throws PdfException
     */
    protected function _getPageSize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->_stdPageSizes[$size])) {
                throw new PdfException('Unknown page size: '.$size);
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
     * Set page margins
     *
     * @param $left
     * @param $top
     * @param null $right
     */
    public function setMargins($left, $top, $right = null)
    {
        $this->_lMargin = $left;
        $this->_tMargin = $top;
        if ($right === null) {
            $right = $left;
        }
        $this->_rMargin = $right;
    }

    /**
     * Set the left margin
     *
     * @param $margin
     */
    public function setLeftMargin($margin)
    {
        $this->_lMargin = $margin;
        if ($this->getDocument()->getCurrPage() > 0 && $this->_x < $margin) {
            $this->_x = $margin;
        }
    }

    /**
     * Set top margin
     *
     * @param $margin
     */
    public function setTopMargin($margin)
    {
        $this->_tMargin = $margin;
    }

    /**
     * Set right margin
     *
     * @param $margin
     */
    public function setRightMargin($margin)
    {
        $this->_rMargin = $margin;
    }

    /**
     * Set auto page break mode and triggering margin
     *
     * @param $auto
     * @param int $margin
     */
    public function setAutoPageBreak($auto, $margin = 0)
    {
        $this->_autoPageBreak    = $auto;
        $this->_bMargin          = $margin;
        $this->_pageBreakTrigger = $this->_h - $margin;
    }

    /**
     * Set display mode in viewer
     *
     * @param  $zoom
     * @param  string $layout
     * @throws PdfException
     */
    public function setDisplayMode($zoom, $layout = 'default')
    {
        $zoomModes   = array('fullpage', 'fullwidth', 'real', 'default');
        $layoutModes = array('single', 'continuous', 'two', 'default');

        if ( !is_string($zoom) || in_array($zoom, $zoomModes) ) {
            $this->_zoomMode = $zoom;
        } else {
            throw new PdfException('Incorrect zoom display mode: '.$zoom);
        }

        if ( in_array($layout, $layoutModes)) {
            $this->_layoutMode = $layout;
        } else {
            throw new PdfException('Incorrect layout display mode: '.$layout);
        }
    }

    /**
     * @param $orientation
     * @param $size
     */
    protected function _beginPage($orientation, $size)
    {
        //$this->getDocument()->_page++;
        //$this->getDocument()->_pages[$this->getDocument()->_page] = '';

        $builder = $this->_pdfDocument;
        $builder->setState($builder::STATE_NEW_PAGE);

        $this->_x = $this->_lMargin;
        $this->_y = $this->_tMargin;

        //$this->FontFamily = '';

        $orientation = empty($orientation) ? $this->_defOrientation : strtoupper($orientation[0]);
        $size = empty($size) ? $this->_defPageSize : $this->_getPageSize($size);

        if ($orientation != $this->_curOrientation || $size[0] != $this->_curPageSize[0] || $size[1] != $this->_curPageSize[1]) {
            if ($orientation == 'P') {
                $this->_w = $size[0];
                $this->_h = $size[1];
            } else {
                $this->_w = $size[1];
                $this->_h = $size[0];
            }

            $this->_wPt = $this->_w * $this->_scaleFactor;
            $this->_hPt = $this->_h * $this->_scaleFactor;

            $this->_pageBreakTrigger = $this->_h - $this->_bMargin;
            $this->_curOrientation   = $orientation;
            $this->_curPageSize      = $size;
        }
        if ($orientation != $this->_defOrientation || $size[0] != $this->_defPageSize[0] || $size[1] != $this->_defPageSize[1]) {
            $this->getDocument()->pageSizes[$this->getDocument()->getCurrPageNo()] = array($this->_wPt, $this->_hPt);
        }
    }

    /**
     * End this page
     */
    public function _endPage()
    {
        $builder = $this->_pdfDocument;
        $builder->setState($builder::STATE_END_PAGE);
    }

    /**
     * Call plugin functions, if found in lookup-table
     *
     * @param  $method
     * @param  $parameters
     * @return mixed
     */
    public function __call($method, $parameters = array())
    {
        if (is_callable(array($this->getDocument(), $method))) {
            return call_user_func_array(array($this->getDocument(), $method), $parameters);
        }

        $className = isset($this->getDocument()->plugins[$method]) ? $this->getDocument()->plugins[$method] : false;
        if ($className) {
            return call_user_func_array(array($className, $method), $parameters);
        } else {
            return false;
        }
    }

}