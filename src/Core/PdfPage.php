<?php
namespace PdfBuilder\Core;

use PdfBuilder\PdfDocument;
use PdfBuilder\Exception\PdfException;

class PdfPage
{

    /**
     * @var PdfDocument
     */
    protected $_pdfDocument;

    /**
     * @var array
     */
    protected $_curPageSize = array();

    /**
     * @var string
     */
    protected $_curOrientation;

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
     * @var float
     */
    protected $_lineWidth;

    /**
     * @var array
     */
    public $pageLinks = array();

    /**
     * @var
     */
    protected $_pageBreakTrigger;

    /**
     * @var
     */
    protected $_autoPageBreak;

    /**
     * Output buffer for page content
     *
     * @var string
     */
    public $pageBuffer;

    /**
     * New PDF page constructor.
     *
     * Any function called in here that outputs anything
     * to the page buffer need to do so directly, and not through
     * $pdfDocument->out() that has page-count != current page.
     *
     * @param  PdfDocument $pdfDocument
     * @param  string      $orientation
     * @param  string      $size
     * @throws PdfException
     */
    public function __construct(PdfDocument $pdfDocument, $orientation, $size)
    {
        $this->_pdfDocument = $pdfDocument;
        $this->pageBuffer   = "2 J\n";
        $size               = $this->_getPageSize($size);
        $this->_curPageSize = $size;

        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->_curOrientation = 'P';
            $this->_w = $size[0];
            $this->_h = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->_curOrientation = 'L';
            $this->_w = $size[1];
            $this->_h = $size[0];
        } else {
            throw new PdfException('Incorrect orientation: '.$orientation);
        }

        $this->_wPt = $this->_w * $pdfDocument->getScaleFactor();
        $this->_hPt = $this->_h * $pdfDocument->getScaleFactor();

        if ($pdfDocument->getPageCount() > 0) {
            $defPageSize = $pdfDocument->getDefPageSize();
            if ($orientation != $pdfDocument->getDefOrientation() || $size[0] != $defPageSize[0] || $size[1] != $defPageSize[1]) {
                $pdfDocument->addPageSize($this->_wPt, $this->_hPt);
            }
        } else {
            $pdfDocument->setDefPageSize($this->getCurPageSize());
            $this->setLineWidth(0.567 / $pdfDocument->getScaleFactor());
        }

        $margin = 28.35 / $pdfDocument->getScaleFactor();
        $this->setMargins($margin, $margin);

        $this->_cMargin = $margin / 10;
        $this->setAutoPageBreak(true, 2 * $margin);

        $this->_x = $this->_lMargin;
        $this->_y = $this->_tMargin;
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
     * Get page orientation
     *
     * @return string
     */
    public function getOrientation()
    {
        return $this->_curOrientation;
    }

    /**
     * Get page size
     *
     * @param  $size
     * @return array|string
     * @throws PdfException
     */
    protected function _getPageSize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!$this->_pdfDocument->getStdPageSize($size)) {
                throw new PdfException('Unknown page size: '.$size);
            }
            $a = $this->_pdfDocument->getStdPageSize($size);

            return array(
                $a[0] / $this->getDocument()->getScaleFactor(),
                $a[1] / $this->getDocument()->getScaleFactor()
            );
        } elseif ($size[0] > $size[1]) {
            return array($size[1], $size[0]);
        }

        return $size;
    }

    /**
     * Get current page-size
     *
     * @return array
     */
    public function getCurPageSize()
    {
        return $this->_curPageSize;
    }

    /**
     * Set line width, outputting directly to the page buffer
     * since it's called when page-count might be in flux.
     *
     * @param  $width
     * @return PdfDocument
     */
    function setLineWidth($width)
    {
        $this->_pdfDocument->data['lineWidth']  = $width;
        $this->pageBuffer .= sprintf("%.2F w\n", $width * $this->getDocument()->getScaleFactor());

        return $this->_pdfDocument;
    }

    /**
     * Set page margins
     *
     * @param              $left
     * @param              $top
     * @param  null        $right
     * @return PdfDocument
     */
    public function setMargins($left, $top, $right = null)
    {
        $this->_lMargin = $left;
        $this->_tMargin = $top;

        if ($right === null) {
            $right = $left;
        }
        $this->_rMargin = $right;

        return $this->_pdfDocument;
    }

    /**
     * Set the left margin
     *
     * @param $margin
     */
    public function setLeftMargin($margin)
    {
        $this->_lMargin = $margin;

        if ($this->getDocument()->getPageNo() > 0 && $this->_x < $margin) {
            $this->_x = $margin;
        }
    }

    /**
     * Get left margin
     *
     * @return float
     */
    public function getLeftMargin()
    {
        return $this->_lMargin;
    }

    /**
     * Set top margin
     *
     * @param  $margin
     * @return PdfDocument
     */
    public function setTopMargin($margin)
    {
        $this->_tMargin = $margin;

        return $this->_pdfDocument;
    }

    /**
     * Get cell margin
     *
     * @return float
     */
    public function getCellMargin()
    {
        return $this->_cMargin;
    }

    /**
     * Get right margin
     *
     * @return float
     */
    public function getRightMargin()
    {
        return $this->_rMargin;
    }

    /**
     * Set right margin
     *
     * @param  $margin
     * @return PdfDocument
     */
    public function setRightMargin($margin)
    {
        $this->_rMargin = $margin;

        return $this->_pdfDocument;
    }

    /**
     * Set auto page break mode and triggering margin
     *
     * @param  $auto
     * @param  int         $margin
     * @return PdfDocument
     */
    public function setAutoPageBreak($auto, $margin = 0)
    {
        $this->_autoPageBreak    = $auto;
        $this->_bMargin          = $margin;
        $this->_pageBreakTrigger = $this->_h - $margin;

        return $this->_pdfDocument;
    }

    /**
     * Accept automatic page break or not
     *
     * @return mixed
     */
    public function acceptPageBreak()
    {
        return $this->_autoPageBreak;
    }

    /**
     * Get page break trigger
     *
     * @return mixed
     */
    public function getPageBreakTrigger()
    {
        return $this->_pageBreakTrigger;
    }

    /**
     * Get height
     *
     * @return float
     */
    public function getHeight()
    {
        return $this->_h;
    }

    /**
     * Get width
     *
     * @return float
     */
    public function getWidth()
    {
        return $this->_w;
    }

    /**
     * Get x position
     *
     * @return float
     */
    public function getX()
    {
        return $this->_x;
    }

    /**
     * Set x position
     *
     * @param  $x
     * @return PdfDocument
     */
    public function setX($x)
    {
        if ($x >= 0) {
            $this->_x = $x;
        } else {
            $this->_x = $this->_w + $x;
        }

        return $this->_pdfDocument;
    }

    /**
     * Get y position
     *
     * @return float
     */
    public function getY()
    {
        return $this->_y;
    }

    /**
     * Set y position and reset x
     *
     * @param  $y
     * @return PdfDocument
     */
    public function setY($y)
    {
        $this->_x = $this->_lMargin;

        if ($y >= 0) {
            $this->_y = $y;
        } else {
            $this->_y = $this->_h + $y;
        }

        return $this->_pdfDocument;
    }

    /**
     * Set x and y positions
     *
     * @param  $x
     * @param  $y
     * @return PdfDocument
     */
    public function setXY($x, $y)
    {
        $this->setY($y);
        $this->setX($x);

        return $this->_pdfDocument;
    }

    /**
     * Create a new internal link
     *
     * @return int
     */
    public function addLink()
    {
        $this->_pdfDocument->internalLinks[] = array(0, 0);
        return count($this->_pdfDocument->internalLinks);
    }

    /**
     * Set destination of internal link
     *
     * @param  $link
     * @param  int $y
     * @param  $page
     * @return PdfDocument
     */
    public function setLink($link, $y = 0, $page = -1)
    {
        if ($y == -1) {
            $y = $this->_y;
        }
        if ($page == -1) {
            $page = $this->_pdfDocument->getPageNo();
        }
        $this->_pdfDocument->internalLinks[$link] = array($page, $y);

        return $this->_pdfDocument;
    }

    /**
     * Put a link on the page
     *
     * @param  $x
     * @param  $y
     * @param  $w
     * @param  $h
     * @param  $link
     * @return PdfDocument
     */
    public function link($x, $y, $w, $h, $link)
    {
        $scaleFactor = $this->_pdfDocument->getScaleFactor();

        $this->pageLinks[] = array(
            $x * $scaleFactor,
            $this->_hPt - $y * $scaleFactor,
            $w * $scaleFactor,
            $h * $scaleFactor,
            $link
        );

        return $this->_pdfDocument;
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
        if (method_exists($this->getDocument(), $method)) {
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
