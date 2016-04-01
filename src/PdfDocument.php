<?php
namespace PdfBuilder;

use PdfBuilder\Core\PdfPage;
use PdfBuilder\Core\PdfOutput;
use PdfBuilder\Exception\PdfException;

define('PDFBUILDER_VERSION','1.00');

class PdfDocument {

    /**
     * @var int Current page number
     */
    protected $_curPage = 0;

    /**
     * @var array PDF pages
     */
    protected $_pages = array();

    /**
     * @var int Current document state
     */
    protected $_curState = 1;

    /**
     * @var string PDF version used
     */
    public $pdfVersion = '1.3';

    /**
     * @var null Zoom display mode
     */
    protected $_zoomMode;

    /**
     * @var null Layout mode
     */
    protected $_layoutMode;

    /**
     * @var float
     */
    protected $_scaleFactor = 1;

    /**
     * Internal document links
     *
     * @var array
     */
    public $internalLinks = array();

    /**
     * constants for PDF state
     */
    const STATE_END_PAGE = 1;
    const STATE_NEW_PAGE = 2;
    const STATE_END_DOC  = 3;

    /**
     * @var string
     */
    protected $_defSizeFormat = 'A4';

    /**
     * @var array
     */
    protected $_stdPageSize = array(
        'a3'     => array(841.89, 1190.55),
        'a4'     => array(595.28, 841.89),
        'a5'     => array(420.94, 595.28),
        'letter' => array(612, 792),
        'legal'  => array(612, 1008)
    );

    /**
     * @var array
     */
    protected $_defPageSize = array();

    /**
     * Non default page sizes
     *
     * @var array
     */
    protected $_pageSizes;

    /**
     * @var string
     */
    protected $_defOrientation = 'P';

    /**
     * Array holding plugin objects & methods
     * preset for core plugins.
     *
     * @var array
     */
    public $plugins = array(
        'addHeader'    => 'PdfBuilder\Core\PdfHeader',
        'outputHeader' => 'PdfBuilder\Core\PdfHeader',
        'addFooter'    => 'PdfBuilder\Core\PdfFooter',
        'outputFooter' => 'PdfBuilder\Core\PdfFooter',
        'addImage'     => 'PdfBuilder\Plugins\PdfImage',
        'setTextColor' => 'PdfBuilder\Plugins\PdfText',
        'text'         => 'PdfBuilder\Plugins\PdfText',
        'cell'         => 'PdfBuilder\Plugins\PdfText',
        'setFont'      => 'PdfBuilder\Plugins\PdfText',
        'addText'      => 'PdfBuilder\Plugins\PdfText',
        'addCircle'    => 'PdfBuilder\Plugins\PdfShape',
        'addRectangle' => 'PdfBuilder\Plugins\PdfShape',
    );

    /**
     * Document settings, in array for easy plugin usage
     * and copy over to new page.
     *
     * @var array
     */
    public $data = array();

    /**
     * @var PdfOutput
     */
    protected $_pdfOutput;

    /**
     * PdfBuilder constructor
     *
     * @param string $orientation
     * @param string $unit
     * @param string $size
     * @param null   $fontPath
     */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $fontPath = null)
    {
        $fontPath = empty($fontPath) ? dirname(__FILE__).'/Fonts/' : $fontPath;

        $this->_doChecks();
        $this->setFontPath($fontPath);
        $this->setScaleFactor($unit);
        $this->setDefOrientation($orientation);
        $this->setDefSizeFormat($size);
        $this->setDisplayMode('default');
        $this->setDoCompress(false);

        if (function_exists('gzcompress')) {
            $this->setDoCompress(true);
        }

        $this->_pdfOutput = new PdfOutput($this);
    }

    /**
     * Some initial checks
     *
     * @throws PdfException
     */
    private function _doChecks()
    {
        if (sprintf('%.1F', 1.0) != '1.0') {
            throw new PdfException('This version of PHP is not supported');
        }

        if (!function_exists('mb_strlen')) {
            throw new PdfException('mbstring extension is not available');
        }

        if (ini_get('mbstring.func_overload') & 2) {
            throw new PdfException('mbstring overloading must be disabled');
        }

        if (get_magic_quotes_runtime() && version_compare(PHP_VERSION, '5.3.0', '<')) {
            @set_magic_quotes_runtime(0);
        }
    }

    /**
     * Set unit/scale-factor
     *
     * @param  $unit
     * @return $this
     * @throws PdfException
     */
    public function setScaleFactor($unit)
    {
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
        return $this;
    }

    /**
     * Get document scale-factor
     *
     * @return float
     */
    public function getScaleFactor()
    {
        return $this->_scaleFactor;
    }

    /**
     * Add non-default page-size
     *
     * @param  $wPt
     * @param  $hPt
     * @return $this
     */
    public function addPageSize($wPt, $hPt)
    {
        $this->_pageSizes[$this->_curPage] = array($wPt, $hPt);
        return $this;
    }

    /**
     * Get custom page size
     *
     * @param  $number
     * @return bool
     */
    public function getPageSize($number)
    {
        return isset($this->_pageSizes[$number]) ? $this->_pageSizes[$number] : false;
    }

    /**
     * Get the standard page size(s)
     *
     * @param  $number
     * @return array|bool
     */
    public function getStdPageSize($number = false)
    {
        if ($number) {
            return isset($this->_stdPageSize[$number]) ? $this->_stdPageSize[$number] : false;
        }
        return $this->_stdPageSize;
    }

    /**
     * Set the default page size
     *
     * @param  $size
     * @return $this
     */
    public function setDefPageSize($size)
    {
        $this->_defPageSize = $size;
        return $this;
    }

    /**
     * Get default page-size
     *
     * @return array
     */
    public function getDefPageSize()
    {
        return $this->_defPageSize;
    }

    /**
     * Get default orientation
     *
     * @return string
     */
    public function getDefOrientation()
    {
        return $this->_defOrientation;
    }

    /**
     * Set default orientation
     *
     * @param  string $defOrientation
     * @return $this
     */
    public function setDefOrientation($defOrientation)
    {
        $this->_defOrientation = $defOrientation;
        return $this;
    }

    /**
     * Get default size format
     *
     * @return string
     */
    public function getDefSizeFormat()
    {
        return $this->_defSizeFormat;
    }

    /**
     * Set default size format
     *
     * @param string $defSizeFormat
     */
    public function setDefSizeFormat($defSizeFormat)
    {
        $this->_defSizeFormat = $defSizeFormat;
    }

    /**
     * Set document state
     *
     * @param $state
     */
    public function setState($state)
    {
        $this->_curState = $state;
    }

    /**
     * Get current document state
     *
     * @return int
     */
    public function getState()
    {
        return $this->_curState;
    }

    /**
     * @return int Current page number
     */
    public function getCurPageNo()
    {
        return $this->_curPage;
    }

    /**
     * Get document zoom mode
     *
     * @return null
     */
    public function getZoomMode()
    {
        return $this->_zoomMode;
    }

    /**
     * @return PdfOutput
     */
    public function getOutputter()
    {
        return $this->_pdfOutput;
    }

    /**
     * Get document layout mode
     *
     * @return null
     */
    public function getLayoutMode()
    {
        return $this->_layoutMode;
    }

    /**
     * Add plugin, will use all public methods except constructor
     *
     * $param string  Loaded class-name
     */
    public function addPlugin($className)
    {
        $methodNames = get_class_methods($className);
        foreach ($methodNames as $name) {
            if ($name != "__construct" && !isset($this->plugins[$name])) {
                $this->plugins[$name] = $className;
            }
        }
    }

    /**
     * Add a TrueType, OpenType or Type1 font
     *
     * @param $family
     * @param string $style
     * @param string $file
     * @param bool $uni
     */
    public function addFont($family, $style = '', $file = '', $uni = false)
    {
        $this->getOutputter()->getFontOutputter()->addFont($family, $style, $file, $uni);
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
     * close the document
     */
    public function close()
    {
        if ($this->_curState == self::STATE_END_DOC) {
            return;
        }
        if ($this->_curPage == 0) {
            $this->addPage();
        }

        $this->outputFooter();
        $this->setState(self::STATE_END_PAGE);
        $this->_pdfOutput->endDoc();
    }

    /**
     * Get a PDF page instance
     */
    public function getPage($number = null)
    {
        $number = empty($number) ? $this->getCurPageNo() : $number;
        return isset($this->_pages[$number - 1]) ? $this->_pages[$number - 1] : $this->addPage();
    }

    /**
     * Add new PDF page to document
     *
     * @param  string  $orientation
     * @param  string  $size
     * @return PdfPage
     */
    public function addPage($orientation = '',  $size = '')
    {
        $this->_curState = self::STATE_END_PAGE;

        if ($this->_curPage > 0) {
            $this->outputFooter();
            $this->setState(self::STATE_END_PAGE);

            $orientation = empty($orientation) ? $this->getPage()->getOrientation() : $orientation;
            $size        = empty($size) ? $this->getPage()->getCurPageSize() : $size;
        } else {
            $orientation = empty($orientation) ? $this->_defOrientation : $orientation;
            $size        = empty($size) ? $this->_defSizeFormat : $size;
        }

        $this->_curPage++;
        $this->setState(self::STATE_NEW_PAGE);

        $page = new PdfPage();
        $this->_pages[] = $page;
        $page->initPage($this, $orientation, $size);

        $this->outputHeader();

        return $page;
    }

    /**
     * Output to buffer(s)
     *
     * @param $s
     */
    public function out($s)
    {
        $this->_pdfOutput->out($s);
    }

    /**
     * Output the PDF, with support for IE contype request
     *
     * @param  string $name
     * @param  string $destination
     * @throws PdfException
     * @return string
     */
    public function output($name = '', $destination = 'F')
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
            header('Content-Type: application/pdf');
            exit;
        }
        if ($this->_curState < self::STATE_END_DOC) {
            $this->close();
        }
        if (empty($name)) {
            $name        = 'doc.pdf';
            $destination = 'I';
        }

        switch (strtoupper($destination)) {
            case 'I':
                $this->_checkOutput();

                if (PHP_SAPI != 'cli') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->_pdfOutput->outBuffer;
                break;
            case 'D':
                $this->_checkOutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->_pdfOutput->outBuffer;
                break;
            case 'F':
                $f = fopen($name, 'wb');
                if (!$f) {
                    throw new PdfException('Unable to create output file: '.$name);
                }
                fwrite($f, $this->_pdfOutput->outBuffer, strlen($this->_pdfOutput->outBuffer));
                fclose($f);
                break;
            case 'S':
                return $this->_pdfOutput->outBuffer;
            default:
                throw new PdfException('Incorrect output destination: '.$destination);
        }
        return '';
    }

    /**
     * Check if we can output PDF
     *
     * @throws PdfException
     */
    private function _checkOutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                throw new PdfException("Some data has already been outputted, can't send PDF file (output started at $file:$line)");
            }
        }
        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                ob_clean();
            } else {
                throw new PdfException("Some data has already been outputted, can't send PDF file");
            }
        }
    }

    /**
     * get PDF output
     *
     * @return string
     */
    public function __toString() {
        return $this->output('', 'S');
    }

    /**
     * Magic getter, checks plugins first.
     *
     * @param  $name
     * @return string
     */
    public function __get($name)
    {
        $method = "get".ucfirst($name);
        $class  = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($class) {
            if (!is_object($class)) {
                $class = new $class($this);
                $this->plugins[$method] = $class;
            }
            return call_user_func(array($class, $method));
        } else {
            return isset($this->data[$name]) ? $this->data[$name] : false;
        }
    }

    /**
     * Magic setter, checks plugins first
     *
     * @param  $name
     * @param  $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        $method = "set".ucfirst($name);
        $class  = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($class) {
            if (!is_object($class)) {
                $class = new $class($this);
                $this->plugins[$method] = $class;
            }
            return call_user_func_array(array($class, $method), $value);
        } else {
            if (is_array($value) && count($value) < 2) {
                $value = reset($value);
            }
            $this->data[$name] = $value;
            return $this;
        }
    }

    /**
     * Call PdfPage functions
     *
     * @param  $method
     * @param  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $class = isset($this->plugins[$method]) ? $this->plugins[$method] : false;

        if ($class) {
            if (!is_object($class)) {
                $class = new $class($this);
                $this->plugins[$method] = $class;
            }
            return call_user_func_array(array($class, $method), $parameters);
        } else if (substr($method, 0, 3) == "set") {
            return $this->__set(substr($method, 3), $parameters);
        } else if (substr($method, 0, 3) == "get") {
            return $this->__get(substr($method, 3));
        } else if ($this->getCurPageNo() > 0) {
            return call_user_func_array(array($this->getPage(), $method), $parameters);
        } else {
            return false;
        }
    }

}