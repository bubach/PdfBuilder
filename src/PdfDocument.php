<?php
namespace PdfBuilder;

use PdfBuilder\Core\PdfPage;
use PdfBuilder\Core\PdfOutput;
use PdfBuilder\Exception\PdfException;

define('PDFBUILDER_VERSION','1.00');

class PdfDocument
{

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
        'addHeader'      => 'PdfBuilder\Core\PdfHeader',
        'outputHeader'   => 'PdfBuilder\Core\PdfHeader',
        'addFooter'      => 'PdfBuilder\Core\PdfFooter',
        'outputFooter'   => 'PdfBuilder\Core\PdfFooter',
        'addImage'       => 'PdfBuilder\Plugins\PdfImage',
        'setTextColor'   => 'PdfBuilder\Plugins\PdfText',
        'getStringWidth' => 'PdfBuilder\Plugins\PdfText',
        'addText'        => 'PdfBuilder\Plugins\PdfText',
        'addLn'          => 'PdfBuilder\Plugins\PdfText',
        'addCell'        => 'PdfBuilder\Plugins\PdfText',
        'addMultiCell'   => 'PdfBuilder\Plugins\PdfText',
        'setFont'        => 'PdfBuilder\Plugins\PdfText',
        'setDrawColor'   => 'PdfBuilder\Plugins\PdfShape',
        'setFillColor'   => 'PdfBuilder\Plugins\PdfShape',
        'setLineWidth'   => 'PdfBuilder\Plugins\PdfShape',
        'addLine'        => 'PdfBuilder\Plugins\PdfShape',
        'addRect'        => 'PdfBuilder\Plugins\PdfShape',
    );

    /**
     * @var array One instance reference for each plugin
     */
    protected $pluginInstances = array();

    /**
     * Document settings, in array for easy plugin usage
     * and copy over to new page.
     *
     * @var array
     */
    public $data = array(
        'fontFamily' => '',
        'fontStyle' => '',
        'fontSizePt' => 12,
        'underline' => false,
        'lineWidth' => null,
        'drawColor' => '0 G',
        'fillColor' => '0 g',
        'textColor' => '0 g',
        'colorFlag' => false,
    );

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
        $fontPath = empty($fontPath) ? dirname(__FILE__) . '/Fonts/' : $fontPath;

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
     * Shortcut for FPDF compatibility with classes that extend PdfDocument
     *
     * @return mixed
     */
    public function acceptPageBreak()
    {
        return $this->getPage()->acceptPageBreak();
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
     * @return int Current page number
     */
    public function getPageNo()
    {
        return $this->_curPage;
    }

    /**
     * For FPDF compatibility
     *
     * @return int
     */
    public function pageNo()
    {
        return $this->getPageNo();
    }

    /**
     * Get the amount of pages in the document
     *
     * @return int
     */
    public function getPageCount()
    {
        return count($this->_pages);
    }

    /**
     * Get a PDF page instance, numbered or current.
     *
     * @param  null $number
     * @return PdfPage|false
     */
    public function getPage($number = null)
    {
        $number = (is_null($number) || ($number - 1 < 0)) ? ($this->getPageNo() - 1) : ($number - 1);

        if (isset($this->_pages[$number])) {
            $this->_curPage = $number + 1;
            return $this->_pages[$number];
        }

        return false;
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
     * Close the document
     */
    public function close()
    {
        if ($this->_curState == self::STATE_END_DOC) {
            return;
        }
        if ($this->getPageCount() < 1) {
            $this->addPage();
        }

        $this->outputFooter();
        $this->setState(self::STATE_END_PAGE);
        $this->_pdfOutput->endDoc();
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
        if ($this->getPageCount() > 0) {
            $dataCopy = $this->data;
            $this->outputFooter();

            $this->setState(self::STATE_END_PAGE);
            $orientation = empty($orientation) ? $this->getPage()->getOrientation() : $orientation;
            $size        = empty($size) ? $this->getPage()->getCurPageSize() : $size;
        } else {
            $orientation = empty($orientation) ? $this->_defOrientation : $orientation;
            $size        = empty($size) ? $this->_defSizeFormat : $size;
        }

        $this->setState(self::STATE_NEW_PAGE);
        $this->_curPage++;
        $this->_pages[] = new PdfPage($this, $orientation, $size);

        $dataCopy = (isset($dataCopy)) ? $dataCopy : $this->data;
        $this->outputHeader();

        $font_data = array('fontFamily', 'fontStyle', 'underline', 'fontSizePt');
        if (!empty($dataCopy['fontFamily'])) {
            $style = $dataCopy['fontStyle'] . ($dataCopy['underline']) ? 'U' : '';
            $this->setFont($dataCopy['fontFamily'], $style, $dataCopy['fontSizePt']);
        }

        foreach ($dataCopy as $key => $value) {
            if ($value !== $this->data[$key] && !in_array($key, $font_data)) {
                $method = 'set' . ucfirst($key);
                $this->$method($value);
            }
        }

        return $this->getPage();
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

        if (empty($destination) && empty($name)) {
            $name        = 'doc.pdf';
            $destination = 'I';
        } elseif (empty($destination)) {
            $destination = 'F';
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
                throw new PdfException(
                    "Some data has already been outputted, can't send PDF file (output started at $file:$line)"
                );
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
     * Fluently call methods in PdfPage or other plugins. Also
     * acts as getter / setter for PDF-settings and variables.
     *
     * @param  $method
     * @param  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $class   = isset($this->plugins[$method]) ? $this->plugins[$method] : false;
        $class   = isset($this->plugins[lcfirst($method)]) ? $this->plugins[lcfirst($method)] : $class;
        $operand = false;

        if (!$class) {
            $operand = (strlen($method) > 3) ? substr(lcfirst($method), 0, 3) : false;
            $field   = ($operand && strlen($method) > 3) ? lcfirst(substr($method, 3)) : $method;
        }

        if (!$class) {
            foreach (array("add", "get", "set") as $op) {
                if (isset($this->plugins[$op.ucfirst($method)])) {
                    $class  = $this->plugins[$op.ucfirst($method)];
                    $method = $op.ucfirst($method);
                    break;
                } elseif (method_exists($this, $op.ucfirst($method))) {
                    return call_user_func_array(array($this, $op.ucfirst($method)), $parameters);
                }
            }
        }

        if ($class) {
            if (!isset($this->pluginInstances[$class])) {
                $this->pluginInstances[$class] = new $class($this);
            }
            return call_user_func_array(
                array($this->pluginInstances[$class], $method),
                $parameters
            );
        } elseif ($this->getPageCount() > 0 && method_exists($this->getPage(), $method)) {
            return call_user_func_array(
                array($this->getPage(), $method),
                $parameters
            );
        } elseif ($operand == "set") {
            if (is_array($parameters) && count($parameters) < 2) {
                $parameters = reset($parameters);
            }
            $this->data[$field] = $parameters;
            return $this;
        } elseif ($operand == "get") {
            return isset($this->data[$field]) ? $this->data[$field] : false;
        } else {
            return false;
        }
    }
}
